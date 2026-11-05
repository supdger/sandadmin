<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Plugin;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Plugin\ExtensionCodec;
use Sandadmin\Domain\Plugin\ExtensionDraft;
use Sandadmin\Domain\Plugin\ExtensionManifest;
use Sandadmin\Domain\Plugin\ExtensionRules;
use Sandadmin\Infra\Plugin\ExtensionRegistry;
use Sandadmin\Infra\Plugin\PluginExtensionRepository;
use Sandadmin\Infra\Plugin\Schema\PluginExtensionSchema;
use Sandadmin\Infra\Plugin\StoredExtension;

final class RegistrySourceConstraintsTest extends TestCase
{
    public function testProductionCodeDoesNotScanDirectoriesOrKeepStaticState(): void
    {
        $roots = [
            dirname(__DIR__, 4) . '/src/Domain/Plugin',
            dirname(__DIR__, 4) . '/src/Infra/Plugin',
            dirname(__DIR__, 4) . '/migrations',
        ];
        $needles = [
            'scandir(',
            'glob(',
            'opendir(',
            'readdir(',
            'RecursiveDirectoryIterator',
            'DirectoryIterator',
            'FilesystemIterator',
            'GlobIterator',
            '$GLOBALS',
            '$_SESSION',
            '$_ENV',
            '$_SERVER',
            'getenv(',
            'putenv(',
            'file_get_contents(',
            'file_put_contents(',
            'include ',
            'include_once',
            'require ',
            'require_once',
            'EntityManager',
            'static $',
            'eval(',
            'extract(',
            'compact(',
            '__get',
            '__set',
            '__call',
        ];
        $files = [];
        foreach ($roots as $root) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                $files[] = $file->getPathname();
            }
        }
        $files[] = dirname(__DIR__, 4) . '/migrations.php';
        self::assertCount(21, $files);
        foreach ($files as $path) {
            $source = file_get_contents($path);
            if (!is_string($source)) {
                self::fail($path . ' 无法读取');
            }
            self::assertStringContainsString('declare(strict_types=1);', $source, $path);
            self::assertDoesNotMatchRegularExpression('/^class /m', $source, $path);
            foreach ($needles as $needle) {
                self::assertStringNotContainsString($needle, $source, $path . ' contains ' . $needle);
            }
        }
    }

    public function testRegistryTypesAreFinalAndHaveNoStaticProperties(): void
    {
        foreach ([
            ExtensionRules::class,
            ExtensionCodec::class,
            ExtensionManifest::class,
            ExtensionDraft::class,
            ExtensionRegistry::class,
            PluginExtensionRepository::class,
            PluginExtensionSchema::class,
            StoredExtension::class,
        ] as $class) {
            $ref = new \ReflectionClass($class);
            self::assertTrue($ref->isFinal(), $class);
            foreach ($ref->getProperties() as $property) {
                self::assertFalse($property->isStatic(), $class . '::$' . $property->getName());
                self::assertTrue($property->isReadOnly(), $class . '::$' . $property->getName());
            }
        }
    }
}
