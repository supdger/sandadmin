<?php

declare(strict_types=1);

// Public controller + real HTTP response encoding; deterministic timer/transport,
// no listener, authentication store, database or external network.
namespace Workerman {
    final class Timer {
        public static array $tasks = [];
        public static function add(float $interval, callable $callback, array $args = [], bool $persistent = true): int {
            self::$tasks[] = fn() => $callback(...$args);
            return count(self::$tasks);
        }
    }
}
namespace {
    require dirname(__DIR__, 2) . '/vendor/autoload.php';
    require dirname(__DIR__, 2) . '/plugin/sandpackage/app/service/RepositoryClient.php';
    require dirname(__DIR__, 2) . '/plugin/sandpackage/app/logic/RepositoryLogic.php';
    require dirname(__DIR__, 2) . '/plugin/sandpackage/app/controller/InstallController.php';
    function config(string $key, mixed $default = null): mixed {
        return ['plugin.sandadmin.app.version' => '6.0.11'][$key] ?? $default;
    }
    function json(mixed $data, int $options = 0): \support\Response {
        return new \support\Response(200, ['Content-Type' => 'application/json'], json_encode($data, $options | JSON_THROW_ON_ERROR));
    }
}
namespace plugin\sandpackage\app\service {
    final class GithubRepositoryClient implements RepositoryClient {
        public static bool $fail = false;
        public static int $calls = 0;
        public static function shared(): self { return new self(); }
        public function get(string $url, int $maxBytes, callable $complete): void {
            self::$calls++;
            $complete(self::$fail ? null : '{"schema":1,"plugins":[]}', self::$fail ? new \plugin\sandadmin\exception\ApiException('仓库读取失败') : null);
        }
    }
}
namespace {
    final class FixtureController extends \plugin\sandpackage\app\controller\InstallController {
        public static int $fixtureAdmin = 1;
        protected function init(): void { $this->adminId = self::$fixtureAdmin; }
    }
    final class RecordingConnection extends \Workerman\Connection\TcpConnection {
        public array $sent = [];
        public bool $closed = false;
        public int $fixtureStatus = self::STATUS_ESTABLISHED;
        public function __construct() {}
        public function __destruct() {}
        public function close(mixed $data = null, bool $raw = false): void { if ($data !== null) $this->send($data, $raw); $this->closed = true; }
        public function getStatus(bool $rawOutput = true): int|string { return $this->fixtureStatus; }
        public function send(mixed $sendBuffer, bool $raw = false): bool|null { $this->sent[] = (string) $sendBuffer; return true; }
    }
    function check(bool $ok, string $message): void {
        if (!$ok) throw new RuntimeException($message);
        echo "[PASS] $message\n";
    }
    function requestFixture(): \support\Request {
        $request = new \support\Request("GET /tool/install/repository/catalog HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->connection = new RecordingConnection();
        return $request;
    }
    FixtureController::$fixtureAdmin = 2;
    try { new FixtureController(); throw new RuntimeException('non-admin accepted'); }
    catch (\plugin\sandadmin\exception\ApiException $error) { check($error->getMessage() === '仅超级管理员能够操作', 'repository controller retains super-admin guard'); }
    FixtureController::$fixtureAdmin = 1;
    $controller = new FixtureController();
    $request = requestFixture();
    $response = $controller->repositoryCatalog($request);
    check($response instanceof \support\Response && $response->getHeader('Transfer-Encoding') === 'chunked', 'middleware receives a real streaming JSON Response');
    check($request->connection->sent === [] && \plugin\sandpackage\app\service\GithubRepositoryClient::$calls === 0, 'headers returned before network callback can send body');
    $head = (string) $response;
    check(str_contains($head, 'Transfer-Encoding: chunked') && !str_contains($head, 'Content-Length:'), 'headers do not terminate with a premature empty response');
    (array_shift(\Workerman\Timer::$tasks))();
    $chunks = $request->connection->sent;
    check(count($chunks) === 2 && $chunks[1] === "0\r\n\r\n", 'exactly one JSON chunk and terminating chunk sent');
    [$size, $body] = explode("\r\n", $chunks[0], 2);
    $data = json_decode(substr($body, 0, hexdec($size)), true, 32, JSON_THROW_ON_ERROR);
    check($data['code'] === 200 && $data['data']['plugins'] === [], 'browser receives ordinary successful API JSON');
    \plugin\sandpackage\app\service\GithubRepositoryClient::$fail = true;
    $request = requestFixture();
    $controller->repositoryCatalog($request);
    (array_shift(\Workerman\Timer::$tasks))();
    [$size, $body] = explode("\r\n", $request->connection->sent[0], 2);
    $errorData = json_decode(substr($body, 0, hexdec($size)), true, 32, JSON_THROW_ON_ERROR);
    check($errorData['code'] === 400 && $errorData['message'] === '仓库读取失败' && count($request->connection->sent) === 2, 'async errors complete with standard error JSON');
    $closeRequest = new \support\Request("GET /tool/install/repository/catalog HTTP/1.1\r\nHost: localhost\r\nConnection: close\r\n\r\n");
    $closeRequest->connection = new RecordingConnection();
    $closeResponse = $controller->repositoryCatalog($closeRequest);
    check(!$closeRequest->connection->closed && $closeResponse->getHeader('Connection') === 'close', 'close request remains open until async JSON completes');
    (array_shift(\Workerman\Timer::$tasks))();
    check($closeRequest->connection->closed && $closeRequest->connection->sent[1] === "0\r\n\r\n", 'close request closes only after terminating JSON chunk');
    $oldRequest = new \support\Request("GET /tool/install/repository/catalog HTTP/1.0\r\nHost: localhost\r\n\r\n");
    $oldRequest->connection = new RecordingConnection();
    $oldResponse = $controller->repositoryCatalog($oldRequest);
    check($oldResponse->getStatusCode() === 505 && \Workerman\Timer::$tasks === [], 'HTTP 1.0 rejected before async operation with ordinary response');
    $request = requestFixture();
    $controller->repositoryCatalog($request);
    $request->connection->fixtureStatus = RecordingConnection::STATUS_CLOSED;
    $before = \plugin\sandpackage\app\service\GithubRepositoryClient::$calls;
    (array_shift(\Workerman\Timer::$tasks))();
    check($request->connection->sent === [] && \plugin\sandpackage\app\service\GithubRepositoryClient::$calls === $before, 'closed connection before dispatch starts no network operation');
    try { $controller->repositoryDownload(requestFixture()); throw new RuntimeException('missing payload accepted'); }
    catch (\plugin\sandadmin\exception\ApiException) { check(\Workerman\Timer::$tasks === [], 'invalid download selection rejected before dispatch'); }
}
