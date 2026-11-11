<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Org;

use Doctrine\DBAL\Connection;
use Sandadmin\Domain\Idempotency\IdempotencyConflict;
use Sandadmin\Domain\Idempotency\IdempotencyStore;

/**
 * 组织新建的幂等：同键同内容重放首次结果，同键不同内容返回 409。
 * 幂等行和业务插入在同一事务里，冲突时第二次插入一起回滚。
 * 操作人 id 不大于 0 时不写：外键必须指向真实用户。
 */
final class OrgIdempotency
{
    private const KEY_PATTERN = '/^[A-Za-z0-9._~-]{1,128}$/';

    public function __construct(private readonly ?IdempotencyStore $store)
    {
    }

    /**
     * @param array<string, mixed> $body
     * @param \Closure(): array<string, mixed> $write
     *
     * @return array<string, mixed>
     */
    public function storeOnce(Connection $connection, int $userId, ?string $key, string $action, array $body, \Closure $write): array
    {
        $cached = $this->replay($userId, $key, $action, $body);
        if ($cached !== null) {
            return $cached;
        }
        try {
            $created = $connection->transactional(function () use ($write, $userId, $key, $action, $body): array {
                $result = $write();
                $this->save($userId, $key, $action, $body, $result);

                return $result;
            });
        } catch (IdempotencyConflict) {
            $cached = $this->replay($userId, $key, $action, $body);
            if ($cached === null) {
                throw OrgException::idempotency();
            }

            return $cached;
        }

        return $created;
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>|null
     */
    private function replay(int $userId, ?string $key, string $action, array $body): ?array
    {
        $this->assertKey($key);
        $store = $this->store;
        if ($store === null || $userId <= 0 || $key === null) {
            return null;
        }
        $found = $store->findActive($userId, $key);
        if ($found === null) {
            return null;
        }
        if ($found['request_hash'] !== $this->hashOf($action, $body)) {
            throw OrgException::idempotency();
        }
        $snapshot = $found['response'];
        if ($snapshot instanceof \stdClass) {
            return self::objectToArray($snapshot);
        }
        if (is_array($snapshot) && !array_is_list($snapshot)) {
            return self::mapFields($snapshot);
        }
        throw new \RuntimeException('幂等记录损坏');
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $response
     */
    private function save(int $userId, ?string $key, string $action, array $body, array $response): void
    {
        $store = $this->store;
        if ($store === null || $userId <= 0 || $key === null) {
            return;
        }
        $store->record($userId, $key, $this->hashOf($action, $body), $response);
    }

    private function assertKey(?string $key): void
    {
        if ($key === null) {
            return;
        }
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw OrgException::invalid(['idempotency_key' => ['幂等键不正确']]);
        }
    }

    /** @param array<string, mixed> $body */
    private function hashOf(string $action, array $body): string
    {
        try {
            $encoded = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (\Throwable $error) {
            throw new \RuntimeException('幂等请求无法编码', 0, $error);
        }
        return hash('sha256', $action . '|' . $encoded);
    }

    /** @return array<string, mixed> */
    private static function objectToArray(\stdClass $value): array
    {
        return self::mapFields(get_object_vars($value));
    }

    /**
     * @param array<mixed> $fields
     *
     * @return array<string, mixed>
     */
    private static function mapFields(array $fields): array
    {
        $result = [];
        foreach ($fields as $key => $item) {
            if (!is_string($key)) {
                continue;
            }
            $result[$key] = self::normalize($item);
        }

        return $result;
    }

    private static function normalize(mixed $item): mixed
    {
        if ($item instanceof \stdClass) {
            return self::objectToArray($item);
        }
        if (!is_array($item)) {
            return $item;
        }
        if (array_is_list($item)) {
            $items = [];
            foreach ($item as $entry) {
                $items[] = self::normalize($entry);
            }

            return $items;
        }

        return self::mapFields($item);
    }
}
