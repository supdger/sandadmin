<?php
// Legacy recovery regression only; normal lifecycle is covered by UpstreamPostgresLifecycleTest.php.

declare(strict_types=1);

namespace {
    $testRoot = sys_get_temp_dir() . '/sandpackage-upload-sanitize-v14-' . bin2hex(random_bytes(6));

    function runtime_path(): string { global $testRoot; return $testRoot . '/runtime'; }
    function base_path(): string { global $testRoot; return $testRoot . '/server'; }
    function env(string $key, mixed $default = null): mixed { return $default; }
    function config(string $key, mixed $default = null): mixed
    {
        return in_array($key, ['plugin.sandadmin.app.version', 'plugin.sandpackage.app.version'], true) ? '6.0.11' : $default;
    }
}

namespace Saithink\Saipackage\service {
    final class Server
    {
        /** @return array<string,mixed> */
        public static function getIni(string $directory): array
        {
            $file = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'info.ini';
            return is_file($file) ? (parse_ini_file($file, false, INI_SCANNER_TYPED) ?: []) : [];
        }

        /** @param array<string,mixed> $info */
        public static function setIni(string $directory, array $info): bool
        {
            $lines = [];
            foreach ($info as $key => $value) {
                $lines[] = $key . ' = ' . var_export($value, true);
            }
            return file_put_contents(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'info.ini', implode(PHP_EOL, $lines) . PHP_EOL) !== false;
        }

        /** @return list<array<string,mixed>> */
        public static function installedList(string $directory): array
        {
            $items = [];
            foreach (glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $path) {
                if (in_array(basename($path), ['backups', 'locks', 'uploads', 'quarantine'], true)) {
                    continue;
                }
                $info = self::getIni($path);
                if ($info !== []) {
                    $items[] = $info;
                }
            }
            return $items;
        }
    }

    final class Version { public static function compare(string $minimum, string $actual): bool { return version_compare($actual, $minimum, '>='); } }
    final class Filesystem
    {
        public static function dirIsEmpty(string $directory): bool { return !is_dir($directory) || count(scandir($directory) ?: []) <= 2; }
        public static function unzip(string $archive, string $target): void
        {
            $zip = new \ZipArchive();
            if ($zip->open($archive) !== true || !$zip->extractTo($target)) {
                throw new \RuntimeException('cannot extract upload fixture');
            }
            $zip->close();
        }
        public static function delDir(string $directory): void
        {
            if (!is_dir($directory)) { return; }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $item) { $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname()); }
            rmdir($directory);
        }
    }
    final class Depends {}
}

namespace plugin\sandadmin\exception { class ApiException extends \RuntimeException {} }
namespace plugin\sandadmin\app\cache { final class UserMenuCache { public static function clearMenuCache(): void {} } }
namespace plugin\sandadmin\app\middleware { final class SystemLog {} final class CheckLogin {} }
namespace plugin\sandadmin\basic {
    abstract class BaseController
    {
        protected int $adminId = 1;
        public function __construct() {}
        public function success(array $data): \support\Response { return new \support\Response($data); }
    }
}
namespace support {
    final class Request {}
    final class Response { /** @param array<string,mixed> $data */ public function __construct(public array $data) {} }
    final class Log { public static function error(string $message, array $context = []): void {} }
}
namespace support\annotation { #[\Attribute(\Attribute::TARGET_CLASS)] final class Middleware { public function __construct(string ...$middlewares) {} } }

namespace {
    use plugin\sandpackage\app\controller\InstallController;
    use plugin\sandpackage\app\logic\LegacyInstallLogic as InstallLogic;
    use Saithink\Saipackage\service\Server;

    require dirname(__DIR__) . '/server/plugin/sandpackage/app/logic/LegacyInstallLogic.php';
    require __DIR__ . '/fixtures/InstallController.php';

    function expect(bool $condition, string $message): void
    {
        if (!$condition) { throw new \RuntimeException($message); }
    }

    function removeTree(string $directory): void
    {
        if (!is_dir($directory)) { return; }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) { $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname()); }
        rmdir($directory);
    }

    function archive(string $path): void
    {
        $zip = new \ZipArchive();
        expect($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true, 'cannot create upload fixture');
        $zip->addFromString('info.ini', implode(PHP_EOL, [
            'app = sand-iam',
            'title = Fixture',
            'about = Fixture',
            'author = Fixture',
            'version = 0.7.0',
            'support = 6.x',
            'state = 8',
            'last_error_code = ATTACKER_CONTROLLED',
            'diagnostic_id = SP-ATTACKER-CONTROLLED',
            '',
        ]));
        expect($zip->close(), 'cannot close upload fixture');
    }

    /** @return array<string,mixed> */
    function controllerRow(): array
    {
        $response = (new InstallController())->index(new \support\Request());
        $rows = $response->data['data'] ?? null;
        expect(is_array($rows) && count($rows) === 1 && is_array($rows[0]), 'controller did not serialize uploaded registry row');
        return $rows[0];
    }

    foreach ([false => 'ready', true => 'registration_ready'] as $runtimeExists => $expectedStage) {
        global $testRoot;
        removeTree($testRoot);
        mkdir($testRoot, 0755, true);
        if ($runtimeExists) {
            $runtime = base_path() . '/plugin/sand-iam';
            mkdir($runtime, 0755, true);
        }
        $zip = $testRoot . '/malicious-info.zip';
        archive($zip);
        (new InstallLogic())->uploadFromPath($zip);
        $stored = (new InstallLogic('sand-iam'))->getInfo();
        expect(($stored['stage'] ?? null) === $expectedStage, $expectedStage . ': actual upload did not reach expected stage');
        expect(!array_key_exists('last_error_code', $stored) && !array_key_exists('diagnostic_id', $stored), $expectedStage . ': uploaded registry retained attacker diagnostic fields');
        expect(($stored['last_error'] ?? null) === '', $expectedStage . ': uploaded registry did not reset error text');
        $row = controllerRow();
        expect(!array_key_exists('last_error_code', $row) && !array_key_exists('diagnostic_id', $row), $expectedStage . ': controller leaked attacker diagnostic fields');
        expect(($row['last_error'] ?? null) === '', $expectedStage . ': controller exposed stale uploaded error text');
    }

    global $testRoot;
    removeTree($testRoot);
    echo "SandPackage v14 upload diagnostic sanitization contract passed\n";
}
