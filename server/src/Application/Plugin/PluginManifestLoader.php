<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Application\Plugin;

use Sandadmin\Domain\Plugin\InvalidExtensionException;
use Sandadmin\Domain\Plugin\PluginLocaleSpec;
use Sandadmin\Domain\Plugin\PluginManifest;
use Sandadmin\Domain\Plugin\PluginMenuEntry;

/**
 * 从 <插件根>/<code>/manifest.json 读取宿主消费的声明段（menus、locales）。
 * 清单缺失、JSON 非法或字段不合法都按「无声明」处理并记 warning，
 * 单个插件的清单问题不能拖垮菜单等消费方。
 * 进程内按 code 缓存结果，manifest 是不可变文件内容，可安全跨请求复用。
 */
final class PluginManifestLoader
{
    private const CODE_PATTERN = '/^[a-z0-9-]+$/';
    private const MENU_KEY_PATTERN = '/^[a-z0-9-]+$/';
    private const LOCALE_DIR_PATTERN = '#^[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*$#';

    /** @var array<string, PluginManifest> */
    private array $cache = [];

    /** @param ?\Closure(string): void $warn */
    public function __construct(
        private readonly string $pluginDir,
        private readonly ?\Closure $warn = null,
    ) {
    }

    /** 宿主默认装载器：插件根取 app.plugin_dir，warning 落到运行日志。 */
    public static function atDefault(): self
    {
        $dir = 'plugin';
        if (function_exists('config')) {
            $configured = config('app.plugin_dir');
            if (is_string($configured) && $configured !== '') {
                $dir = $configured;
            }
        }

        return new self($dir, self::defaultWarn());
    }

    public function load(string $code): PluginManifest
    {
        return $this->cache[$code] ??= $this->read($code);
    }

    private function read(string $code): PluginManifest
    {
        if (preg_match(self::CODE_PATTERN, $code) !== 1) {
            return PluginManifest::empty($code);
        }
        $file = rtrim($this->pluginDir, '/') . '/' . $code . '/manifest.json';
        if (!is_file($file)) {
            $this->notice($code, 'manifest.json 缺失，按无声明处理');
            return PluginManifest::empty($code);
        }
        $raw = file_get_contents($file);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            $this->notice($code, 'manifest.json 不是合法 JSON');
            return PluginManifest::empty($code);
        }
        try {
            return new PluginManifest(
                $code,
                self::menus($decoded['menus'] ?? null, $code),
                self::locales($decoded['locales'] ?? null),
            );
        } catch (InvalidExtensionException $error) {
            $this->notice($code, 'manifest.json 声明不合法：' . $error->getMessage());
            return PluginManifest::empty($code);
        }
    }

    /**
     * @return list<PluginMenuEntry>
     */
    private static function menus(mixed $raw, string $code): array
    {
        if ($raw === null) {
            return [];
        }
        if (!is_array($raw) || !array_is_list($raw)) {
            throw new InvalidExtensionException('menus 必须为列表', ['menus' => 'list']);
        }
        $entries = [];
        foreach ($raw as $index => $item) {
            $entries[] = self::menuEntry($item, 'menus.' . $index, $code, false);
        }

        return $entries;
    }

    private static function menuEntry(mixed $item, string $path, string $code, bool $nested): PluginMenuEntry
    {
        $object = self::object($item, $path);
        self::only($object, ['key', 'path', 'title', 'title_key', 'icon', 'permission', 'sort', 'children'], $path);

        $key = self::requireString($object, 'key', $path);
        if (preg_match(self::MENU_KEY_PATTERN, $key) !== 1) {
            throw new InvalidExtensionException('菜单 key 只允许小写字母、数字、连字符', [$path . '.key' => 'pattern']);
        }
        $menuPath = self::requireString($object, 'path', $path);
        $prefix = '/plugin/' . $code;
        if ($menuPath !== $prefix && !str_starts_with($menuPath, $prefix . '/')) {
            throw new InvalidExtensionException('菜单 path 必须以 ' . $prefix . ' 开头', [$path . '.path' => 'prefix']);
        }
        $title = self::requireString($object, 'title', $path);
        if (trim($title) === '') {
            throw new InvalidExtensionException('菜单 title 不能为空', [$path . '.title' => 'required']);
        }
        $sort = 0;
        if (array_key_exists('sort', $object)) {
            if (!is_int($object['sort'])) {
                throw new InvalidExtensionException('sort 必须为整数', [$path . '.sort' => 'int']);
            }
            $sort = $object['sort'];
        }
        $children = [];
        if (array_key_exists('children', $object)) {
            if ($nested) {
                throw new InvalidExtensionException('菜单 children 最多一层', [$path . '.children' => 'depth']);
            }
            $raw = $object['children'];
            if (!is_array($raw) || !array_is_list($raw)) {
                throw new InvalidExtensionException('children 必须为列表', [$path . '.children' => 'list']);
            }
            foreach ($raw as $index => $child) {
                $children[] = self::menuEntry($child, $path . '.children.' . $index, $code, true);
            }
        }

        return new PluginMenuEntry(
            $key,
            $menuPath,
            $title,
            self::optionalString($object, 'title_key', $path),
            self::optionalString($object, 'icon', $path),
            self::optionalString($object, 'permission', $path),
            $sort,
            $children,
        );
    }

    private static function locales(mixed $raw): ?PluginLocaleSpec
    {
        if ($raw === null) {
            return null;
        }
        $object = self::object($raw, 'locales');
        self::only($object, ['backend', 'frontend'], 'locales');
        $backend = self::optionalString($object, 'backend', 'locales');
        $frontend = self::optionalString($object, 'frontend', 'locales');
        foreach (['backend' => $backend, 'frontend' => $frontend] as $field => $dir) {
            if ($dir !== null && preg_match(self::LOCALE_DIR_PATTERN, $dir) !== 1) {
                throw new InvalidExtensionException('locales 目录必须为相对路径', ['locales.' . $field => 'path']);
            }
        }

        return new PluginLocaleSpec($backend, $frontend);
    }

    /**
     * @return array<string, mixed>
     */
    private static function object(mixed $item, string $path): array
    {
        if (!is_array($item)) {
            throw new InvalidExtensionException('清单项必须为对象', [$path => 'object']);
        }
        $out = [];
        foreach ($item as $key => $value) {
            if (!is_string($key)) {
                throw new InvalidExtensionException('清单项必须为对象', [$path => 'object']);
            }
            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $item
     * @param list<string> $allowed
     */
    private static function only(array $item, array $allowed, string $path): void
    {
        $known = [];
        foreach ($allowed as $key) {
            $known[$key] = true;
        }
        foreach ($item as $key => $_) {
            if (!isset($known[$key])) {
                throw new InvalidExtensionException('清单含未知字段', [$path . '.' . $key => 'unknown']);
            }
        }
    }

    /** @param array<string, mixed> $item */
    private static function requireString(array $item, string $field, string $path): string
    {
        if (!array_key_exists($field, $item) || !is_string($item[$field])) {
            throw new InvalidExtensionException('清单字段必须为字符串', [$path . '.' . $field => 'string']);
        }

        return $item[$field];
    }

    /** @param array<string, mixed> $item */
    private static function optionalString(array $item, string $field, string $path): ?string
    {
        if (!array_key_exists($field, $item) || $item[$field] === null) {
            return null;
        }
        if (!is_string($item[$field])) {
            throw new InvalidExtensionException('清单字段必须为字符串', [$path . '.' . $field => 'string']);
        }

        return $item[$field];
    }

    private function notice(string $code, string $reason): void
    {
        ($this->warn ?? self::defaultWarn())('插件 ' . $code . ' ' . $reason);
    }

    /** @return \Closure(string): void */
    private static function defaultWarn(): \Closure
    {
        return static function (string $message): void {
            try {
                $channels = function_exists('config') ? config('log') : null;
                if (is_array($channels) && ($channels['default'] ?? null) !== null) {
                    \support\Log::channel('default')->warning($message);
                    return;
                }
            } catch (\Throwable) {
            }
            error_log($message);
        };
    }
}
