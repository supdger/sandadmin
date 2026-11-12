<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Setting;

use Doctrine\DBAL\Connection;
use Sandadmin\Infra\File\LocalFileStorage;
use Sandadmin\Module\Org\OrgActor;
use Sandadmin\Module\Org\OrgAudit;
use Sandadmin\Module\Org\OrgException;
use Sandadmin\Module\Org\OrgSql;

/**
 * 系统设置：sand_system_setting 键值（jsonb），分组与各键的校验、
 * 选项、默认值按 system-settings.md 5.3 写死；插件扩展分类暂不接
 * （签名清单 SettingsCategory 生效后再并进来）。
 * 敏感类型 type=password 只回 has_value 不回明文。
 */
final class SettingService
{
    /** 标志大小上限 1MB（规格 5.3）。 */
    private const LOGO_MAX_BYTES = 1048576;
    private const LOGO_MEDIA = ['png' => 'image/png', 'svg' => 'image/svg+xml', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg'];

    private const GROUPS = [
        'basic' => '基本信息',
        'login' => '登录与安全',
        'users' => '用户',
        'watermark' => '水印',
    ];

    /**
     * 每键定义：label/type/default/options(值=>显示名)/max/required/remark。
     * options 为 null 表示该键不是下拉；default 为缺省回显值。
     *
     * @var array<string, array<string, array<string, mixed>>>
     */
    private const ITEMS = [
        'basic' => [
            'site_name' => ['label' => '系统名称', 'type' => 'text', 'default' => 'SandAdmin', 'max' => 20, 'required' => true, 'remark' => '显示在登录页、左上角和浏览器标签上'],
            'site_logo' => ['label' => '标志', 'type' => 'image', 'default' => null, 'remark' => '建议用透明底的图片，深色模式下也看得清'],
            'timezone' => ['label' => '时区', 'type' => 'select', 'default' => 'Asia/Shanghai', 'options' => [
                'Asia/Shanghai' => '北京时间（东八区）',
                'Asia/Singapore' => '新加坡时间（东八区）',
                'Asia/Tokyo' => '东京时间（东九区）',
                'Europe/London' => '伦敦时间（零时区）',
                'Europe/Berlin' => '柏林时间（东一区）',
                'America/New_York' => '纽约时间（西五区）',
                'America/Los_Angeles' => '洛杉矶时间（西八区）',
                'UTC' => '世界协调时间',
            ], 'remark' => '工作台的问候语、“今天”和所有时间按这里显示'],
            'copyright' => ['label' => '版权信息', 'type' => 'text', 'default' => '© 2026 青岛沙盒创意网络科技有限公司', 'max' => 50, 'remark' => '显示在页面最下方'],
        ],
        'login' => [
            'session_days' => ['label' => '登录保持多久', 'type' => 'select', 'default' => 7, 'options' => [1 => '1 天', 7 => '7 天', 30 => '30 天'], 'remark' => '登录后最长保持这么久，到期要重新登录'],
            'idle_minutes' => ['label' => '多久不操作就要重新登录', 'type' => 'select', 'default' => 120, 'options' => [30 => '30 分钟', 60 => '1 小时', 120 => '2 小时', 240 => '4 小时'], 'remark' => '这么久没有任何操作，就要重新登录'],
            'captcha_after' => ['label' => '输错几次后要安全验证', 'type' => 'select', 'default' => 3, 'options' => [3 => '3 次', 5 => '5 次'], 'remark' => '防止别人猜密码'],
            'lock_minutes' => ['label' => '输错太多锁定多久', 'type' => 'select', 'default' => 15, 'options' => [15 => '15 分钟', 30 => '30 分钟', 60 => '1 小时'], 'remark' => '锁定期间这个账号不能登录'],
            'lock_after' => ['label' => '输错几次锁定', 'type' => 'select', 'default' => 10, 'options' => [5 => '5 次', 10 => '10 次', 20 => '20 次'], 'remark' => '连续输错这么多次，账号暂时锁定'],
        ],
        'users' => [
            'default_role_id' => ['label' => '新用户默认角色', 'type' => 'select', 'default' => null, 'options_dynamic' => 'role', 'remark' => '添加用户时自动选上；删除角色时，里面的人也会改成这个角色'],
        ],
        'watermark' => [
            'watermark_enabled' => ['label' => '在含个人信息的页面显示水印', 'type' => 'boolean', 'default' => true, 'remark' => '用户、日志等页面会淡淡地显示看的人的姓名，防止截图外传'],
        ],
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly OrgAudit $audit,
    ) {
    }

    /** @return list<array{key: string, title: string, owner: string, plugin_code: null}> */
    public function groups(): array
    {
        $out = [];
        foreach (self::GROUPS as $key => $title) {
            $out[] = ['key' => $key, 'title' => $title, 'owner' => 'host', 'plugin_code' => null];
        }

        return $out;
    }

    /** @return array{group: string, items: list<array<string, mixed>>} */
    public function read(string $group): array
    {
        $this->assertGroup($group);
        $stored = $this->values($group);

        return ['group' => $group, 'items' => $this->items($group, $stored)];
    }

    /** 设置项键 → 中文显示名（审计 change_labels 用）；不是设置项键返回 null。 */
    public static function fieldLabel(string $key): ?string
    {
        foreach (self::ITEMS as $defs) {
            if (isset($defs[$key])) {
                return $defs[$key]['label'];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array{group: string, items: list<array<string, mixed>>}
     */
    public function update(string $group, array $values, OrgActor $actor, string $requestId, ?string $ip, ?string $device): array
    {
        $this->assertGroup($group);
        $defs = self::ITEMS[$group];
        $fields = [];
        $unknown = [];
        $normalized = [];
        foreach ($values as $key => $value) {
            if (!isset($defs[$key])) {
                $unknown[$key] = ['这个设置项不存在'];

                continue;
            }
            $normalized[$key] = $this->normalize($group, $key, $value, $fields);
        }
        if ($unknown !== []) {
            throw OrgException::failed(422, 'SETTING_KEY_UNKNOWN', '有的设置项不存在', $unknown);
        }
        if ($fields !== []) {
            throw OrgException::invalid($fields);
        }
        $stored = $this->values($group);
        $changes = [];
        foreach ($normalized as $key => $value) {
            $def = $defs[$key] ?? null;
            $before = $stored[$key] ?? ($def === null ? null : ($def['default'] ?? null));
            if ($before === $value) {
                continue;
            }
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);
            $this->connection->executeStatement(
                'INSERT INTO sand_system_setting (key, value, group_code) VALUES (:key, CAST(:value AS jsonb), :group)'
                . ' ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value, group_code = EXCLUDED.group_code, update_time = CURRENT_TIMESTAMP',
                ['key' => $key, 'value' => is_string($encoded) ? $encoded : 'null', 'group' => $group],
            );
            $changes[$key] = ['before' => $this->masked($def ?? [], $before), 'after' => $this->masked($def ?? [], $value)];
        }
        $title = self::GROUPS[$group];
        $this->audit->record($actor, 'setting', 'update', '更新了系统设置「' . $title . '」', 'setting', $group, $title, $requestId, $ip, $device, $changes === [] ? null : $changes);
        $stored = $this->values($group);

        return ['group' => $group, 'items' => $this->items($group, $stored)];
    }

    /**
     * 上传系统标志：校验 png/svg/jpg 且 ≤1MB，写 purpose=attachment 的公开文件，
     * 返回契约 FileObject 形状；调用方随后把 file_id 写进 site_logo。
     *
     * @param array<string, mixed> $file 上传文件数组（name/tmp_name/size/type/error）
     *
     * @return array<string, mixed>
     */
    public function uploadLogo(array $file, OrgActor $actor, string $requestId, ?string $ip, ?string $device): array
    {
        $path = is_string($file['tmp_name'] ?? null) ? $file['tmp_name'] : '';
        $size = OrgSql::int($file['size'] ?? null);
        if ($path === '' || !is_file($path) || $size <= 0) {
            throw OrgException::invalid(['file' => ['请选一张图片']], '请选一张图片');
        }
        if ($size > self::LOGO_MAX_BYTES) {
            throw OrgException::invalid(['file' => ['图片太大了，请换一张 1MB 以内的']], '图片太大了，请换一张 1MB 以内的');
        }
        $name = is_string($file['name'] ?? null) && $file['name'] !== '' ? $file['name'] : 'logo';
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $media = self::LOGO_MEDIA[$ext] ?? null;
        if ($media === null) {
            throw OrgException::invalid(['file' => ['只支持 png、svg、jpg 图片']], '只支持 png、svg、jpg 图片');
        }
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw OrgException::invalid(['file' => ['上传内容读不出来']]);
        }
        $storage = LocalFileStorage::open($this->fileRoot());
        $key = $storage->writeStream($stream);
        fclose($stream);
        $content = file_get_contents($path);
        $sha = is_string($content) ? hash('sha256', $content) : hash('sha256', $name . $size);
        $uploader = $actor->writerId();
        $id = OrgSql::int($this->connection->fetchOne(
            'INSERT INTO sand_system_file (original_name, kind, media_type, extension, size_bytes, sha256, storage_key, visibility, purpose, usage_tracked, uploader_id)'
            . ' VALUES (:name, \'image\', :media, :ext, :size, :sha, :storage, \'public\', \'attachment\', false, :uploader)'
            . ' RETURNING id',
            ['name' => mb_substr($name, 0, 255), 'media' => $media, 'ext' => $ext, 'size' => $size, 'sha' => $sha, 'storage' => $key, 'uploader' => $uploader],
        ));
        $this->audit->record($actor, 'setting', 'logo', '上传了系统标志', 'file', (string) $id, $name, $requestId, $ip, $device);
        $createTime = OrgSql::str($this->connection->fetchOne('SELECT create_time FROM sand_system_file WHERE id = ?', [$id]));

        return [
            'id' => $id,
            'original_name' => mb_substr($name, 0, 255),
            'kind' => 'image',
            'media_type' => $media,
            'size_bytes' => $size,
            'usage_state' => 'unknown',
            'usage_label' => '系统标志',
            'visibility' => 'public',
            'purpose' => 'attachment',
            'uploader_id' => $uploader,
            'uploader_name' => $actor->name,
            'create_time' => $createTime !== '' ? (new \DateTimeImmutable($createTime))->format(\DateTimeInterface::ATOM) : null,
        ];
    }

    private function assertGroup(string $group): void
    {
        if (!isset(self::GROUPS[$group])) {
            throw OrgException::notFound();
        }
    }

    /** @return array<string, mixed> 该分组下各键的库存值 */
    private function values(string $group): array
    {
        $rows = $this->connection->fetchAllKeyValue(
            'SELECT key, value::text AS value FROM sand_system_setting WHERE group_code = ?',
            [$group],
        );
        $out = [];
        foreach ($rows as $key => $raw) {
            $decoded = json_decode(OrgSql::str($raw), true);
            $out[OrgSql::str($key)] = $decoded;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $stored
     *
     * @return list<array<string, mixed>>
     */
    private function items(string $group, array $stored): array
    {
        $out = [];
        foreach (self::ITEMS[$group] as $key => $def) {
            $value = array_key_exists($key, $stored) ? $stored[$key] : ($def['default'] ?? null);
            $item = [
                'key' => $key,
                'label' => $def['label'],
                'type' => $def['type'],
                'value' => $this->masked($def, $value),
                'has_value' => $value !== null && $value !== '',
                'default_value' => $this->masked($def, $def['default'] ?? null),
                'options' => $this->options($def),
                'remark' => $def['remark'],
            ];
            $out[] = $item;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $def
     *
     * @return list<array{label: string, value: mixed}>|null
     */
    private function options(array $def): ?array
    {
        if (($def['options_dynamic'] ?? null) === 'role') {
            // 角色是硬删表（无 delete_time）；默认角色只列启用中的
            $rows = $this->connection->fetchAllAssociative(
                'SELECT id, name FROM sand_system_role WHERE status = 1 ORDER BY id',
            );
            $out = [];
            foreach ($rows as $row) {
                $out[] = ['label' => OrgSql::str($row['name'] ?? ''), 'value' => OrgSql::int($row['id'] ?? null)];
            }

            return $out;
        }
        if (!isset($def['options']) || !is_array($def['options'])) {
            return null;
        }
        $out = [];
        foreach ($def['options'] as $value => $label) {
            $out[] = ['label' => OrgSql::str($label), 'value' => $value];
        }

        return $out;
    }

    /**
     * @param array<string, list<string>> $fields
     */
    private function normalize(string $group, string $key, mixed $value, array &$fields): mixed
    {
        $def = self::ITEMS[$group][$key];
        $type = $def['type'];
        if ($type === 'boolean') {
            if (!is_bool($value)) {
                $fields[$key][] = '只能选开或关';

                return null;
            }

            return $value;
        }
        if ($type === 'image') {
            if ($value === null) {
                return null;
            }
            if (!is_int($value) || $value <= 0 || $this->fileExists($value) === false) {
                $fields[$key][] = '图片不对，请重新上传';

                return null;
            }

            return $value;
        }
        if (($def['options_dynamic'] ?? null) === 'role') {
            if ($value === null) {
                return null;
            }
            if (!is_int($value) || $value <= 0 || !$this->roleExists($value)) {
                $fields[$key][] = '这个角色不存在';

                return null;
            }

            return $value;
        }
        if (isset($def['options'])) {
            // 键在 PHP 数组里是 int，JSON 来的值可能是 string，宽松归一
            $match = null;
            foreach (array_keys($def['options']) as $candidate) {
                if ($candidate === $value || OrgSql::str($candidate) === OrgSql::str($value)) {
                    $match = $candidate;
                    break;
                }
            }
            if ($match === null && $value !== null) {
                $fields[$key][] = '选项里没有这个值';

                return null;
            }
            if ($match === null) {
                $match = $def['default'];
            }

            return is_int(array_key_first($def['options'])) ? OrgSql::int($match) : $match;
        }
        if ($type === 'text') {
            if (!is_string($value)) {
                $fields[$key][] = '请填写文字';

                return null;
            }
            $max = (int) ($def['max'] ?? 255);
            if (($def['required'] ?? false) === true && trim($value) === '') {
                $fields[$key][] = '请填写' . $def['label'];

                return null;
            }
            if (mb_strlen($value) > $max) {
                $fields[$key][] = '最多 ' . $max . ' 个字';

                return null;
            }

            return $value;
        }

        return $value;
    }

    /**
     * 敏感类型回写库前遮罩：changes 里只留「已修改」。
     *
     * @param array<string, mixed> $def
     */
    private function masked(array $def, mixed $value): mixed
    {
        if (($def['type'] ?? '') === 'password' && $value !== null && $value !== '') {
            return '已修改';
        }

        return $value;
    }

    private function fileExists(int $fileId): bool
    {
        return $this->connection->fetchOne(
            'SELECT 1 FROM sand_system_file WHERE id = ? AND delete_time IS NULL',
            [$fileId],
        ) !== false;
    }

    private function roleExists(int $roleId): bool
    {
        // 角色是硬删表，不存在即删除；启用的才收（与 options 同口径）
        return $this->connection->fetchOne(
            'SELECT 1 FROM sand_system_role WHERE id = ? AND status = 1',
            [$roleId],
        ) !== false;
    }

    private function fileRoot(): string
    {
        if (function_exists('config')) {
            $configured = config('file.root');
            if (is_string($configured) && $configured !== '') {
                return $configured;
            }
        }
        $fromEnv = getenv('SAND_FILE_ROOT');
        if (is_string($fromEnv) && $fromEnv !== '') {
            return $fromEnv;
        }

        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sandadmin-files';
    }
}
