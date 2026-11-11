<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Announcement;

use Sandadmin\Infra\Db\Exception\InvalidQueryException;
use Sandadmin\Infra\Db\Query\ListQuery;

/**
 * 公告列表查询：keyword 前缀匹配标题；state 支持 draft/published/withdrawn
 * 与派生筛选态 expired（expire_time 已过，不落库）。
 */
final readonly class AnnouncementQuery extends ListQuery
{
    public function __construct(
        int $page = 1,
        int $pageSize = self::DEFAULT_PAGE_SIZE,
        array $sort = [],
        public readonly ?string $keyword = null,
        public readonly ?string $state = null,
    ) {
        parent::__construct($page, $pageSize, $sort);
    }

    /** @param array<array-key, mixed> $query */
    public static function fromQuery(array $query): self
    {
        $base = self::fromArray($query);
        $keyword = self::text($query['keyword'] ?? null, 'keyword');
        $state = self::text($query['state'] ?? null, 'state');
        if ($state !== null
            && AnnouncementState::tryFrom($state) === null
            && $state !== AnnouncementState::DERIVED_EXPIRED
        ) {
            throw new InvalidQueryException('公告状态不正确', ['state' => 'enum:draft,published,withdrawn,expired']);
        }

        return new self($base->page, $base->pageSize, $base->sort, $keyword, $state);
    }

    private static function text(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new InvalidQueryException("{$field} 必须为字符串", [$field => 'string']);
        }

        return trim($value);
    }
}
