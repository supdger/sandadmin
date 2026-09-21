<?php

declare(strict_types=1);

namespace SandAdmin;

use RuntimeException;

final class Install
{
    public const WEBMAN_PLUGIN = true;

    /**
     * Only these package-owned plugin trees are installed into the consumer.
     * Runtime state remains under storage/ and is never part of this mapping.
     *
     * @var array<string, string>
     */
    private const PATH_RELATION = [
        'plugin/sandadmin' => 'plugin/sandadmin',
        'plugin/sandpackage' => 'plugin/sandpackage',
    ];

    public static function install(bool $isInstall = true): void
    {
        self::installPayload();
        self::installConfigTemplates();
        self::ensureFileCacheDriver();
        self::ensureBootstrap();
        self::ensureDefaultRoutesAreDisabled();
        self::ensureConfigurablePorts();
        self::ensureRequestExtension();
    }

    public static function update(): void
    {
        self::install(false);
    }

    public static function uninstall(): void
    {
        self::restoreFileCacheDriver();
        self::restoreConfigurablePorts();
        foreach (self::PATH_RELATION as $destination) {
            $path = base_path($destination);
            if (!is_dir($path) && !is_file($path) && !is_link($path)) {
                continue;
            }
            remove_dir($path);
            echo "Remove {$destination}\r\n";
        }
    }

    private static function installPayload(): void
    {
        foreach (self::PATH_RELATION as $source => $destination) {
            $sourcePath = __DIR__ . '/' . $source;
            if (!is_dir($sourcePath)) {
                throw new RuntimeException("SandAdmin package payload is missing: {$source}");
            }

            $destinationPath = base_path($destination);
            $parent = dirname($destinationPath);
            if (!is_dir($parent) && !mkdir($parent, 0777, true) && !is_dir($parent)) {
                throw new RuntimeException("Unable to create SandAdmin plugin directory: {$parent}");
            }

            copy_dir($sourcePath, $destinationPath, true);
            echo "Create {$destination}\r\n";
        }
    }

    private static function ensureRequestExtension(): void
    {
        $requestFile = base_path('support/Request.php');
        if (!is_file($requestFile)) {
            throw new RuntimeException('SandAdmin requires a standard Webman support/Request.php file.');
        }

        $content = file_get_contents($requestFile);
        if ($content === false) {
            throw new RuntimeException('Unable to read Webman support/Request.php.');
        }
        if (preg_match('/public\s+function\s+more\s*\(/', $content) === 1) {
            return;
        }

        $classEnd = strrpos($content, '}');
        if ($classEnd === false) {
            throw new RuntimeException('Unable to locate the Webman Request class boundary.');
        }

        $method = <<<'PHP'

    /**
     * Read multiple request values using SandAdmin's field/default contract.
     *
     * @param array<int, string|array{0: string|array{0: string, 1: string}, 1?: mixed}> $params
     * @return array<string, mixed>
     */
    public function more(array $params): array
    {
        $values = [];
        foreach ($params as $param) {
            if (!is_array($param)) {
                $values[$param] = $this->input($param);
                continue;
            }

            $default = $param[1] ?? '';
            if (is_array($param[0])) {
                $name = $param[0][0] . '/' . $param[0][1];
                $key = $param[0][0];
            } else {
                $name = $param[0];
                $key = $param[0];
            }
            $values[$key] = $this->input($name, $default);
        }
        return $values;
    }

PHP;

        $updated = substr_replace($content, $method, $classEnd, 0);
        if (file_put_contents($requestFile, $updated) === false) {
            throw new RuntimeException('Unable to update Webman support/Request.php.');
        }
        echo "Extend support/Request.php\r\n";
    }

    private static function installConfigTemplates(): void
    {
        foreach (['database.php', 'think-orm.php', 'think-cache.php'] as $file) {
            $destination = base_path('config/' . $file);
            if (is_file($destination)) {
                continue;
            }
            $source = __DIR__ . '/install/config/' . $file;
            if (!is_file($source)) {
                throw new RuntimeException("SandAdmin config template is missing: {$file}");
            }
            if (!is_dir(dirname($destination))
                && !mkdir(dirname($destination), 0777, true)
                && !is_dir(dirname($destination))) {
                throw new RuntimeException('Unable to create the Webman config directory.');
            }
            if (!copy($source, $destination)) {
                throw new RuntimeException("Unable to install SandAdmin config template: {$file}");
            }
            echo "Create config/{$file}\r\n";
        }
    }

    private static function ensureFileCacheDriver(): void
    {
        $file = base_path('config/think-cache.php');
        $content = self::readConsumerFile($file, 'Webman config/think-cache.php');
        if (str_contains($content, 'plugin\\sandadmin\\app\\cache\\driver\\File::class')) {
            return;
        }
        $updated = preg_replace(
            "/('file'\\s*=>\\s*\\[.*?'type'\\s*=>\\s*)'file'/s",
            '$1\\plugin\\sandadmin\\app\\cache\\driver\\File::class',
            $content,
            1,
            $count,
        );
        if (!is_string($updated) || $count !== 1 || file_put_contents($file, $updated) === false) {
            throw new RuntimeException('Unable to register the SandAdmin file-cache driver.');
        }
        echo "Extend config/think-cache.php\r\n";
    }

    private static function restoreFileCacheDriver(): void
    {
        $file = base_path('config/think-cache.php');
        if (!is_file($file)) {
            return;
        }
        $content = file_get_contents($file);
        if ($content === false
            || !str_contains($content, 'plugin\\sandadmin\\app\\cache\\driver\\File::class')) {
            return;
        }
        $updated = str_replace(
            '\\plugin\\sandadmin\\app\\cache\\driver\\File::class',
            "'file'",
            $content,
            $count,
        );
        if ($count !== 1 || file_put_contents($file, $updated) === false) {
            throw new RuntimeException('Unable to restore the consumer file-cache driver.');
        }
        echo "Restore config/think-cache.php\r\n";
    }

    private static function ensureConfigurablePorts(): void
    {
        self::replaceConsumerConfig(
            base_path('config/process.php'),
            "'http://0.0.0.0:8787'",
            "'http://0.0.0.0:' . env('SANDADMIN_SERVER_PORT', 8787)",
            'Webman HTTP port',
        );
        self::replaceConsumerConfig(
            base_path('config/plugin/webman/channel/process.php'),
            "'frame://0.0.0.0:2206'",
            "'frame://0.0.0.0:' . env('SANDADMIN_CHANNEL_PORT', 2206)",
            'Webman Channel port',
        );
    }

    private static function restoreConfigurablePorts(): void
    {
        self::replaceConsumerConfig(
            base_path('config/process.php'),
            "'http://0.0.0.0:' . env('SANDADMIN_SERVER_PORT', 8787)",
            "'http://0.0.0.0:8787'",
            'Webman HTTP port',
            false,
        );
        self::replaceConsumerConfig(
            base_path('config/plugin/webman/channel/process.php'),
            "'frame://0.0.0.0:' . env('SANDADMIN_CHANNEL_PORT', 2206)",
            "'frame://0.0.0.0:2206'",
            'Webman Channel port',
            false,
        );
    }

    private static function replaceConsumerConfig(
        string $file,
        string $from,
        string $to,
        string $label,
        bool $required = true,
    ): void {
        if (!is_file($file)) {
            if ($required) {
                throw new RuntimeException("SandAdmin requires {$label} config: {$file}");
            }
            return;
        }
        $content = file_get_contents($file);
        if ($content === false) {
            throw new RuntimeException("Unable to read {$label} config.");
        }
        if (str_contains($content, $to)) {
            return;
        }
        $updated = str_replace($from, $to, $content, $count);
        if ($count === 0 && !$required) {
            return;
        }
        if ($count !== 1 || file_put_contents($file, $updated) === false) {
            throw new RuntimeException("Unable to update {$label} config.");
        }
        echo "Update {$label}\r\n";
    }

    private static function ensureBootstrap(): void
    {
        $file = base_path('config/bootstrap.php');
        $content = self::readConsumerFile($file, 'Webman config/bootstrap.php');
        if (str_contains($content, 'Webman\\ThinkOrm\\ThinkOrm::class')) {
            return;
        }
        $updated = preg_replace(
            '/return\s*\[\s*/',
            "return [\n    \\Webman\\ThinkOrm\\ThinkOrm::class,\n",
            $content,
            1,
            $count,
        );
        if (!is_string($updated) || $count !== 1 || file_put_contents($file, $updated) === false) {
            throw new RuntimeException('Unable to register ThinkORM in Webman config/bootstrap.php.');
        }
        echo "Extend config/bootstrap.php\r\n";
    }

    private static function ensureDefaultRoutesAreDisabled(): void
    {
        $file = base_path('config/route.php');
        $content = self::readConsumerFile($file, 'Webman config/route.php');
        if (preg_match('/Route::disableDefaultRoute\s*\(\s*\)\s*;/', $content) === 1) {
            return;
        }
        if (!str_contains($content, 'use Webman\\Route;')) {
            $content = preg_replace('/<\?php\s*/', "<?php\n\nuse Webman\\Route;\n", $content, 1, $count);
            if (!is_string($content) || $count !== 1) {
                throw new RuntimeException('Unable to import Webman Route in config/route.php.');
            }
        }
        $updated = rtrim($content) . "\n\nRoute::disableDefaultRoute();\n";
        if (file_put_contents($file, $updated) === false) {
            throw new RuntimeException('Unable to disable Webman default routes.');
        }
        echo "Harden config/route.php\r\n";
    }

    private static function readConsumerFile(string $file, string $label): string
    {
        if (!is_file($file)) {
            throw new RuntimeException("SandAdmin requires a standard {$label} file.");
        }
        $content = file_get_contents($file);
        if ($content === false) {
            throw new RuntimeException("Unable to read {$label}.");
        }
        return $content;
    }
}
