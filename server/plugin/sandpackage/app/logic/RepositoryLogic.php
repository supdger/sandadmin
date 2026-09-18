<?php

declare(strict_types=1);

namespace plugin\sandpackage\app\logic;

use plugin\sandadmin\exception\ApiException;
use plugin\sandpackage\app\service\RepositoryClient;
use Throwable;
use ZipArchive;

/** Public repository distribution; installation remains owned by InstallLogic. */
final class RepositoryLogic
{
    public function __construct(
        private RepositoryClient $client,
        private string $repository,
        private string $ref,
        private string $hostVersion,
    ) {
        if (!preg_match('~^[A-Za-z0-9][A-Za-z0-9_.-]*/[A-Za-z0-9][A-Za-z0-9_.-]*$~D', $repository)
            || !preg_match('~^[A-Za-z0-9][A-Za-z0-9._/-]{0,199}$~D', $ref)
            || str_contains($ref, '..') || str_contains($ref, '//')) {
            throw new ApiException('插件仓库配置无效');
        }
    }

    /** @param callable(?array, ?Throwable): void $complete */
    public function catalog(callable $complete): void
    {
        $ref = implode('/', array_map('rawurlencode', explode('/', $this->ref)));
        $url = 'https://raw.githubusercontent.com/' . $this->repository . '/' . $ref . '/plugins/catalog.json';
        $this->client->get($url, 1048576, function (?string $body, ?Throwable $error) use ($complete): void {
            if ($error !== null) { $complete(null, $error); return; }
            try {
                $catalog = self::parseCatalog($body ?? '');
                $result = ['repository' => $this->repository, 'ref' => $this->ref, 'plugins' => $catalog['plugins']];
            } catch (Throwable $error) { $complete(null, $error); return; }
            $complete($result, null);
        });
    }

    /** The browser supplies an identity, never a URL. @param callable(?array, ?Throwable): void $complete */
    public function download(string $app, string $version, string $sha256, callable $complete): void
    {
        $this->catalog(function (?array $catalog, ?Throwable $error) use ($app, $version, $sha256, $complete): void {
            if ($error !== null) { $complete(null, $error); return; }
            try {
                $release = null;
                foreach ($catalog['plugins'] as $plugin) {
                    if ($plugin['app'] !== $app) continue;
                    foreach ($plugin['versions'] as $candidate) {
                        if ($candidate['version'] === $version) $release = $candidate;
                    }
                }
                if ($release === null) throw new ApiException('仓库中不存在此插件版本，请刷新插件清单');
                if (!hash_equals($release['sha256'], $sha256)) throw new ApiException('插件清单已变化，请刷新后重新选择版本');
                if (!version_compare($this->hostVersion, $release['host_min'], '>=')
                    || (isset($release['host_max']) && !version_compare($this->hostVersion, $release['host_max'], '<='))) {
                    throw new ApiException('该插件版本与当前 SandAdmin 版本不兼容');
                }
                $url = 'https://github.com/' . $this->repository . '/releases/download/'
                    . rawurlencode($release['tag']) . '/' . rawurlencode($release['asset']);
            } catch (Throwable $error) { $complete(null, $error); return; }
            $this->client->get($url, 5242880, function (?string $body, ?Throwable $error) use ($app, $version, $sha256, $complete): void {
                if ($error !== null) { $complete(null, $error); return; }
                try { $result = $this->stage($body ?? '', $app, $version, $sha256); }
                catch (Throwable $error) { $complete(null, $error); return; }
                $complete($result, null);
            });
        });
    }

    private function stage(string $body, string $app, string $version, string $sha256): array
    {
        if (strlen($body) > 5242880 || !hash_equals($sha256, hash('sha256', $body))) {
            throw new ApiException('插件包校验失败，未准备安装');
        }
        $file = tempnam(sys_get_temp_dir(), 'sandpackage-download-');
        if ($file === false) throw new ApiException('无法创建插件下载临时文件');
        try {
            if (file_put_contents($file, $body) !== strlen($body)) throw new ApiException('插件包保存失败');
            $zip = new ZipArchive();
            if ($zip->open($file) !== true) throw new ApiException('下载文件不是有效的 ZIP 插件包');
            try {
                $stat = $zip->statName('info.ini');
                if (!$stat || $stat['size'] > 16384) throw new ApiException('插件包缺少有效的 info.ini');
                $raw = $zip->getFromName('info.ini');
                $info = is_string($raw) ? @parse_ini_string($raw, true, INI_SCANNER_TYPED) : false;
                if (!is_array($info) || ($info['app'] ?? null) !== $app || ($info['version'] ?? null) !== $version) {
                    throw new ApiException('插件包名称或版本与所选仓库版本不一致');
                }
            } finally { $zip->close(); }
            return (new InstallLogic())->uploadFromPath($file);
        } finally { if (is_file($file)) unlink($file); }
    }

    /** Validate the entire manifest before displaying or trusting any release. */
    public static function parseCatalog(string $json): array
    {
        if (strlen($json) > 1048576) throw new ApiException('插件清单超过大小限制');
        try {
            $shape = json_decode($json, false, 32, JSON_THROW_ON_ERROR);
            $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        }
        catch (Throwable) { throw new ApiException('插件清单不是有效的 JSON'); }
        if (!is_object($shape) || !is_array($shape->plugins ?? null) || !is_array($data) || ($data['schema'] ?? null) !== 1 || !is_array($data['plugins'] ?? null)
            || !array_is_list($data['plugins']) || count($data['plugins']) > 200) {
            throw new ApiException('插件清单格式不受支持');
        }
        $apps = [];
        $plugins = [];
        foreach ($data['plugins'] as $plugin) {
            if (!is_array($plugin)) throw new ApiException('插件清单条目无效');
            $app = self::text($plugin, 'app', 64);
            if (!preg_match('/^[a-z][a-z0-9-]{1,63}$/D', $app)
                || in_array($app, ['sandadmin', 'sandpackage', 'saiadmin', 'saipackage', 'locks', 'backups', 'fresh-recovery'], true)
                || isset($apps[$app])) throw new ApiException('插件清单包含无效或重复的插件标识');
            $apps[$app] = true;
            $item = ['app' => $app, 'title' => self::text($plugin, 'title', 200),
                'about' => self::text($plugin, 'about', 4000), 'author' => self::text($plugin, 'author', 200), 'versions' => []];
            if (!is_array($plugin['versions'] ?? null) || !array_is_list($plugin['versions']) || count($plugin['versions']) > 50) {
                throw new ApiException('插件版本清单无效');
            }
            $versions = [];
            foreach ($plugin['versions'] as $release) {
                if (!is_array($release)) throw new ApiException('插件版本条目无效');
                $version = self::version($release, 'version');
                if (isset($versions[$version])) throw new ApiException('插件版本重复');
                $versions[$version] = true;
                $tag = self::text($release, 'tag', 160);
                $asset = self::text($release, 'asset', 200);
                $sha = self::text($release, 'sha256', 64);
                if (!preg_match('~^[A-Za-z0-9][A-Za-z0-9._/-]*$~D', $tag) || str_contains($tag, '..')
                    || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*\.zip$/D', $asset)
                    || !preg_match('/^[a-f0-9]{64}$/D', $sha)) throw new ApiException('插件发布附件信息无效');
                $entry = ['version' => $version, 'tag' => $tag, 'asset' => $asset, 'sha256' => $sha,
                    'host_min' => self::version($release, 'host_min'), 'notes' => self::text($release, 'notes', 8000, true)];
                if (isset($release['host_max'])) {
                    $entry['host_max'] = self::version($release, 'host_max');
                    if (version_compare($entry['host_max'], $entry['host_min'], '<')) throw new ApiException('插件宿主兼容范围无效');
                }
                $item['versions'][] = $entry;
            }
            $plugins[] = $item;
        }
        return ['schema' => 1, 'plugins' => $plugins];
    }

    private static function text(array $data, string $key, int $max, bool $optional = false): string
    {
        $value = $data[$key] ?? ($optional ? '' : null);
        if (!is_string($value) || strlen($value) > $max || (!$optional && trim($value) === '') || str_contains($value, "\0")) {
            throw new ApiException('插件清单字段无效：' . $key);
        }
        return $value;
    }

    private static function version(array $data, string $key): string
    {
        $value = self::text($data, $key, 80);
        if (!preg_match('/^\d+\.\d+\.\d+(?:-[A-Za-z0-9.-]+)?$/D', $value)) throw new ApiException('插件清单版本号无效');
        return $value;
    }
}
