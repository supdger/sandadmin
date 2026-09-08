<?php

declare(strict_types=1);

namespace plugin\sandpackage\app\logic;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use plugin\sandadmin\exception\ApiException;

/**
 * Builds the immutable identity of a failed-upgrade candidate.
 *
 * The component deliberately knows nothing about a concrete plugin. It reads
 * only the candidate-owned v2 descriptor and deterministic package files.
 */
final class FailedUpgradePackageIdentity
{
    /** @var list<string> */
    private const PAYLOAD_EXCLUSIONS = [
        'recovery/failed-upgrade.v2.json',
        'recovery/failed-upgrade.v2.json.sha256',
        'registration_manifest.json',
    ];

    public function readDescriptor(string $candidate): string
    {
        $file = rtrim($candidate, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'recovery'
            . DIRECTORY_SEPARATOR . 'failed-upgrade.v2.json';
        if (!$this->isSafeRegularFile($file)) {
            throw new ApiException('FAILED_UPGRADE_RECOVERY_BLOCKED：候选包缺少安全的恢复描述文件', 400);
        }
        $raw = file_get_contents($file);
        if (!is_string($raw) || $raw === '') {
            throw new ApiException('FAILED_UPGRADE_RECOVERY_BLOCKED：候选包恢复描述文件不可读取', 400);
        }
        (new FailedUpgradeRecoveryVerifier())->parseDescriptor($raw);
        return $raw;
    }

    public function fileSha256(string $file, string $label, ?int $exactMode = null): string
    {
        if (!$this->isSafeRegularFile($file, $exactMode)) {
            throw new ApiException($label . '不安全或不可读取');
        }
        $hash = hash_file('sha256', $file);
        if (!is_string($hash) || preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1) {
            throw new ApiException($label . '摘要计算失败');
        }
        return $hash;
    }

    /**
     * @param callable(string):string $normalizedInfo returns canonical info.ini
     * @return array<string,array{size:int,sha256:string}>
     */
    public function payloadManifest(string $directory, callable $normalizedInfo): array
    {
        $this->assertSafeDirectory($directory);
        $manifest = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iterator as $item) {
            if ($item->isLink() || !$item->isFile()) {
                throw new ApiException('候选包恢复载荷包含不安全文件');
            }
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', $iterator->getSubPathName());
            if (in_array($relative, self::PAYLOAD_EXCLUSIONS, true)
                || preg_match('/^registration_manifest\.json\.[a-f0-9]{16}\.tmp$/D', $relative) === 1) {
                continue;
            }
            if ($relative === 'info.ini') {
                $content = $normalizedInfo($directory);
                $manifest[$relative] = ['size' => strlen($content), 'sha256' => hash('sha256', $content)];
                continue;
            }
            $hash = hash_file('sha256', $item->getPathname());
            if (!is_string($hash)) {
                throw new ApiException('候选包恢复载荷摘要计算失败');
            }
            $manifest[$relative] = ['size' => $item->getSize(), 'sha256' => $hash];
        }
        if (!isset($manifest['info.ini'], $manifest['update.sql'])) {
            throw new ApiException('候选包恢复载荷不完整');
        }
        ksort($manifest, SORT_STRING);
        return $manifest;
    }

    /** @param array<string,mixed> $manifest */
    public function manifestDigest(array $manifest): string
    {
        return hash('sha256', json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function retainArchive(string $archive, string $expectedSha256, string $archiveDirectory): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $expectedSha256)
            || !hash_equals($expectedSha256, $this->fileSha256($archive, '候选安装包归档', 0400))) {
            throw new ApiException('候选安装包归档未通过预检');
        }
        $this->preparePrivateDirectory($archiveDirectory);
        $target = rtrim($archiveDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $expectedSha256 . '.zip';
        if (is_file($target)) {
            if (!hash_equals($expectedSha256, $this->fileSha256($target, '候选安装包归档', 0400))) {
                throw new ApiException('候选安装包归档冲突');
            }
            return;
        }
        if (!copy($archive, $target) || !chmod($target, 0400)) {
            @unlink($target);
            throw new ApiException('无法封存候选安装包归档');
        }
        if (!hash_equals($expectedSha256, $this->fileSha256($target, '候选安装包归档', 0400))) {
            @unlink($target);
            throw new ApiException('无法封存候选安装包归档');
        }
    }

    private function preparePrivateDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new ApiException('无法保存候选安装包归档');
        }
        if (is_link($directory) || !chmod($directory, 0700)) {
            throw new ApiException('无法保护候选安装包归档');
        }
        $this->assertSafeDirectory($directory);
    }

    private function assertSafeDirectory(string $directory): void
    {
        $stat = @lstat($directory);
        $expectedUid = function_exists('posix_geteuid') ? posix_geteuid() : null;
        if (!is_array($stat) || is_link($directory) || (($stat['mode'] & 0170000) !== 0040000)
            || (($stat['mode'] & 0002) !== 0) || ($expectedUid !== null && $stat['uid'] !== $expectedUid)) {
            throw new ApiException('候选包目录安全属性不符合要求');
        }
    }

    private function isSafeRegularFile(string $file, ?int $exactMode = null): bool
    {
        $stat = @lstat($file);
        $expectedUid = function_exists('posix_geteuid') ? posix_geteuid() : null;
        if (!is_array($stat) || is_link($file) || (($stat['mode'] & 0170000) !== 0100000)
            || (($stat['mode'] & 0002) !== 0) || ($expectedUid !== null && $stat['uid'] !== $expectedUid)
            || !is_readable($file)) {
            return false;
        }
        return $exactMode === null || (($stat['mode'] & 0777) === $exactMode);
    }
}
