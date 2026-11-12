import { http } from 'msw';

import type { AuditLogDetail } from '@/api/v1/auditLogs';
import type { LoginLog } from '@/api/v1/loginLogs';
import type { ScheduleRun, ScheduledTask } from '@/api/v1/schedules';
import type { SettingGroup, SettingItem } from '@/api/v1/settings';

import {
  failV1,
  okV1,
  queryPage,
  queryParam,
  requirePermission,
  requireUser,
  v1Base,
} from './_v1';
import { mockMenuTree, mockUsers } from './_v1data';

/*
 * 监控与工具域 mock：/monitor/server、/login-logs、/audit-logs、
 * /scheduled-tasks、/settings、/search。数据是内存态，写操作
 * 在页面生命周期内保持。
 */

const T0 = '2026-01-10T08:00:00+08:00';

// ---------- 运行状态 ----------

const serverStatus = {
  collected_time: T0,
  server_time: T0,
  version: '1.2.0',
  db_ok: true,
  db_check_time: T0,
  cpu: { usage_percent: 23 },
  memory: { usage_percent: 41, used_mb: 512, total_mb: 1248 },
  disk: { free_gb: 42, total_gb: 100 },
  process: { memory_mb: 512, uptime_seconds: 432000 },
};

// ---------- 登录日志 ----------

const loginLogs: LoginLog[] = [
  { id: 'll-1', user_id: 'u-admin', nickname: '管理员', username: 'admin', succeeded: true, reason: null, ip: '10.0.0.8', location: '公司网络', device_label: 'Mac · Chrome', login_time: '2026-01-10T09:12:00+08:00' },
  { id: 'll-2', user_id: 'u-zhangsan', nickname: '张三', username: 'zhangsan', succeeded: true, reason: null, ip: '10.0.0.21', location: '山东青岛', device_label: 'Windows · Edge', login_time: '2026-01-10T08:47:00+08:00' },
  { id: 'll-3', user_id: 'u-lisi', nickname: '李四', username: 'lisi', succeeded: false, reason: '密码不对', ip: '10.0.0.32', location: '山东青岛', device_label: 'iPhone · Safari', login_time: '2026-01-10T08:15:00+08:00' },
  { id: 'll-4', user_id: null, nickname: null, username: 'wangwu', succeeded: false, reason: '没有这个账号', ip: '203.0.113.9', location: '未知', device_label: 'Windows · Chrome', login_time: '2026-01-09T22:03:00+08:00' },
  { id: 'll-5', user_id: 'u-admin', nickname: '管理员', username: 'admin', succeeded: true, reason: null, ip: '10.0.0.8', location: '公司网络', device_label: 'Mac · Chrome', login_time: '2026-01-09T09:02:00+08:00' },
  { id: 'll-6', user_id: 'u-zhaoliu', nickname: '赵六', username: 'zhaoliu', succeeded: false, reason: '账号已停用', ip: '10.0.0.55', location: '山东青岛', device_label: 'Android · Chrome', login_time: '2026-01-09T08:30:00+08:00' },
  { id: 'll-7', user_id: 'u-zhangsan', nickname: '张三', username: 'zhangsan', succeeded: false, reason: null, ip: '10.0.0.21', location: '山东青岛', device_label: 'Windows · Edge', login_time: '2026-01-08T18:40:00+08:00' },
  { id: 'll-8', user_id: 'u-lisi', nickname: '李四', username: 'lisi', succeeded: true, reason: null, ip: '10.0.0.32', location: '山东青岛', device_label: 'iPhone · Safari', login_time: '2026-01-08T09:00:00+08:00' },
  { id: 'll-9', user_id: 'u-admin', nickname: '管理员', username: 'admin', succeeded: true, reason: null, ip: '10.0.0.8', location: '公司网络', device_label: 'Mac · Chrome', login_time: '2026-01-07T09:05:00+08:00' },
  { id: 'll-10', user_id: 'u-zhangsan', nickname: '张三', username: 'zhangsan', succeeded: true, reason: null, ip: '10.0.0.21', location: '山东青岛', device_label: 'Windows · Edge', login_time: '2026-01-07T08:55:00+08:00' },
  { id: 'll-11', user_id: 'u-admin', nickname: '管理员', username: 'admin', succeeded: true, reason: null, ip: '10.0.0.8', location: '公司网络', device_label: 'Mac · Chrome', login_time: '2026-01-06T09:10:00+08:00' },
  { id: 'll-12', user_id: 'u-lisi', nickname: '李四', username: 'lisi', succeeded: true, reason: null, ip: '10.0.0.32', location: '山东青岛', device_label: 'iPhone · Safari', login_time: '2026-01-06T08:58:00+08:00' },
];

// ---------- 操作日志 ----------

const auditLogs: AuditLogDetail[] = [
  { id: 'al-1', actor_id: 'u-admin', actor_name: '管理员', action_text: '停用了用户张三', module_key: 'system:user', module_label: '用户管理', succeeded: true, create_time: '2026-01-10T09:20:00+08:00', changes: [{ label: '状态', before: '启用', after: '停用' }] },
  { id: 'al-2', actor_id: 'u-admin', actor_name: '管理员', action_text: '新建了角色「运营专员」', module_key: 'system:role', module_label: '角色权限', succeeded: true, create_time: '2026-01-10T09:05:00+08:00', changes: [{ label: '名称', before: null, after: '运营专员' }] },
  { id: 'al-3', actor_id: 'u-zhangsan', actor_name: '张三', action_text: '修改了公告「国庆值班安排」', module_key: 'system:announcement', module_label: '公告管理', succeeded: true, create_time: '2026-01-09T17:32:00+08:00', changes: [{ label: '标题', before: '国庆安排', after: '国庆值班安排' }] },
  { id: 'al-4', actor_id: 'u-admin', actor_name: '管理员', action_text: '重置了用户李四的密码', module_key: 'system:user', module_label: '用户管理', succeeded: true, create_time: '2026-01-09T15:11:00+08:00', changes: [{ label: '密码', before: '***', after: '***' }] },
  { id: 'al-5', actor_id: 'u-admin', actor_name: '管理员', action_text: '删除了选项「测试分类」', module_key: 'system:dict', module_label: '选项管理', succeeded: false, create_time: '2026-01-09T11:40:00+08:00', changes: null, remark: '这个选项还有地方在用，删不掉' },
  { id: 'al-6', actor_id: 'u-lisi', actor_name: '李四', action_text: '上传了文件「报表.xlsx」', module_key: 'system:file', module_label: '文件管理', succeeded: true, create_time: '2026-01-08T14:20:00+08:00', changes: [{ label: '文件名', before: null, after: '报表.xlsx' }] },
  { id: 'al-7', actor_id: 'u-admin', actor_name: '管理员', action_text: '修改了菜单「帮助文档」', module_key: 'system:menu', module_label: '菜单管理', succeeded: true, create_time: '2026-01-08T10:02:00+08:00', changes: [{ label: '地址', before: 'https://old.example.com', after: 'https://docs.example.com' }] },
  { id: 'al-8', actor_id: 'u-admin', actor_name: '管理员', action_text: '给张三分配了角色「运营专员」', module_key: 'system:user', module_label: '用户管理', succeeded: true, create_time: '2026-01-07T16:48:00+08:00', changes: [{ label: '角色', before: '无', after: '运营专员' }] },
];

const auditModuleOptions = [
  { key: 'system:user', label: '用户管理' },
  { key: 'system:role', label: '角色权限' },
  { key: 'system:menu', label: '菜单管理' },
  { key: 'system:dict', label: '选项管理' },
  { key: 'system:file', label: '文件管理' },
  { key: 'system:announcement', label: '公告管理' },
  { key: 'system:plugin', label: '插件管理' },
];

// ---------- 定时任务 ----------

const scheduledTasks: ScheduledTask[] = [
  { code: 'backup', name: '数据备份', description: '每天夜里把数据完整备一份', source: { kind: 'system', label: '系统' }, schedule: { freq: 'daily', time: '03:00' }, schedule_locked: false, status: 'enabled', is_critical: true, last_run_time: '2026-01-10T03:00:00+08:00', last_run_ok: true, last_run_message: null, next_run_time: '2026-01-11T03:00:00+08:00' },
  { code: 'clean-logs', name: '清理过期日志', description: '把超过保留期的日志删掉', source: { kind: 'system', label: '系统' }, schedule: { freq: 'weekly', weekday: 1, time: '09:00' }, schedule_locked: false, status: 'enabled', is_critical: false, last_run_time: '2026-01-05T09:00:00+08:00', last_run_ok: false, last_run_message: '磁盘空间不足，清理没有做完', next_run_time: '2026-01-12T09:00:00+08:00' },
  { code: 'report-summary', name: '统计汇总', description: '每十分钟算一次汇总数据', source: { kind: 'plugin', label: '统计插件' }, schedule: { freq: 'interval', interval: 10 }, schedule_locked: false, status: 'enabled', is_critical: false, last_run_time: '2026-01-10T09:10:00+08:00', last_run_ok: true, last_run_message: null, next_run_time: '2026-01-10T09:20:00+08:00' },
  { code: 'system-check', name: '系统自检', description: '系统内部的例行检查，时间由系统定', source: { kind: 'system', label: '系统' }, schedule: null, schedule_locked: true, status: 'enabled', is_critical: true, last_run_time: '2026-01-10T08:00:00+08:00', last_run_ok: true, last_run_message: null, next_run_time: '2026-01-10T10:00:00+08:00' },
  { code: 'month-report', name: '月度报表', description: '每月末生成上月报表', source: { kind: 'plugin', label: '统计插件' }, schedule: { freq: 'monthly', monthday: 31, time: '06:00' }, schedule_locked: false, status: 'disabled', is_critical: false, last_run_time: '2025-12-31T06:00:00+08:00', last_run_ok: true, last_run_message: null, next_run_time: '2026-01-31T06:00:00+08:00' },
];

const scheduleRuns: Record<string, ScheduleRun[]> = {
  backup: [
    { id: 'run-b1', trigger: 'auto', state: 'succeeded', start_time: '2026-01-10T03:00:00+08:00', finish_time: '2026-01-10T03:04:12+08:00', message: null },
    { id: 'run-b2', trigger: 'manual', state: 'failed', start_time: '2026-01-09T15:20:00+08:00', finish_time: '2026-01-09T15:20:30+08:00', message: null, error: { code: 'BACKUP_IO', request_id: 'req-demo-1' } },
    { id: 'run-b3', trigger: 'auto', state: 'succeeded', start_time: '2026-01-09T03:00:00+08:00', finish_time: '2026-01-09T03:03:55+08:00', message: null },
  ],
  'clean-logs': [
    { id: 'run-c1', trigger: 'auto', state: 'failed', start_time: '2026-01-05T09:00:00+08:00', finish_time: '2026-01-05T09:00:12+08:00', message: '磁盘空间不足，清理没有做完' },
  ],
};

let runSeq = 100;

// ---------- 系统设置 ----------

const settingGroups: SettingGroup[] = [
  { key: 'basic', title: '基本信息' },
  { key: 'security', title: '登录与安全' },
  { key: 'users', title: '用户' },
  { key: 'watermark', title: '水印' },
];

const settingItems: Record<string, SettingItem[]> = {
  basic: [
    { key: 'site_name', label: '系统名字', type: 'text', value: 'SandAdmin', has_value: true, remark: '显示在左上角和浏览器标题上' },
    { key: 'site_logo', label: '系统标志', type: 'image', value: null, has_value: false, remark: '浅色和深色模式下都要看得清' },
    { key: 'timezone', label: '时区', type: 'select', value: 'Asia/Shanghai', has_value: true, default_value: 'Asia/Shanghai', options: [
      { value: 'Asia/Shanghai', label: '中国标准时间（UTC+8）' },
      { value: 'Asia/Tokyo', label: '东京时间（UTC+9）' },
      { value: 'UTC', label: '世界协调时间（UTC）' },
    ], remark: '影响后台显示的日期和时间' },
    { key: 'copyright', label: '版权信息', type: 'text', value: '© 2026 沙盒网络', has_value: true },
  ],
  security: [
    { key: 'session_days', label: '登录后保持多久', type: 'select', value: 7, has_value: true, default_value: 7, options: [
      { value: 1, label: '1 天' },
      { value: 7, label: '7 天' },
      { value: 30, label: '30 天' },
    ], remark: '超过这个时间就要重新登录' },
    { key: 'idle_minutes', label: '不操作多久后退出', type: 'select', value: 30, has_value: true, default_value: 30, options: [
      { value: 15, label: '15 分钟' },
      { value: 30, label: '30 分钟' },
      { value: 120, label: '2 小时' },
    ], remark: '清空后用默认值：30 分钟' },
    { key: 'captcha_after', label: '输错几次后出验证码', type: 'number', value: 3, has_value: true },
    { key: 'lock_minutes', label: '锁定时长（分钟）', type: 'number', value: 15, has_value: true },
    { key: 'secret_key', label: '内部密钥', type: 'text', value: null, has_value: true },
  ],
  users: [
    { key: 'default_role_id', label: '新用户默认角色', type: 'select', value: 'role-staff', has_value: true, options: [
      { value: 'role-staff', label: '普通员工' },
      { value: 'role-ops', label: '运营专员' },
    ], remark: '注册进来的人先给这个角色' },
  ],
  watermark: [
    { key: 'watermark_enabled', label: '在后台页面显示水印', type: 'boolean', value: true, has_value: true, remark: '打开后页面上会浅浅地印着当前用户' },
  ],
};

// ---------- 全局搜索 ----------

function searchUsers(keyword: string) {
  const kw = keyword.trim().toLowerCase();
  if (!kw) return [];
  return mockUsers
    .filter(
      (u) =>
        u.nickname.includes(kw) ||
        (u.username ?? '').toLowerCase().includes(kw) ||
        (u.dept_name ?? '').includes(kw),
    )
    .slice(0, 8)
    .map((u) => ({
      id: u.id,
      title: u.nickname,
      subtitle: [u.dept_name, u.post_id ? null : null].filter(Boolean).join(' · ') || (u.username ?? ''),
      link: `/system/user?kw=${encodeURIComponent(u.nickname)}`,
    }));
}

/** 菜单标题里的汉字 → 拼音首字母，本地「搜功能」用。 */
const PINYIN_INITIAL: Record<string, string> = {
  用: 'y', 户: 'h', 管: 'g', 理: 'l', 组: 'z', 织: 'z', 与: 'y', 部: 'b',
  门: 'm', 岗: 'g', 位: 'w', 角: 'j', 色: 's', 权: 'q', 限: 'x', 菜: 'c',
  单: 'd', 系: 'x', 统: 't', 设: 's', 置: 'z', 运: 'y', 行: 'x', 状: 'z',
  态: 't', 登: 'd', 录: 'l', 日: 'r', 志: 'z', 操: 'c', 作: 'z', 定: 'd',
  时: 's', 任: 'r', 务: 'w', 插: 'c', 件: 'j', 更: 'g', 新: 'x', 工: 'g',
  台: 't', 偏: 'p', 好: 'h', 文: 'w', 公: 'g', 告: 'g',
  选: 'x', 项: 'x', 数: 's', 据: 'j', 看: 'k', 板: 'b', 帮: 'b', 助: 'z',
  中: 'z', 心: 'x', 搜: 's', 索: 's', 审: 's', 批: 'p', 维: 'w', 护: 'h',
};

function initialsOf(text: string): string {
  return [...text].map((ch) => PINYIN_INITIAL[ch] ?? ch.toLowerCase()).join('');
}

function searchMenus(keyword: string) {
  const kw = keyword.trim().toLowerCase();
  if (!kw) return [];
  const matched: { id: string; title: string; subtitle: string | null; link: string }[] = [];
  const walk = (nodes: typeof mockMenuTree.items, groupTitle: string | null) => {
    for (const node of nodes) {
      if (node.children) {
        walk(node.children, node.title);
        continue;
      }
      if (node.type !== 'page' || !node.url?.startsWith('/')) continue;
      if (node.visible === false) continue;
      const haystacks = [node.title, groupTitle ?? ''];
      const initials = initialsOf(node.title);
      const hit =
        haystacks.some((h) => h.includes(keyword.trim())) ||
        initials.startsWith(kw) ||
        node.title.toLowerCase().includes(kw);
      if (hit) {
        matched.push({
          id: node.key,
          title: node.title,
          subtitle: groupTitle,
          link: node.url,
        });
      }
    }
  };
  walk(mockMenuTree.items, null);
  return matched;
}

// ---------- handlers ----------

export default [
  http.get(`${v1Base}/monitor/server`, ({ request }) => {
    const auth = requirePermission(request, 'system:monitor:index');
    if ('response' in auth) return auth.response;
    return okV1(serverStatus);
  }),

  http.get(`${v1Base}/login-logs`, ({ request }) => {
    const auth = requirePermission(request, 'system:login-log:index');
    if ('response' in auth) return auth.response;
    const url = new URL(request.url);
    const keyword = queryParam(url, 'keyword');
    const succeeded = queryParam(url, 'succeeded');
    const from = queryParam(url, 'login_time_from');
    const to = queryParam(url, 'login_time_to');
    const filtered = loginLogs
      .filter((l) => (keyword ? (l.nickname ?? '').includes(keyword) || (l.username ?? '').includes(keyword) : true))
      .filter((l) => (succeeded ? l.succeeded === (succeeded === 'true') : true))
      .filter((l) => (from ? l.login_time >= from : true))
      .filter((l) => (to ? l.login_time <= `${to}T23:59:59+08:00` : true));
    const { page, page_size } = queryPage(url);
    return okV1({
      items: filtered
        .slice((page - 1) * page_size, page * page_size)
        .map((l) => {
          // 列表不回传登录设备地址。
          const { ip, ...rest } = l;
          void ip;
          return rest;
        }),
      total: filtered.length,
      page,
      page_size,
    });
  }),

  http.get(`${v1Base}/login-logs/export`, ({ request }) => {
    const auth = requirePermission(request, 'system:login-log:index');
    if ('response' in auth) return auth.response;
    return okV1({ task_id: 'task-login-export', state: 'queued' });
  }),

  http.post(`${v1Base}/login-logs/export`, ({ request }) => {
    const auth = requirePermission(request, 'system:login-log:index');
    if ('response' in auth) return auth.response;
    return okV1({ task_id: 'task-login-export', state: 'queued' });
  }),

  http.get(`${v1Base}/login-logs/:logId`, ({ request, params }) => {
    const auth = requirePermission(request, 'system:login-log:index');
    if ('response' in auth) return auth.response;
    const found = loginLogs.find((l) => l.id === params.logId);
    if (!found) {
      return failV1(404, 'LOG_NOT_FOUND', '这条记录不存在或已过期');
    }
    return okV1(found);
  }),

  http.get(`${v1Base}/audit-logs/module-options`, ({ request }) => {
    const auth = requireUser(request);
    if ('response' in auth) return auth.response;
    return okV1({ items: auditModuleOptions });
  }),

  http.get(`${v1Base}/audit-logs`, ({ request }) => {
    const auth = requirePermission(request, 'system:audit-log:index');
    if ('response' in auth) return auth.response;
    const url = new URL(request.url);
    const keyword = queryParam(url, 'keyword');
    const actorId = queryParam(url, 'actor_id');
    const moduleKey = queryParam(url, 'module');
    const succeeded = queryParam(url, 'succeeded');
    const from = queryParam(url, 'create_time_from');
    const to = queryParam(url, 'create_time_to');
    const filtered = auditLogs
      .filter((l) => (keyword ? l.action_text.includes(keyword) : true))
      .filter((l) => (actorId ? l.actor_id === actorId : true))
      .filter((l) => (moduleKey ? l.module_key === moduleKey : true))
      .filter((l) => (succeeded ? l.succeeded === (succeeded === 'true') : true))
      .filter((l) => (from ? l.create_time >= from : true))
      .filter((l) => (to ? l.create_time <= `${to}T23:59:59+08:00` : true));
    const { page, page_size } = queryPage(url);
    return okV1({
      items: filtered
        .slice((page - 1) * page_size, page * page_size)
        .map((l) => {
          // 改动明细与补充说明只进详情。
          const { changes, remark, ...rest } = l;
          void changes;
          void remark;
          return rest;
        }),
      total: filtered.length,
      page,
      page_size,
    });
  }),

  http.post(`${v1Base}/audit-logs/export`, ({ request }) => {
    const auth = requirePermission(request, 'system:audit-log:index');
    if ('response' in auth) return auth.response;
    return okV1({ task_id: 'task-audit-export', state: 'queued' });
  }),

  http.get(`${v1Base}/audit-logs/:logId`, ({ request, params }) => {
    const auth = requirePermission(request, 'system:audit-log:index');
    if ('response' in auth) return auth.response;
    const found = auditLogs.find((l) => l.id === params.logId);
    if (!found) {
      return failV1(404, 'LOG_NOT_FOUND', '这条记录不存在或已过期');
    }
    return okV1(found);
  }),

  http.get(`${v1Base}/scheduled-tasks`, ({ request }) => {
    const auth = requirePermission(request, 'system:cron:index');
    if ('response' in auth) return auth.response;
    const url = new URL(request.url);
    const keyword = queryParam(url, 'keyword');
    const status = queryParam(url, 'status');
    const lastFailed = queryParam(url, 'last_failed') === 'true';
    const items = scheduledTasks
      .filter((t) => (keyword ? t.name.includes(keyword) : true))
      .filter((t) => (status ? t.status === status : true))
      .filter((t) => (lastFailed ? t.last_run_ok === false : true));
    return okV1({ items });
  }),

  http.put(`${v1Base}/scheduled-tasks/:code`, async ({ request, params }) => {
    const auth = requirePermission(request, 'system:cron:update');
    if ('response' in auth) return auth.response;
    const task = scheduledTasks.find((t) => t.code === params.code);
    if (!task) {
      return failV1(404, 'SCHEDULE_NOT_FOUND', '这个任务不存在');
    }
    const body = (await request.json()) as {
      status?: 'enabled' | 'disabled';
      schedule?: ScheduledTask['schedule'];
    };
    if (body.status) task.status = body.status;
    if (body.schedule) task.schedule = body.schedule;
    return okV1(task);
  }),

  http.post(`${v1Base}/scheduled-tasks/:code/run`, ({ request, params }) => {
    const auth = requirePermission(request, 'system:cron:run');
    if ('response' in auth) return auth.response;
    const task = scheduledTasks.find((t) => t.code === params.code);
    if (!task) {
      return failV1(404, 'SCHEDULE_NOT_FOUND', '这个任务不存在');
    }
    const runId = `run-${params.code as string}-${runSeq++}`;
    const runs = (scheduleRuns[task.code] ??= []);
    runs.unshift({
      id: runId,
      trigger: 'manual',
      state: 'succeeded',
      start_time: new Date().toISOString(),
      finish_time: new Date().toISOString(),
      message: null,
    });
    return okV1({ schedule_run_id: runId });
  }),

  http.get(`${v1Base}/scheduled-tasks/:code/runs`, ({ request, params }) => {
    const auth = requirePermission(request, 'system:cron:index');
    if ('response' in auth) return auth.response;
    const url = new URL(request.url);
    const { page, page_size } = queryPage(url);
    const runs = scheduleRuns[params.code as string] ?? [];
    return okV1({
      items: runs.slice((page - 1) * page_size, page * page_size),
      total: runs.length,
      page,
      page_size,
    });
  }),

  http.get(`${v1Base}/settings/groups`, ({ request }) => {
    const auth = requirePermission(request, 'system:config:index');
    if ('response' in auth) return auth.response;
    return okV1({ items: settingGroups });
  }),

  http.get(`${v1Base}/settings/:group`, ({ request, params }) => {
    const auth = requirePermission(request, 'system:config:index');
    if ('response' in auth) return auth.response;
    const items = (settingItems[params.group as string] ?? []).map((item) => {
      // has_value=true 但 value 为 null 的是密钥类：不回显明文。
      if (item.has_value && item.value === null) {
        return item;
      }
      return item;
    });
    return okV1({ items });
  }),

  http.put(`${v1Base}/settings/:group`, async ({ request, params }) => {
    const auth = requirePermission(request, 'system:config:update');
    if ('response' in auth) return auth.response;
    const group = params.group as string;
    const items = settingItems[group];
    if (!items) {
      return failV1(404, 'SETTING_GROUP_NOT_FOUND', '没有这个设置分组');
    }
    const body = (await request.json()) as {
      values: Record<string, string | number | boolean | null>;
    };
    for (const item of items) {
      if (item.key in (body.values ?? {})) {
        const next = body.values[item.key];
        item.value = next;
        item.has_value = next !== null;
      }
    }
    return okV1({ items });
  }),

  http.post(`${v1Base}/settings/logo`, ({ request }) => {
    const auth = requirePermission(request, 'system:config:update');
    if ('response' in auth) return auth.response;
    return okV1({ file_id: 'file-logo-1', url: '/mock-logo.png' });
  }),

  http.get(`${v1Base}/search`, ({ request }) => {
    const auth = requireUser(request);
    if ('response' in auth) return auth.response;
    const url = new URL(request.url);
    const keyword = queryParam(url, 'q');
    const types = queryParam(url, 'types').split(',').filter(Boolean);
    const groups: { type: string; label: string; items: unknown[] }[] = [];
    if (types.length === 0 || types.includes('user')) {
      groups.push({ type: 'user', label: '用户', items: searchUsers(keyword) });
    }
    if (types.length === 0 || types.includes('menu')) {
      groups.push({
        type: 'menu',
        label: '功能',
        items: searchMenus(keyword),
      });
    }
    return okV1({ groups });
  }),
];
