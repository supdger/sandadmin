<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Announcement;

/** 公告写入输入（新建/更新共用）。fromArray 只校验形状，部门存在性由服务层校验。 */
final readonly class AnnouncementDraft
{
    /**
     * @param list<int> $deptIds
     * @param list<int> $imageFileIds
     */
    public function __construct(
        public string $title,
        public string $content,
        public AnnouncementScope $scope,
        public array $deptIds,
        public AnnouncementLevel $level,
        public bool $pinned,
        public array $imageFileIds,
        public ?\DateTimeImmutable $expireTime,
    ) {
    }

    /** @param array<array-key, mixed> $body */
    public static function fromArray(array $body): self
    {
        $fields = [];

        $title = self::string($body['title'] ?? null);
        if ($title === null || $title === '') {
            $fields['title'] = ['请填写公告标题'];
        } elseif (mb_strlen($title) > 50) {
            $fields['title'] = ['标题不能超过 50 个字'];
        }

        $content = self::string($body['content'] ?? null);
        if ($content === null || trim(strip_tags($content)) === '') {
            $fields['content'] = ['请填写公告内容'];
        }

        $scope = self::string($body['scope'] ?? null);
        $scopeEnum = $scope === null ? null : AnnouncementScope::tryFrom($scope);
        if ($scopeEnum === null) {
            $fields['scope'] = ['发送范围只能是 all 或 dept'];
        }

        $deptIds = self::intList($body['dept_ids'] ?? null, 'dept_ids', $fields);
        if ($scopeEnum === AnnouncementScope::Dept && $deptIds === []) {
            $fields['dept_ids'] = ['指定部门范围时请至少选择一个部门'];
        }

        $level = self::string($body['level'] ?? null);
        $levelEnum = $level === null ? AnnouncementLevel::Normal : AnnouncementLevel::tryFrom($level);
        if ($levelEnum === null) {
            $fields['level'] = ['重要程度不正确'];
            $levelEnum = AnnouncementLevel::Normal;
        }

        $pinned = self::bool($body['pinned'] ?? null);
        $imageFileIds = self::intList($body['image_file_ids'] ?? null, 'image_file_ids', $fields);
        $expireTime = self::dateTime($body['expire_time'] ?? null, $fields);

        if ($fields !== []) {
            throw AnnouncementException::invalid($fields);
        }

        return new self(
            $title ?? '',
            ContentSanitizer::clean($content ?? ''),
            $scopeEnum ?? AnnouncementScope::All,
            $scopeEnum === AnnouncementScope::Dept ? $deptIds : [],
            $levelEnum,
            $pinned ?? false,
            $imageFileIds,
            $expireTime,
        );
    }

    private static function string(mixed $value): ?string
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }

        return trim((string) $value);
    }

    /**
     * @param array<string, list<string>> $fields
     *
     * @return list<int>
     */
    private static function intList(mixed $value, string $field, array &$fields): array
    {
        if ($value === null) {
            return [];
        }
        if (!is_array($value)) {
            $fields[$field] = ['编号清单必须是数组'];

            return [];
        }
        $result = [];
        foreach ($value as $item) {
            if (is_int($item)) {
                $result[] = $item;
            } elseif (is_string($item) && preg_match('/^\d{1,19}$/', $item) === 1) {
                $result[] = (int) $item;
            } else {
                $fields[$field] = ['编号必须是整数'];

                return [];
            }
        }

        return array_values(array_unique($result));
    }

    private static function bool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === null) {
            return null;
        }
        if ($value === 1 || $value === '1' || $value === 'true') {
            return true;
        }
        if ($value === 0 || $value === '0' || $value === 'false') {
            return false;
        }

        return null;
    }

    /** @param array<string, list<string>> $fields */
    private static function dateTime(mixed $value, array &$fields): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            $fields['expire_time'] = ['有效期格式不正确'];

            return null;
        }
        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            $fields['expire_time'] = ['有效期格式不正确'];

            return null;
        }
    }
}
