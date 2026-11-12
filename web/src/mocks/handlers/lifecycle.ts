import { http, type HttpHandler } from 'msw';

import type { LifecycleRequest, RequestAccepted } from '@/api/v1/lifecycle';
import type { Plugin, PluginMarketItem, PluginsOverview } from '@/api/v1/plugins';
import type { UpdateStatus } from '@/api/v1/systemUpdate';
import { failV1, okV1, requirePermission, requireUser, v1Base } from './_v1';
import { registerV1Resetter } from './_v1data';

/**
 * 插件 / 系统更新 / 生命周期申请的 mock。
 * 生命周期申请的推进靠 `pollsUntilDone` 计数：每次 GET 前进一格，
 * 让页面不用定时器就能在几次轮询内看到完成态。
 */

interface MockLifecycleRequest extends LifecycleRequest {
  /** 内部字段：到达终态前还要被 GET 轮询的次数。 */
  pollsUntilDone: number;
  /** 内部字段：已被轮询的次数，用来推五阶段位置。 */
  pollCount: number;
}

interface MockMarketItem extends PluginMarketItem {
  uninstallable: boolean;
}

export const mockPluginsOverview: PluginsOverview = {
  compiled: false,
  package_ops_enabled: true,
  signature_required: true,
};

function buildMarketItem(partial: Partial<MockMarketItem> & Pick<MockMarketItem, 'code' | 'name'>): MockMarketItem {
  return {
    id: `plugin-${partial.code}`,
    version: '1.0.0',
    state: 'uninstalled',
    task_state: null,
    rolled_back: false,
    signature_verified: true,
    description: null,
    provides: null,
    install_time: null,
    latest_version: '1.0.0',
    installed: false,
    upgrade_available: false,
    uninstallable: true,
    ...partial,
  };
}

export const mockMarketPlugins: MockMarketItem[] = [
  buildMarketItem({
    code: 'flow',
    name: '流程审批',
    version: '1.2.0',
    latest_version: '1.4.0',
    state: 'installed',
    installed: true,
    upgrade_available: true,
    install_time: '2026-05-10T10:00:00+08:00',
    description: '给后台加请假、报销一类的审批流程',
    provides: { menus: [{ title: '审批中心', url: '/plugin/flow' }] },
  }),
  buildMarketItem({
    code: 'wechat',
    name: '微信互通',
    version: '1.0.3',
    latest_version: '1.0.3',
    state: 'installed',
    installed: true,
    install_time: '2026-04-02T09:00:00+08:00',
    description: '公告和消息可以同步到企业微信',
    provides: { menus: [{ title: '微信设置', url: '/plugin/wechat' }] },
  }),
  buildMarketItem({
    code: 'stats',
    name: '数据看板',
    version: '2.1.0',
    latest_version: '2.1.0',
    description: '首页加一块看今日数据的大屏卡片',
  }),
  buildMarketItem({
    code: 'signin',
    name: '打卡签到',
    version: '0.9.0',
    latest_version: '1.0.0',
    description: '加一个每天打卡的小工具',
  }),
];

export const mockLifecycleRequests: MockLifecycleRequest[] = [];

let requestSeq = 1;

export const mockUpdateStatus = (): UpdateStatus => ({
  current_version: '1.4.2',
  latest_version: '1.6.0',
  update_available: true,
  changes: ['公告可以定时下线了', '部门树加载更快了', '修了几个小问题'],
  bundled_plugins: [
    { key: 'flow', name: '流程审批' },
    { key: 'wechat', name: '微信互通' },
  ],
  checked_time: '2026-06-10T10:00:00+08:00',
  compiled: false,
  package_ops_enabled: true,
  running_request:
    mockLifecycleRequests.find(
      (r) => r.kind === 'system_upgrade' && (r.state === 'queued' || r.state === 'running'),
    ) ?? null,
});

function acceptRequest(partial: Omit<MockLifecycleRequest, 'id' | 'state' | 'create_time' | 'start_time' | 'finish_time' | 'pollsUntilDone' | 'pollCount'> & { pollsUntilDone?: number }): RequestAccepted {
  const request: MockLifecycleRequest = {
    id: `req-${String(requestSeq).padStart(3, '0')}`,
    state: 'queued',
    create_time: new Date().toISOString(),
    start_time: null,
    finish_time: null,
    pollsUntilDone: partial.pollsUntilDone ?? 3,
    pollCount: 0,
    ...partial,
  };
  requestSeq += 1;
  mockLifecycleRequests.unshift(request);
  return { request_id: request.id, state: 'queued' };
}

function findRequest(id: string): MockLifecycleRequest | undefined {
  return mockLifecycleRequests.find((r) => r.id === id);
}

const SYSTEM_STAGES: Array<{ key: LifecycleRequest['stage_key']; stage: string; progress: number }> = [
  { key: 'check', stage: '正在检查', progress: 10 },
  { key: 'backup', stage: '正在备份', progress: 35 },
  { key: 'download', stage: '正在下载', progress: 60 },
  { key: 'switch', stage: '正在切换', progress: 85 },
  { key: 'verify', stage: '正在检查结果', progress: 95 },
];

/** 每被轮询一次前进一格，系统升级按五阶段走，插件申请直接完成。 */
export function pollLifecycle(request: MockLifecycleRequest): MockLifecycleRequest {
  if (request.state !== 'queued' && request.state !== 'running') {
    return request;
  }
  request.pollsUntilDone -= 1;
  request.pollCount += 1;
  request.state = 'running';
  request.start_time = request.start_time ?? new Date().toISOString();
  if (request.kind === 'system_upgrade') {
    const stageIndex = Math.min(SYSTEM_STAGES.length - 1, request.pollCount - 1);
    const stage = SYSTEM_STAGES[Math.max(0, stageIndex)];
    request.stage_key = stage.key;
    request.stage = stage.stage;
    request.progress = stage.progress;
  } else {
    request.progress = Math.min(90, request.progress + 30);
    request.stage = request.stage ?? '正在处理';
  }
  if (request.items) {
    // 批量申请：每次轮询把下一个排队项推进为 succeeded。
    const next = request.items.find((item) => item.state === 'queued' || item.state === 'running');
    if (next) {
      next.state = 'succeeded';
    }
  }
  if (request.pollsUntilDone <= 0) {
    request.state = 'succeeded';
    request.progress = 100;
    request.finish_time = new Date().toISOString();
    const plugin = mockMarketPlugins.find((p) => p.code === request.target_code);
    if (plugin) {
      if (request.kind === 'plugin_install') {
        plugin.state = 'installed';
        plugin.installed = true;
        plugin.install_time = request.finish_time;
        plugin.version = request.to_version ?? plugin.latest_version;
      } else if (request.kind === 'plugin_upgrade') {
        plugin.version = request.to_version ?? plugin.latest_version;
        plugin.upgrade_available = false;
      } else if (request.kind === 'plugin_uninstall') {
        plugin.state = 'uninstalled';
        plugin.installed = false;
        plugin.upgrade_available = false;
      }
      plugin.task_state = null;
    }
    if (request.kind === 'plugin_upgrade_batch' && request.items) {
      for (const item of request.items) {
        const plugin = mockMarketPlugins.find((p) => p.code === item.code);
        if (plugin && item.state === 'succeeded') {
          plugin.version = item.to_version ?? plugin.latest_version;
          plugin.upgrade_available = false;
        }
        if (plugin) {
          plugin.task_state = null;
        }
      }
    }
  }
  return request;
}

/** 进行中申请占用的插件编码；用于卡片上的 task_state。 */
function syncTaskStates(): void {
  for (const plugin of mockMarketPlugins) {
    plugin.task_state = null;
  }
  const active = mockLifecycleRequests.find(
    (r) => r.state === 'queued' || r.state === 'running',
  );
  if (!active) return;
  if (active.kind === 'plugin_upgrade_batch' && active.items) {
    for (const item of active.items) {
      if (item.state === 'queued' || item.state === 'running') {
        const plugin = mockMarketPlugins.find((p) => p.code === item.code);
        if (plugin) plugin.task_state = item.state;
      }
    }
    return;
  }
  const plugin = mockMarketPlugins.find((p) => p.code === active.target_code);
  if (plugin) plugin.task_state = active.state;
}

function hasActiveRequest(): boolean {
  return mockLifecycleRequests.some((r) => r.state === 'queued' || r.state === 'running');
}

/** 测试钩子：清掉生命周期申请并把插件恢复出厂快照。 */
const initialMarket = structuredClone(mockMarketPlugins);
export function resetLifecycleMock(): void {
  mockLifecycleRequests.splice(0, mockLifecycleRequests.length);
  mockMarketPlugins.splice(0, mockMarketPlugins.length, ...structuredClone(initialMarket));
  mockPluginsOverview.compiled = false;
  mockPluginsOverview.package_ops_enabled = true;
  requestSeq = 1;
}
registerV1Resetter(resetLifecycleMock);

function pluginView(item: MockMarketItem): Plugin {
  return {
    id: item.id,
    code: item.code,
    name: item.name,
    version: item.version,
    state: item.state,
    task_state: item.task_state,
    rolled_back: item.rolled_back,
    signature_verified: item.signature_verified,
    description: item.description,
    provides: item.provides,
    install_time: item.install_time,
  };
}

/** 对外字段=LifecycleRequest；pollsUntilDone/pollCount 是 mock 内部字段，不外泄。 */
function toPublicRequest(r: MockLifecycleRequest): LifecycleRequest {
  return {
    id: r.id,
    kind: r.kind,
    target_code: r.target_code,
    source: r.source,
    from_version: r.from_version,
    to_version: r.to_version,
    purge_data: r.purge_data,
    items: r.items,
    state: r.state,
    stage: r.stage,
    stage_key: r.stage_key,
    progress: r.progress,
    rolled_back: r.rolled_back,
    error_code: r.error_code,
    error_message: r.error_message,
    create_time: r.create_time,
    start_time: r.start_time,
    finish_time: r.finish_time,
  };
}

export default [
  http.get(`${v1Base}/plugins/overview`, ({ request }) => {
    const auth = requirePermission(request, 'system:plugin:index');
    if ('response' in auth) return auth.response;
    return okV1(mockPluginsOverview);
  }),

  http.get(`${v1Base}/plugins/market`, ({ request }) => {
    const auth = requirePermission(request, 'system:plugin:index');
    if ('response' in auth) return auth.response;
    syncTaskStates();
    return okV1({ items: mockMarketPlugins });
  }),

  http.get(`${v1Base}/plugins/installed`, ({ request }) => {
    const auth = requirePermission(request, 'system:plugin:index');
    if ('response' in auth) return auth.response;
    syncTaskStates();
    return okV1({ items: mockMarketPlugins.filter((p) => p.installed).map(pluginView) });
  }),

  http.get(`${v1Base}/plugins/:pluginCode`, ({ request, params }) => {
    const auth = requirePermission(request, 'system:plugin:index');
    if ('response' in auth) return auth.response;
    const plugin = mockMarketPlugins.find((p) => p.code === String(params.pluginCode));
    if (!plugin) {
      return failV1(404, 'PLUGIN_NOT_FOUND', '插件不存在或已经被卸载');
    }
    syncTaskStates();
    return okV1(pluginView(plugin));
  }),

  http.post(`${v1Base}/plugins/:pluginCode/install`, ({ request, params }) => {
    const auth = requirePermission(request, 'system:plugin:install');
    if ('response' in auth) return auth.response;
    if (!auth.user.is_super) {
      return failV1(403, 'PLUGIN_OPERATION_FORBIDDEN', '只有超级管理员能安装插件');
    }
    if (mockPluginsOverview.compiled || !mockPluginsOverview.package_ops_enabled) {
      return failV1(403, 'PLUGIN_OPERATION_FORBIDDEN', '这个版本不能在网页上安装插件');
    }
    const plugin = mockMarketPlugins.find((p) => p.code === String(params.pluginCode));
    if (!plugin) {
      return failV1(404, 'PLUGIN_NOT_FOUND', '插件不存在或已经被卸载');
    }
    if (plugin.installed) {
      return failV1(409, 'PLUGIN_ALREADY_INSTALLED', '「{{name}}」已经装好了'.replace('{{name}}', plugin.name));
    }
    if (hasActiveRequest()) {
      return failV1(409, 'PLUGIN_BUSY', '刚才已经有别的安装或升级开始了，做完后再试');
    }
    return okV1(
      acceptRequest({
        kind: 'plugin_install',
        target_code: plugin.code,
        source: 'official',
        from_version: null,
        to_version: plugin.latest_version,
        purge_data: false,
        items: null,
        stage: '正在处理',
        stage_key: null,
        progress: 0,
        rolled_back: false,
        error_code: null,
        error_message: null,
      }),
    );
  }),

  http.post(`${v1Base}/plugins/:pluginCode/upgrade`, ({ request, params }) => {
    const auth = requirePermission(request, 'system:plugin:upgrade');
    if ('response' in auth) return auth.response;
    if (!auth.user.is_super) {
      return failV1(403, 'PLUGIN_OPERATION_FORBIDDEN', '只有超级管理员能升级插件');
    }
    if (mockPluginsOverview.compiled || !mockPluginsOverview.package_ops_enabled) {
      return failV1(403, 'PLUGIN_OPERATION_FORBIDDEN', '这个版本不能在网页上升级插件');
    }
    const plugin = mockMarketPlugins.find((p) => p.code === String(params.pluginCode));
    if (!plugin) {
      return failV1(404, 'PLUGIN_NOT_FOUND', '插件不存在或已经被卸载');
    }
    if (!plugin.installed) {
      return failV1(409, 'PLUGIN_NOT_INSTALLED', '这个插件还没安装');
    }
    if (hasActiveRequest()) {
      return failV1(409, 'PLUGIN_BUSY', '刚才已经有别的安装或升级开始了，做完后再试');
    }
    return okV1(
      acceptRequest({
        kind: 'plugin_upgrade',
        target_code: plugin.code,
        source: 'official',
        from_version: plugin.version,
        to_version: plugin.latest_version,
        purge_data: false,
        items: null,
        stage: '正在处理',
        stage_key: null,
        progress: 0,
        rolled_back: false,
        error_code: null,
        error_message: null,
      }),
    );
  }),

  http.post(`${v1Base}/plugins/:pluginCode/uninstall`, async ({ request, params }) => {
    const auth = requirePermission(request, 'system:plugin:uninstall');
    if ('response' in auth) return auth.response;
    if (!auth.user.is_super) {
      return failV1(403, 'PLUGIN_OPERATION_FORBIDDEN', '只有超级管理员能卸载插件');
    }
    const plugin = mockMarketPlugins.find((p) => p.code === String(params.pluginCode));
    if (!plugin) {
      return failV1(404, 'PLUGIN_NOT_FOUND', '插件不存在或已经被卸载');
    }
    if (!plugin.installed) {
      return failV1(409, 'PLUGIN_NOT_INSTALLED', '这个插件还没安装');
    }
    if (hasActiveRequest()) {
      return failV1(409, 'PLUGIN_BUSY', '刚才已经有别的安装或升级开始了，做完后再试');
    }
    let purge = false;
    try {
      const body = (await request.json()) as { purge_data?: boolean };
      purge = body.purge_data === true;
    } catch {
      purge = false;
    }
    return okV1(
      acceptRequest({
        kind: 'plugin_uninstall',
        target_code: plugin.code,
        source: 'official',
        from_version: plugin.version,
        to_version: null,
        purge_data: purge,
        items: null,
        stage: '正在处理',
        stage_key: null,
        progress: 0,
        rolled_back: false,
        error_code: null,
        error_message: null,
      }),
    );
  }),

  http.post(`${v1Base}/plugins/upgrade-all`, ({ request }) => {
    const auth = requirePermission(request, 'system:plugin:upgrade');
    if ('response' in auth) return auth.response;
    if (!auth.user.is_super) {
      return failV1(403, 'PLUGIN_OPERATION_FORBIDDEN', '只有超级管理员能升级插件');
    }
    if (mockPluginsOverview.compiled || !mockPluginsOverview.package_ops_enabled) {
      return failV1(403, 'PLUGIN_OPERATION_FORBIDDEN', '这个版本不能在网页上升级插件');
    }
    if (hasActiveRequest()) {
      return failV1(409, 'PLUGIN_BUSY', '刚才已经有别的安装或升级开始了，做完后再试');
    }
    const upgradeable = mockMarketPlugins.filter((p) => p.upgrade_available);
    if (upgradeable.length === 0) {
      return failV1(409, 'UPDATE_NOT_AVAILABLE', '没有可以升级的插件');
    }
    return okV1(
      acceptRequest({
        kind: 'plugin_upgrade_batch',
        target_code: '*',
        source: 'official',
        from_version: null,
        to_version: null,
        purge_data: false,
        items: upgradeable.map((p) => ({
          code: p.code,
          from_version: p.version,
          to_version: p.latest_version,
          state: 'queued' as const,
        })),
        stage: '正在处理',
        stage_key: null,
        progress: 0,
        rolled_back: false,
        error_code: null,
        error_message: null,
        pollsUntilDone: upgradeable.length + 1,
      }),
    );
  }),

  http.post(`${v1Base}/plugins/upload`, async ({ request }) => {
    const auth = requirePermission(request, 'system:plugin:upload');
    if ('response' in auth) return auth.response;
    if (!auth.user.is_super) {
      return failV1(403, 'PLUGIN_OPERATION_FORBIDDEN', '只有超级管理员能安装插件');
    }
    if (mockPluginsOverview.compiled || !mockPluginsOverview.package_ops_enabled) {
      return failV1(403, 'PLUGIN_OPERATION_FORBIDDEN', '这个版本不能在网页上安装插件');
    }
    const form = await request.formData();
    const file = form.get('package');
    if (!(file instanceof File) || !file.name.endsWith('.zip')) {
      return failV1(422, 'PLUGIN_PACKAGE_INVALID', '这个文件不是能用的安装包，请检查后再试');
    }
    if (hasActiveRequest()) {
      return failV1(409, 'PLUGIN_BUSY', '刚才已经有别的安装或升级开始了，做完后再试');
    }
    return okV1(
      acceptRequest({
        kind: 'plugin_install',
        target_code: file.name.replace(/\.zip$/i, '') || 'upload',
        source: 'upload',
        from_version: null,
        to_version: null,
        purge_data: false,
        items: null,
        stage: '正在处理',
        stage_key: null,
        progress: 0,
        rolled_back: false,
        error_code: null,
        error_message: null,
      }),
    );
  }),

  http.get(`${v1Base}/system/update/status`, ({ request }) => {
    const auth = requirePermission(request, 'system:update:index');
    if ('response' in auth) return auth.response;
    return okV1(mockUpdateStatus());
  }),

  http.post(`${v1Base}/system/update/check`, ({ request }) => {
    const auth = requirePermission(request, 'system:update:check');
    if ('response' in auth) return auth.response;
    return okV1(mockUpdateStatus());
  }),

  http.post(`${v1Base}/system/update/upload`, async ({ request }) => {
    const auth = requirePermission(request, 'system:update:upload');
    if ('response' in auth) return auth.response;
    if (!auth.user.is_super) {
      return failV1(403, 'SYSTEM_UPDATE_OPERATION_FORBIDDEN', '只有超级管理员能升级系统');
    }
    const form = await request.formData();
    const file = form.get('package');
    if (!(file instanceof File) || !file.name.endsWith('.zip')) {
      return failV1(422, 'UPDATE_PACKAGE_INVALID', '这个文件不是能用的升级包，请检查后再试');
    }
    if (hasActiveRequest()) {
      return failV1(409, 'UPDATE_IN_PROGRESS', '刚才已经有别的安装或升级开始了，做完后再试');
    }
    return okV1(
      acceptRequest({
        kind: 'system_upgrade',
        target_code: 'host',
        source: 'upload',
        from_version: null,
        to_version: null,
        purge_data: false,
        items: null,
        stage: '正在检查',
        stage_key: 'check',
        progress: 0,
        rolled_back: false,
        error_code: null,
        error_message: null,
        pollsUntilDone: 5,
      }),
    );
  }),

  http.post(`${v1Base}/system/update/start`, async ({ request }) => {
    const auth = requirePermission(request, 'system:update:start');
    if ('response' in auth) return auth.response;
    if (!auth.user.is_super) {
      return failV1(403, 'SYSTEM_UPDATE_OPERATION_FORBIDDEN', '只有超级管理员能升级系统');
    }
    const status = mockUpdateStatus();
    if (!status.package_ops_enabled || status.compiled) {
      return failV1(403, 'SYSTEM_UPDATE_OPERATION_FORBIDDEN', '这个版本不能在网页上升级');
    }
    if (hasActiveRequest()) {
      return failV1(409, 'UPDATE_IN_PROGRESS', '刚才已经有别的安装或升级开始了，做完后再试');
    }
    let target = status.latest_version;
    try {
      const body = (await request.json()) as { target_version?: string };
      if (body.target_version) target = body.target_version;
    } catch {
      // 空 body 用默认最新版本
    }
    if (!status.update_available && !target) {
      return failV1(409, 'UPDATE_NOT_AVAILABLE', '没有可以升级的版本');
    }
    return okV1(
      acceptRequest({
        kind: 'system_upgrade',
        target_code: 'host',
        source: 'official',
        from_version: status.current_version,
        to_version: target,
        purge_data: false,
        items: null,
        stage: '正在检查',
        stage_key: 'check',
        progress: 0,
        rolled_back: false,
        error_code: null,
        error_message: null,
        pollsUntilDone: 5,
      }),
    );
  }),

  http.get(`${v1Base}/lifecycle-requests/:requestId`, ({ request, params }) => {
    const auth = requireUser(request);
    if ('response' in auth) return auth.response;
    const found = findRequest(String(params.requestId));
    if (!found) {
      return failV1(404, 'LIFECYCLE_REQUEST_NOT_FOUND', '要查看的进度不存在或已被清理');
    }
    return okV1(toPublicRequest(pollLifecycle(found)));
  }),
] satisfies HttpHandler[];
