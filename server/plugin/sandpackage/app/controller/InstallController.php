<?php

namespace plugin\sandpackage\app\controller;

use plugin\sandadmin\app\cache\UserMenuCache;
use plugin\sandadmin\app\middleware\SystemLog;
use plugin\sandadmin\app\middleware\CheckLogin;
use plugin\sandadmin\basic\BaseController;
use plugin\sandadmin\exception\ApiException;
use plugin\sandpackage\app\logic\InstallLogic;
use Saithink\Saipackage\service\Server;
use Saithink\Saipackage\service\Version;
use support\annotation\Middleware;
use support\Request;
use support\Response;
use Throwable;

#[Middleware(CheckLogin::class, SystemLog::class)]
class InstallController extends BaseController
{
    /**
     * 构造
     */
    public function __construct()
    {
        parent::__construct();
        if ($this->adminId !== 1) {
            throw new ApiException('仅超级管理员能够操作');
        }
    }

    /**
     * 环境检查状态
     */
    static string $ok = 'ok';
    static string $fail = 'fail';
    static string $warn = 'warn';

    static array $needDependentVersion = [
        'php' => '8.1.0',
        'sandadmin' => '6.0.0',
        'sandpackage' => '6.0.0',
    ];

    /**
     * 应用列表
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $data = Server::installedList(runtime_path() . DIRECTORY_SEPARATOR . 'sandpackage' . DIRECTORY_SEPARATOR);
        $data = array_map(static function (array $item): array {
            return array_merge($item, InstallLogic::presentInfo($item));
        }, $data);

        $phpVersion = phpversion();
        $phpVersionCompare = Version::compare(self::$needDependentVersion['php'], $phpVersion);
        $phpVersionNotes = '正常';
        if (!$phpVersionCompare) {
            $phpVersionNotes = '需要版本' . ' >= ' . self::$needDependentVersion['php'];
        }

        $sandadminVersion = config('plugin.sandadmin.app.version');
        $sandadminVersionCompare = Version::compare(self::$needDependentVersion['sandadmin'], $sandadminVersion);
        $sandadminVersionNotes = '正常';
        if (!$sandadminVersionCompare) {
            $sandadminVersionNotes = '需要版本' . ' >= ' . self::$needDependentVersion['sandadmin'];
        }

        $saithinkVersion = config('plugin.sandpackage.app.version');
        $saithinkVersionCompare = Version::compare(self::$needDependentVersion['sandpackage'], $saithinkVersion);
        $saithinkVersionNotes = '正常';
        if (!$saithinkVersionCompare) {
            $saithinkVersionNotes = '需要版本' . ' >= ' . self::$needDependentVersion['sandpackage'];
        }


        return $this->success([
            'version' => [
                'php_version' => [
                    'describe' => $phpVersion,
                    'state' => $phpVersionCompare ? self::$ok : self::$fail,
                    'notes' => $phpVersionNotes,
                ],
                'sandadmin_version' => [
                    'describe' => $sandadminVersion,
                    'state' => $sandadminVersionCompare ? self::$ok : self::$fail,
                    'notes' => $sandadminVersionNotes,
                ],
                'sandpackage_version' => [
                    'describe' => $saithinkVersion,
                    'state' => $saithinkVersionCompare ? self::$ok : self::$fail,
                    'notes' => $saithinkVersionNotes,
                ],
            ],
            'data' => $data
        ]);
    }

    /**
     * 上传插件
     * @param Request $request
     * @return Response
     * @throws Throwable
     */
    public function upload(Request $request): Response
    {
        $spl_file = $request->file('file');
        if (!is_object($spl_file)
            || !method_exists($spl_file, 'isValid')
            || !method_exists($spl_file, 'getUploadExtension')
            || !method_exists($spl_file, 'getSize')) {
            throw new ApiException('请选择有效的插件包文件后再上传', 400);
        }
        if (!$spl_file->isValid()) {
            throw new ApiException('上传文件未通过校验，请重新选择完整的 ZIP 插件包', 400);
        }
        $config = config('plugin.sandpackage.upload', [
            'size' => 1024 * 1024 * 5,
            'type' => ['zip']
        ]);
        if (!in_array($spl_file->getUploadExtension(), $config['type'])) {
            return $this->fail('文件格式上传失败,请选择zip格式文件上传');
        }
        if ($spl_file->getSize() > $config['size']) {
            return $this->fail('文件大小不能超过5M');
        }
        $install = new InstallLogic();
        $info = $install->upload($spl_file);
        return $this->success($info);
    }

    /**
     * 安装插件
     * @param Request $request
     * @return Response
     * @throws Throwable
     */
    public function install(Request $request): Response
    {
        $appName = $request->post("appName", '');
        if (empty($appName)) {
            return $this->fail('参数错误');
        }
        $install = new InstallLogic($appName);
        $confirmation = (string) $request->post('confirmation', '');
        $info = $install->install(true, $confirmation);
        UserMenuCache::clearMenuCache();
        return $this->success($info);
    }

    /**
     * Formally register a plugin that is already deployed on this host.
     * This endpoint never imports SQL or deploys package files.
     *
     * @throws Throwable
     */
    public function registerExisting(Request $request): Response
    {
        if (strtoupper($request->method()) !== 'POST') {
            return $this->fail('登记操作仅支持 POST 请求');
        }
        $appName = trim((string) $request->post('appName', ''));
        $confirmation = (string) $request->post('confirmation', '');
        if ($appName === '' || $confirmation === '') {
            return $this->fail('请填写插件标识和完整登记确认内容');
        }
        $install = new InstallLogic($appName);
        $info = $install->registerExisting($confirmation);
        return $this->success(array_merge($info, InstallLogic::presentInfo($info)), '插件登记完成');
    }

    /**
     * Revert only a ready, unexecuted upgrade candidate. SystemLog records the
     * operation; the logic never invokes SQL, file deployment or service reload.
     *
     * @throws Throwable
     */
    public function discardCandidate(Request $request): Response
    {
        if (strtoupper($request->method()) !== 'POST') {
            return $this->fail('撤回候选仅支持 POST 请求');
        }
        $appName = trim((string) $request->post('appName', ''));
        $confirmation = (string) $request->post('confirmation', '');
        if ($appName === '' || $confirmation === '') {
            return $this->fail('请填写插件标识和完整撤回确认内容');
        }
        $install = new InstallLogic($appName);
        $info = $install->discardCandidate($confirmation);
        return $this->success(array_merge($info, InstallLogic::presentInfo($info)), '升级候选已撤回，数据库未执行无需回滚');
    }

    /** Read-only recovery inspection; no directory, journal or runtime mutation. */
    public function inspectFailedUpgradeRecovery(Request $request): Response
    {
        if (strtoupper($request->method()) !== 'POST') throw new ApiException('恢复检查仅支持 POST 请求', 400);
        $appName = trim((string) $request->post('appName', ''));
        if ($appName === '') throw new ApiException('请填写插件标识', 400);
        return $this->success((new InstallLogic($appName))->inspectFailedUpgradeRecovery($this->adminId), '恢复状态检查完成');
    }

    /** Restore only runtime files from the identity-bound pre-upgrade backup. */
    public function restoreRuntimeFromBackup(Request $request): Response
    {
        if (strtoupper($request->method()) !== 'POST') throw new ApiException('运行文件恢复仅支持 POST 请求', 400);
        $appName = trim((string) $request->post('appName', ''));
        $confirmation = (string) $request->post('confirmation', '');
        if ($appName === '' || $confirmation === '') throw new ApiException('请填写插件标识和完整运行文件恢复确认内容', 400);
        $result = (new InstallLogic($appName))->restoreRuntimeFromBackup($confirmation, $this->adminId);
        return $this->success([
            'result' => $result,
            'presentation' => [
                'recovery_mode' => 'verification_required',
                'allowed_actions' => ['prepare_failed_upgrade_replacement'],
                'message' => $result['message'],
            ],
        ], '运行文件恢复完成');
    }

    /** Revalidates one prepared replacement; no confirmation is issued. */
    public function verifyFailedUpgradeRecovery(Request $request): Response
    {
        if (strtoupper($request->method()) !== 'POST') {
            throw new ApiException('恢复核验仅支持 POST 请求', 400);
        }
        if ($this->adminId !== 1) {
            throw new ApiException('仅超级管理员能够执行恢复核验', 400);
        }
        $appName = trim((string) $request->post('appName', ''));
        $replacementId = trim((string) $request->post('replacementId', ''));
        if ($appName === '' || $replacementId === '') throw new ApiException('请填写插件标识和替换候选标识', 400);
        $result = (new InstallLogic($appName))->verifyPreparedFailedUpgradeReplacement($replacementId, $this->adminId);
        return $this->success($result, '恢复条件核验完成');
    }

    /** Receive and preflight a private failed-upgrade replacement archive. */
    public function prepareFailedUpgradeReplacement(Request $request): Response
    {
        if (strtoupper($request->method()) !== 'POST') throw new ApiException('替换候选预检仅支持 POST 请求', 400);
        if ($this->adminId !== 1) throw new ApiException('仅超级管理员能够预检替换候选', 400);
        $appName = trim((string) $request->post('appName', ''));
        $file = $request->file('file');
        if ($appName === '' || !is_object($file)) throw new ApiException('请填写插件标识并选择替换 ZIP 包', 400);
        $result = (new InstallLogic($appName))->prepareFailedUpgradeReplacement($file, $this->adminId);
        return $this->success($result, '替换候选预检完成');
    }

    /** Durably exchange the failed candidate for one prepared private package. */
    public function replaceFailedUpgradeCandidate(Request $request): Response
    {
        if (strtoupper($request->method()) !== 'POST') throw new ApiException('替换失败候选仅支持 POST 请求', 400);
        if ($this->adminId !== 1) throw new ApiException('仅超级管理员能够替换失败候选', 400);
        $appName = trim((string) $request->post('appName', ''));
        $replacementId = trim((string) $request->post('replacementId', ''));
        $confirmation = (string) $request->post('confirmation', '');
        if ($appName === '' || $replacementId === '' || $confirmation === '') throw new ApiException('请填写插件标识、替换候选标识和完整替换确认内容', 400);
        $result = (new InstallLogic($appName))->replaceFailedUpgradeCandidate($replacementId, $confirmation, $this->adminId);
        return $this->success($result, '失败候选替换完成');
    }

    /** Execute the already verified replacement's update.sql, never install.sql. */
    public function retryFailedUpgrade(Request $request): Response
    {
        if (strtoupper($request->method()) !== 'POST') throw new ApiException('失败升级重试仅支持 POST 请求', 400);
        if ($this->adminId !== 1) throw new ApiException('仅超级管理员能够重试失败升级', 400);
        $appName = trim((string) $request->post('appName', ''));
        $confirmation = (string) $request->post('confirmation', '');
        if ($appName === '' || $confirmation === '') throw new ApiException('请填写插件标识和完整重试确认内容', 400);
        $result = (new InstallLogic($appName))->retryFailedUpgrade($confirmation, $this->adminId);
        return $this->success($result, '失败升级重试完成');
    }

    /**
     * 卸载插件
     * @param Request $request
     * @return Response
     * @throws Throwable
     */
    public function uninstall(Request $request): Response
    {
        $appName = $request->post("appName", '');
        if (empty($appName)) {
            return $this->fail('参数错误');
        }
        $install = new InstallLogic($appName);
        $install->uninstall();
        UserMenuCache::clearMenuCache();
        return $this->success('卸载插件成功');
    }

    /**
     * 重启
     * @param Request $request
     * @return Response
     */
    public function reload(Request $request): Response
    {
        Server::restart();

        return $this->success('重载成功');
    }

    // ========== 商店代理接口 ==========

    /**
     * 代理请求封装
     */
    protected function proxyRequest(string $url, string $method = 'GET', ?string $token = null, ?array $postData = null, int $timeout = 10): array
    {
        $headers = [];
        if ($token) {
            $headers[] = "Authorization: Bearer {$token}";
        }
        if ($postData !== null) {
            $headers[] = "Content-Type: application/json";
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $postData ? json_encode($postData) : null,
                'timeout' => $timeout,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            return ['success' => false, 'message' => '请求失败'];
        }

        // 尝试解析 JSON
        $data = json_decode($response, true);
        if ($data && isset($data['code'])) {
            if ($data['code'] === 200) {
                return ['success' => true, 'data' => $data['data'] ?? null];
            }
            return ['success' => false, 'message' => $data['message'] ?? '请求失败'];
        }

        // 非 JSON 响应（可能是文件）
        return ['success' => true, 'raw' => $response, 'headers' => $http_response_header ?? []];
    }

    /**
     * 获取应用商店列表
     */
    public function appList(Request $request): Response
    {
        $params = http_build_query([
            'page' => $request->input('page', 1),
            'limit' => $request->input('limit', 16),
            'price' => $request->input('price', 'all'),
            'type' => $request->input('type', ''),
            'keywords' => $request->input('keywords', ''),
        ]);

        $result = $this->proxyRequest("https://saas.saithink.top/dev-api/app/saistore/api/store/appList?{$params}");

        return $result['success']
            ? $this->success($result['data'])
            : $this->fail($result['message']);
    }

    /**
     * 获取商店验证码
     */
    public function storeCaptcha(): Response
    {
        $result = $this->proxyRequest("https://saas.saithink.top/dev-api/app/saiuser/api/common/index/captcha");

        return $result['success']
            ? $this->success($result['data'])
            : $this->fail($result['message']);
    }

    /**
     * 商店登录
     */
    public function storeLogin(Request $request): Response
    {
        $result = $this->proxyRequest(
            "https://saas.saithink.top/dev-api/app/saiuser/api/common/index/accountLogin",
            'POST',
            null,
            [
                'username' => $request->input('username'),
                'password' => $request->input('password'),
                'code' => $request->input('code'),
                'uuid' => $request->input('uuid'),
            ]
        );

        return $result['success']
            ? $this->success($result['data'])
            : $this->fail($result['message']);
    }

    /**
     * 获取商店用户信息
     */
    public function storeUserInfo(Request $request): Response
    {
        $token = $request->input('token');
        if (empty($token)) {
            return $this->fail('未登录');
        }

        $result = $this->proxyRequest(
            "https://saas.saithink.top/dev-api/app/saiuser/api/user/user/userInfo",
            'GET',
            $token
        );

        return $result['success']
            ? $this->success($result['data'])
            : $this->fail($result['message']);
    }

    /**
     * 获取已购应用列表
     */
    public function storePurchasedApps(Request $request): Response
    {
        $token = $request->input('token');
        if (empty($token)) {
            return $this->fail('未登录');
        }

        $result = $this->proxyRequest(
            "https://saas.saithink.top/dev-api/app/saistore/api/StoreOrder/orderList?saiType=all",
            'GET',
            $token
        );

        return $result['success']
            ? $this->success($result['data'])
            : $this->fail($result['message']);
    }

    /**
     * 获取应用版本列表
     */
    public function storeAppVersions(Request $request): Response
    {
        $token = $request->input('token');
        $appId = $request->input('app_id');

        if (empty($token)) {
            return $this->fail('未登录');
        }

        $result = $this->proxyRequest(
            "https://saas.saithink.top/dev-api/app/saistore/api/StoreOrder/appVersionList?app_id={$appId}",
            'GET',
            $token
        );

        return $result['success']
            ? $this->success($result['data'])
            : $this->fail($result['message']);
    }

    /**
     * 下载应用 - 下载并调用 InstallLogic 处理
     */
    public function storeDownloadApp(Request $request): Response
    {
        $token = $request->input('token');
        $versionId = $request->input('id');

        if (empty($token)) {
            return $this->fail('未登录');
        }

        if (empty($versionId)) {
            return $this->fail('版本ID不能为空');
        }

        $result = $this->proxyRequest(
            "https://saas.saithink.top/dev-api/app/saistore/api/StoreOrder/downloadVersion",
            'POST',
            $token,
            ['version_id' => (int) $versionId],
            60
        );

        if (!$result['success']) {
            return $this->fail($result['message'] ?? '下载失败');
        }

        if (!isset($result['raw'])) {
            return $this->fail('下载失败');
        }

        // 保存临时 zip 文件
        $tempZip = runtime_path() . DIRECTORY_SEPARATOR . 'sandpackage' . DIRECTORY_SEPARATOR . 'downloadTemp' . date('YmdHis') . '.zip';
        if (!is_dir(dirname($tempZip))) {
            mkdir(dirname($tempZip), 0755, true);
        }
        file_put_contents($tempZip, $result['raw']);

        try {
            // 调用 InstallLogic 处理
            $install = new InstallLogic();
            $info = $install->uploadFromPath($tempZip);

            return $this->success($info, '下载成功，请在插件列表中安装');
        } catch (Throwable $e) {
            @unlink($tempZip);
            return $this->fail($e->getMessage());
        }
    }
}
