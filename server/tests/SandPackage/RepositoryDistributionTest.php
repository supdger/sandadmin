<?php
declare(strict_types=1);

// Repository-to-installer filesystem integration. Network is a deterministic fixture;
// SQL uses a recording connection. No real database, HTTP service or restart.
namespace think\facade {
    final class Db
    {
        public static array $sql = [];
        public static bool $fail = false;
        public static function connect(string $name = ''): object
        {
            if ($name !== 'pgsql') throw new \RuntimeException('Unexpected database connection');
            return new class {
                public function connect(): object { return $this; }
                public function inTransaction(): bool { return false; }
                public function quote(string $value): string { return "'" . str_replace("'", "''", $value) . "'"; }
                public function query(string $sql): object {
                    return new class($sql) {
                        public function __construct(private string $sql) {}
                        public function fetchAll(int $mode): array {
                            return str_contains($this->sql, 'current_database()') ? [['database' => 'recording-fixture', 'oid' => '1', 'username' => 'fixture', 'address' => null, 'port' => null, 'started' => 'fixed']] : [];
                        }
                    };
                }
                public function exec(string $sql): int {
                    Db::$sql[] = trim($sql);
                    if (Db::$fail && str_contains($sql, 'BROKEN SQL')) throw new \RuntimeException('isolated simulated SQL error');
                    return 1;
                }
            };
        }
    }
}
namespace plugin\sandadmin\app\cache {
    final class UserMenuCache { public static function clearMenuCache(): void {} }
}
namespace {
    use plugin\sandpackage\app\logic\InstallLogic;
    use Saithink\Saipackage\service\Server;
    use think\facade\Db;

    $root = realpath(sys_get_temp_dir()) . '/sandpackage-repository-' . bin2hex(random_bytes(6));
    mkdir($root . '/server/plugin', 0755, true);
    mkdir($root . '/sandadmin-artd', 0755, true);
    ini_set('error_log', $root . '/expected-errors.log');
    function base_path($path = ''): string { global $root; return $root . '/server' . ($path ? '/' . $path : ''); }
    function runtime_path(string $path = ''): string { global $root; return $root . '/runtime' . ($path ? '/' . $path : ''); }
    function env(string $key, mixed $default = null): mixed { return $default; }
    require dirname(__DIR__, 2) . '/vendor/autoload.php';
    require dirname(__DIR__, 2) . '/plugin/sandpackage/app/logic/InstallLogic.php';
    require dirname(__DIR__, 2) . '/plugin/sandpackage/app/service/PostgresLifecycleSqlExecutor.php';
    require dirname(__DIR__, 2) . '/plugin/sandpackage/app/service/FreshInstallRecovery.php';

    function check(bool $condition, string $message): void {
        if (!$condition) throw new RuntimeException($message);
        echo "[PASS] $message\n";
    }
    function rejected(callable $operation, string $message): void {
        try { $operation(); } catch (\plugin\sandadmin\exception\ApiException) { echo "[PASS] $message\n"; return; }
        throw new RuntimeException($message);
    }
    function package(string $app, string $version, array $config = [], array $extra = []): string {
        global $root;
        $path = $root . '/' . bin2hex(random_bytes(6)) . '.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('info.ini', "app = $app\ntitle = Neutral\nabout = Fixture\nauthor = Test\nversion = $version\nstate = 0\n");
        $zip->addFromString('config.json', json_encode($config, JSON_THROW_ON_ERROR));
        $zip->addFromString('install.sql', "CREATE TABLE neutral_sample (id bigint);\n");
        $zip->addFromString('update.sql', "ALTER TABLE neutral_sample ADD COLUMN label text;\n");
        $zip->addFromString('uninstall.sql', "DROP TABLE neutral_sample;\n");
        $zip->addFromString("plugin/$app/config/app.php", "<?php return ['version' => '$version'];\n");
        $zip->addFromString("sandadmin-artd/src/views/plugin/$app/index.vue", '<template><div>Neutral ' . $version . '</div></template>');
        foreach ($extra as $name => $body) $zip->addFromString($name, $body);
        $zip->close();
        return $path;
    }

    require dirname(__DIR__, 2) . '/plugin/sandpackage/app/service/RepositoryClient.php';
    require dirname(__DIR__, 2) . '/plugin/sandpackage/app/logic/RepositoryLogic.php';

    final class FixtureRepositoryClient implements \plugin\sandpackage\app\service\RepositoryClient
    {
        public array $requests = [];
        public function __construct(public string $catalog, public string $zip, public ?Throwable $error = null) {}
        public function get(string $url, int $maxBytes, callable $complete): void
        {
            $this->requests[] = [$url, $maxBytes];
            $complete($this->error === null ? (str_contains($url, 'raw.githubusercontent.com') ? $this->catalog : $this->zip) : null, $this->error);
        }
    }
    function manifest(string $zip, string $version = '1.0.0', string $app = 'neutral-sample'): array {
        return ['schema' => 1, 'plugins' => [['app' => $app, 'title' => 'Neutral', 'about' => 'Fixture', 'author' => 'Test',
            'versions' => [['version' => $version, 'tag' => $app . '-v' . $version, 'asset' => $app . '-' . $version . '.zip',
                'sha256' => hash('sha256', $zip), 'host_min' => '6.0.0', 'host_max' => '6.9.9', 'notes' => 'Test release']]]]];
    }
    function downloadFixture(FixtureRepositoryClient $client, string $version = '1.0.0', ?string $sha = null, string $host = '6.0.11'): array {
        $logic = new \plugin\sandpackage\app\logic\RepositoryLogic($client, 'supdger/sandadmin', 'main', $host);
        $calls = 0;
        $result = null;
        $failure = null;
        $logic->download('neutral-sample', $version, $sha ?? hash('sha256', $client->zip), function (?array $data, ?Throwable $error) use (&$calls, &$result, &$failure): void {
            $calls++;
            $result = $data;
            $failure = $error;
        });
        check($calls === 1, 'download completes exactly once');
        if ($failure !== null) throw $failure;
        return $result;
    }
    function clientFor(string $zip, string $version = '1.0.0'): FixtureRepositoryClient {
        return new FixtureRepositoryClient(json_encode(manifest($zip, $version), JSON_THROW_ON_ERROR), $zip);
    }

    try {
        file_put_contents(base_path('composer.json'), '{"name":"test/host","require":{}}');
        file_put_contents($root . '/sandadmin-artd/package.json', '{"name":"test-host","dependencies":{}}');
        $zip = file_get_contents(package('neutral-sample', '1.0.0'));
        $client = clientFor($zip);
        $repository = new \plugin\sandpackage\app\logic\RepositoryLogic($client, 'supdger/sandadmin', 'main', '6.0.11');
        $repository->catalog(function (?array $result, ?Throwable $error): void {
            check($error === null && $result['repository'] === 'supdger/sandadmin' && $result['plugins'][0]['versions'][0]['version'] === '1.0.0', 'repository catalog exposes versioned package without store account');
        });
        $empty = \plugin\sandpackage\app\logic\RepositoryLogic::parseCatalog('{"schema":1,"plugins":[]}');
        check($empty['plugins'] === [], 'empty repository stays empty');
        foreach (['not json', '{"schema":2,"plugins":[]}', '{"schema":1,"plugins":{}}'] as $bad) {
            rejected(fn() => \plugin\sandpackage\app\logic\RepositoryLogic::parseCatalog($bad), 'invalid manifest is rejected');
        }
        $duplicate = manifest($zip);
        $duplicate['plugins'][] = $duplicate['plugins'][0];
        rejected(fn() => \plugin\sandpackage\app\logic\RepositoryLogic::parseCatalog(json_encode($duplicate)), 'duplicate plugin rejected');
        $malicious = manifest($zip);
        $malicious['plugins'][0]['versions'][0]['asset'] = '../evil.zip';
        rejected(fn() => \plugin\sandpackage\app\logic\RepositoryLogic::parseCatalog(json_encode($malicious)), 'asset path traversal rejected');
        rejected(fn() => new \plugin\sandpackage\app\logic\RepositoryLogic($client, '../evil', 'main', '6.0.11'), 'untrusted repository configuration rejected');
        rejected(fn() => downloadFixture(clientFor($zip), '1.0.0', null, '5.0.0'), 'incompatible host rejected before download');
        rejected(fn() => downloadFixture(clientFor($zip), '1.0.0', str_repeat('a', 64)), 'changed catalog digest rejected');
        $wrong = clientFor($zip); $wrong->zip = 'tampered';
        rejected(fn() => downloadFixture($wrong, '1.0.0', hash('sha256', $zip)), 'wrong ZIP checksum rejected');
        $identity = file_get_contents(package('other-sample', '1.0.0'));
        rejected(fn() => downloadFixture(clientFor($identity)), 'package app mismatch rejected');
        $wrongVersion = file_get_contents(package('neutral-sample', '2.0.0'));
        rejected(fn() => downloadFixture(clientFor($wrongVersion)), 'package version mismatch rejected');
        $legacy = file_get_contents(package('neutral-sample', '1.0.0', ['sand_platform' => ['required_plugins' => ['iam']]]));
        rejected(fn() => downloadFixture(clientFor($legacy)), 'old platform extension remains rejected');
        $traversal = file_get_contents(package('neutral-sample', '1.0.0', [], ['../escape' => 'no']));
        rejected(fn() => downloadFixture(clientFor($traversal)), 'existing ZIP path protections retained');
        check(!is_dir(runtime_path('sandpackage/neutral-sample')), 'rejected downloads do not create an installation candidate');
        check(Db::$sql === [], 'rejected downloads execute no SQL');

        $info = downloadFixture($client);
        check($info['state'] === 2 && !is_dir(base_path('plugin/neutral-sample')), 'valid download stages candidate without deployment');
        check(Db::$sql === [], 'successful download executes no SQL');
        check($client->requests[count($client->requests)-1][0] === 'https://github.com/supdger/sandadmin/releases/download/neutral-sample-v1.0.0/neutral-sample-1.0.0.zip', 'download URL derived only from configured repository and validated manifest');
        rejected(fn() => downloadFixture(clientFor($zip)), 'duplicate candidate cannot overwrite pending install');
        $installed = (new InstallLogic('neutral-sample'))->install(false);
        check($installed['state'] === 1 && is_file(base_path('plugin/neutral-sample/config/app.php')), 'downloaded candidate uses existing real file deployment');
        check(Db::$sql === ['BEGIN', 'CREATE TABLE neutral_sample (id bigint)', 'COMMIT'], 'existing install lifecycle receives only install SQL through recording connection');
        $upgrade = file_get_contents(package('neutral-sample', '1.1.0'));
        $info = downloadFixture(clientFor($upgrade, '1.1.0'), '1.1.0');
        check($info['update'] === 1 && $info['upgrade_from_version'] === '1.0.0', 'repository version becomes upgrade candidate');
        Db::$sql = [];
        rejected(fn() => (new InstallLogic('neutral-sample'))->install(false, 'incorrect'), 'repository upgrade still needs existing confirmation');
        check(Db::$sql === [], 'failed confirmation does not execute SQL');
        (new InstallLogic('neutral-sample'))->install(false, 'UPGRADE neutral-sample@1.0.0->1.1.0');
        check(Db::$sql === ['BEGIN', 'ALTER TABLE neutral_sample ADD COLUMN label text', 'COMMIT'], 'downloaded upgrade executes update SQL only');
        rejected(fn() => downloadFixture(clientFor($zip)), 'older repository package cannot downgrade installed plugin');
        (new InstallLogic('neutral-sample'))->setInfo(['state' => 8]);
        $next = file_get_contents(package('neutral-sample', '1.2.0'));
        rejected(fn() => downloadFixture(clientFor($next, '1.2.0'), '1.2.0'), 'recovery state blocks repository staging');
        $network = clientFor($zip); $network->error = new \plugin\sandadmin\exception\ApiException('仓库不可达');
        rejected(fn() => downloadFixture($network), 'network failure propagated without candidate writes');
        echo "Repository distribution fixture passed (no real database/network/restart).\n";
    } finally {
        \Saithink\Saipackage\service\Filesystem::delDir($root);
    }
}
