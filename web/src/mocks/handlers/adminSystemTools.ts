import { http } from 'msw';

import type { AuditLog } from '@/api/v1/auditLogs';
import type { LoginLogDetail } from '@/api/v1/loginLogs';
import type { ScheduledTask, TaskRun, TaskSchedule } from '@/api/v1/schedules';
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
import { registerMockFile } from './data';

/*
 * 监控与工具域 mock：/monitor/server、/login-logs、/audit-logs、
 * /scheduled-tasks、/settings、/search。字段形状对齐 v1 契约
 * （ServerStatus / LoginLog / AuditLog / ScheduledTask / TaskRun /
 * SettingItem / FileObject），数据是内存态，写操作在页面生命周期内保持。
 */

const T0 = '2026-01-10T08:00:00+08:00';

// ---------- 运行状态 ----------

const serverStatus = {
  collected_time: T0,
  server_time: T0,
  version: '1.2.0',
  db_ok: true,
  db_checked_time: T0,
  cpu: { usage_percent: 23, cores: 8 },
  memory: { usage_percent: 41, used_mb: 512, total_mb: 1248 },
  disk: { free_gb: 42, total_gb: 100 },
  process: { memory_mb: 512, uptime_seconds: 432000 },
};

// ---------- 登录日志 ----------

/* 内存里保留详情字段（ip/user_agent/user_id），列表时剥掉。 */
const loginLogs: LoginLogDetail[] = [
  { id: 'll-1', user_id: 'u-admin', username: 'admin', nickname: '管理员', succeeded: true, reason: null, reason_text: null, ip: '10.0.0.8', user_agent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120', location: '公司网络', device_label: 'Mac 上的 Chrome', login_time: '2026-01-10T09:12:00+08:00' },
  { id: 'll-2', user_id: 'u-zhangsan', username: 'zhangsan', nickname: '张三', succeeded: true, reason: null, reason_text: null, ip: '10.0.0.21', user_agent: 'Mozilla/5.0 (Windows NT 10.0) Edge/120', location: '山东青岛', device_label: 'Windows 上的 Edge', login_time: '2026-01-10T08:47:00+08:00' },
  { id: 'll-3', user_id: 'u-lisi', username: 'lisi', nickname: '李四', succeeded: false, reason: 'bad_password', reason_text: '密码不对', ip: '10.0.0.32', user_agent: 'Mozilla/5.0 (iPhone) Safari/17', location: '山东青岛', device_label: 'iPhone 上的 Safari', login_time: '2026-01-10T08:15:00+08:00' },
  { id: 'll-4', user_id: null, username: 'wangwu', nickname: null, succeeded: false, reason: 'user_not_found', reason_text: '没有这个账号', ip: '203.0.113.9', user_agent: 'Mozilla/5.0 (Windows NT 10.0) Chrome/119', location: '未知', device_label: 'Windows 上的 Chrome', login_time: '2026-01-09T22:03:00+08:00' },
  { id: 'll-5', user_id: 'u-admin', username: 'admin', nickname: '管理员', succeeded: true, reason: null, reason_text: null, ip: '10.0.0.8', user_agent: 'Mozilla/5.0 (Macintosh) Chrome/120', location: '公司网络', device_label: 'Mac 上的 Chrome', login_time: '2026-01-09T09:02:00+08:00' },
  { id: 'll-6', user_id: 'u-zhaoliu', username: 'zhaoliu', nickname: '赵六', succeeded: false, reason: 'disabled', reason_text: '账号已停用', ip: '10.0.0.55', user_agent: 'Mozilla/5.0 (Android 14) Chrome/120', location: '山东青岛', device_label: 'Android 上的 Chrome', login_time: '2026-01-09T08:30:00+08:00' },
  { id: 'll-7', user_id: 'u-zhangsan', username: 'zhangsan', nickname: '张三', succeeded: false, reason: 'rate_limited', reason_text: null, ip: '10.0.0.21', user_agent: 'Mozilla/5.0 (Windows NT 10.0) Edge/120', location: '山东青岛', device_label: 'Windows 上的 Edge', login_time: '2026-01-08T18:40:00+08:00' },
  { id: 'll-8', user_id: 'u-lisi', username: 'lisi', nickname: '李四', succeeded: true, reason: null, reason_text: null, ip: '10.0.0.32', user_agent: 'Mozilla/5.0 (iPhone) Safari/17', location: '山东青岛', device_label: 'iPhone 上的 Safari', login_time: '2026-01-08T09:00:00+08:00' },
  { id: 'll-9', user_id: 'u-admin', username: 'admin', nickname: '管理员', succeeded: true, reason: null, reason_text: null, ip: '10.0.0.8', user_agent: 'Mozilla/5.0 (Macintosh) Chrome/120', location: '公司网络', device_label: 'Mac 上的 Chrome', login_time: '2026-01-07T09:05:00+08:00' },
  { id: 'll-10', user_id: 'u-zhangsan', username: 'zhangsan', nickname: '张三', succeeded: true, reason: null, reason_text: null, ip: '10.0.0.21', user_agent: 'Mozilla/5.0 (Windows NT 10.0) Edge/120', location: '山东青岛', device_label: 'Windows 上的 Edge', login_time: '2026-01-07T08:55:00+08:00' },
  { id: 'll-11', user_id: 'u-admin', username: 'admin', nickname: '管理员', succeeded: true, reason: null, reason_text: null, ip: '10.0.0.8', user_agent: 'Mozilla/5.0 (Macintosh) Chrome/120', location: '公司网络', device_label: 'Mac 上的 Chrome', login_time: '2026-01-06T09:10:00+08:00' },
  { id: 'll-12', user_id: 'u-lisi', username: 'lisi', nickname: '李四', succeeded: true, reason: null, reason_text: null, ip: '10.0.0.32', user_agent: 'Mozilla/5.0 (iPhone) Safari/17', location: '山东青岛', device_label: 'iPhone 上的 Safari', login_time: '2026-01-06T08:58:00+08:00' },
];

function loginLogView(l: LoginLogDetail) {
  // 列表不回传 ip / user_agent / user_id——这些只进详情与导出。
  const { ip, user_agent, user_id, ...rest } = l;
  void ip;
  void user_agent;
  void user_id;
  return rest;
}

// ---------- 操作日志 ----------

const auditLogs: AuditLog[] = [
  {
    id: 'al-1', actor_type: 'user', actor_id: 'u-admin', actor_name: '管理员',
    module: 'system:user', module_label: '用户管理', action: 'user.disable',
    action_text: '停用了用户「张三」', target_type: 'user', target_id: 'u-zhangsan', target_label: '张三',
    succeeded: true,
    changes: { status: { before: '启用', after: '停用' } },
    change_labels: { status: '状态' },
    ip: '10.0.0.8', location: '公司网络', device_label: 'Mac 上的 Chrome',
    user_agent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120', request_id: 'req-audit-1', create_time: '2026-01-10T09:20:00+08:00',
  },
  {
    id: 'al-2', actor_type: 'user', actor_id: 'u-admin', actor_name: '管理员',
    module: 'system:role', module_label: '角色权限', action: 'role.create',
    action_text: '新建了角色「运营专员」', target_type: 'role', target_id: 'r-ops', target_label: '运营专员',
    succeeded: true,
    changes: { name: '运营专员' },
    change_labels: { name: '名称' },
    ip: '10.0.0.8', location: '公司网络', device_label: 'Mac 上的 Chrome',
    user_agent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120', request_id: 'req-audit-2', create_time: '2026-01-10T09:05:00+08:00',
  },
  {
    id: 'al-3', actor_type: 'user', actor_id: 'u-zhangsan', actor_name: '张三',
    module: 'system:announcement', module_label: '公告管理', action: 'announcement.update',
    action_text: '修改了公告「国庆值班安排」', target_type: 'announcement', target_id: 'a-3', target_label: '国庆值班安排',
    succeeded: true,
    changes: { title: { before: '国庆安排', after: '国庆值班安排' } },
    change_labels: { title: '标题' },
    ip: '10.0.0.21', location: '山东青岛', device_label: 'Windows 上的 Edge',
    user_agent: 'Mozilla/5.0 (Windows NT 10.0) Edge/120', request_id: 'req-audit-3', create_time: '2026-01-09T17:32:00+08:00',
  },
  {
    id: 'al-4', actor_type: 'user', actor_id: 'u-admin', actor_name: '管理员',
    module: 'system:user', module_label: '用户管理', action: 'user.reset_password',
    action_text: '重置了用户「李四」的密码', target_type: 'user', target_id: 'u-lisi', target_label: '李四',
    succeeded: true,
    // 口令类值后端直接给「已修改」标记，页面只回显。
    changes: { password: '已修改' },
    change_labels: { password: '密码' },
    ip: '10.0.0.8', location: '公司网络', device_label: 'Mac 上的 Chrome',
    user_agent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120', request_id: 'req-audit-4', create_time: '2026-01-09T15:11:00+08:00',
  },
  {
    id: 'al-5', actor_type: 'user', actor_id: 'u-admin', actor_name: '管理员',
    module: 'system:dict', module_label: '选项管理', action: 'dict.delete',
    action_text: '删除了选项「测试分类」：这个选项还有地方在用，删不掉',
    target_type: 'dict', target_id: 'd-9', target_label: '测试分类',
    succeeded: false, changes: null, change_labels: null,
    ip: '10.0.0.8', location: '公司网络', device_label: 'Mac 上的 Chrome',
    user_agent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120', request_id: 'req-audit-5', create_time: '2026-01-09T11:40:00+08:00',
  },
  {
    id: 'al-6', actor_type: 'user', actor_id: 'u-lisi', actor_name: '李四',
    module: 'system:file', module_label: '文件管理', action: 'file.upload',
    action_text: '上传了文件「报表.xlsx」', target_type: 'file', target_id: 'file-007', target_label: '报表.xlsx',
    succeeded: true,
    changes: { original_name: '报表.xlsx' },
    change_labels: { original_name: '文件名' },
    ip: '10.0.0.32', location: '山东青岛', device_label: 'iPhone 上的 Safari',
    user_agent: 'Mozilla/5.0 (iPhone) Safari/17', request_id: 'req-audit-6', create_time: '2026-01-08T14:20:00+08:00',
  },
  {
    id: 'al-7', actor_type: 'user', actor_id: 'u-admin', actor_name: '管理员',
    module: 'system:menu', module_label: '菜单管理', action: 'menu.update',
    action_text: '修改了菜单「帮助文档」', target_type: 'menu', target_id: 'm-help', target_label: '帮助文档',
    succeeded: true,
    changes: { url: { before: 'https://old.example.com', after: 'https://docs.example.com' } },
    change_labels: { url: '地址' },
    ip: '10.0.0.8', location: '公司网络', device_label: 'Mac 上的 Chrome',
    user_agent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120', request_id: 'req-audit-7', create_time: '2026-01-08T10:02:00+08:00',
  },
  {
    id: 'al-8', actor_type: 'user', actor_id: 'u-admin', actor_name: '管理员',
    module: 'system:user', module_label: '用户管理', action: 'user.assign_role',
    action_text: '给「张三」分配了角色「运营专员」', target_type: 'user', target_id: 'u-zhangsan', target_label: '张三',
    succeeded: true,
    changes: { role: { before: '无', after: '运营专员' } },
    change_labels: { role: '角色' },
    ip: '10.0.0.8', location: '公司网络', device_label: 'Mac 上的 Chrome',
    user_agent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120', request_id: 'req-audit-8', create_time: '2026-01-07T16:48:00+08:00',
  },
];

const auditModuleOptions = [
  { key: 'system:user', name: '用户管理' },
  { key: 'system:role', name: '角色权限' },
  { key: 'system:menu', name: '菜单管理' },
  { key: 'system:dict', name: '选项管理' },
  { key: 'system:file', name: '文件管理' },
  { key: 'system:announcement', name: '公告管理' },
  { key: 'system:plugin', name: '插件管理' },
];

// ---------- 定时任务 ----------

/* 清单默认时间：schedule 传 null 恢复默认时回写到这份结构上。 */
const manifestSchedules: Record<string, TaskSchedule> = {
  backup: { freq: 'daily', time: '03:00' },
  'clean-logs': { freq: 'weekly', weekday: 1, time: '09:00' },
  'report-summary': { freq: 'interval', interval: 10 },
  'system-check': { freq: 'hourly', time: '00:00' },
  'month-report': { freq: 'monthly', monthday: 31, time: '06:00' },
};

const scheduledTasks: ScheduledTask[] = [
  {
    code: 'backup', name: '数据备份', description: '每天夜里把数据完整备一份',
    source_label: '系统', is_critical: true, plugin_code: null,
    schedule: { freq: 'daily', time: '03:00' },
    schedule_overridden: false, schedule_locked: false,
    status: 'enabled',
    last_run_time: '2026-01-10T03:00:00+08:00', last_run_succeeded: true,
    next_run_time: '2026-01-11T03:00:00+08:00',
    next_run_times: ['2026-01-11T03:00:00+08:00', '2026-01-12T03:00:00+08:00', '2026-01-13T03:00:00+08:00'],
  },
  {
    code: 'clean-logs', name: '清理过期日志', description: '把超过保留期的日志删掉',
    source_label: '系统', is_critical: false, plugin_code: null,
    schedule: { freq: 'weekly', weekday: 1, time: '09:00' },
    schedule_overridden: false, schedule_locked: false,
    status: 'enabled',
    last_run_time: '2026-01-05T09:00:00+08:00', last_run_succeeded: false,
    next_run_time: '2026-01-12T09:00:00+08:00',
    next_run_times: ['2026-01-12T09:00:00+08:00', '2026-01-19T09:00:00+08:00', '2026-01-26T09:00:00+08:00'],
  },
  {
    code: 'report-summary', name: '统计汇总', description: '每十分钟算一次汇总数据',
    source_label: '统计插件', is_critical: false, plugin_code: 'stats',
    schedule: { freq: 'interval', interval: 10 },
    schedule_overridden: true, schedule_locked: false,
    status: 'enabled',
    last_run_time: '2026-01-10T09:10:00+08:00', last_run_succeeded: true,
    next_run_time: '2026-01-10T09:20:00+08:00',
    next_run_times: ['2026-01-10T09:20:00+08:00', '2026-01-10T09:30:00+08:00', '2026-01-10T09:40:00+08:00'],
  },
  {
    code: 'system-check', name: '系统自检', description: '系统内部的例行检查，时间由系统定',
    source_label: '系统', is_critical: true, plugin_code: null,
    schedule: { freq: 'hourly', time: '00:00' },
    schedule_overridden: false, schedule_locked: true,
    status: 'enabled',
    last_run_time: '2026-01-10T08:00:00+08:00', last_run_succeeded: true,
    next_run_time: '2026-01-10T10:00:00+08:00',
    next_run_times: ['2026-01-10T10:00:00+08:00', '2026-01-10T11:00:00+08:00', '2026-01-10T12:00:00+08:00'],
  },
  {
    code: 'month-report', name: '月度报表', description: '每月末生成上月报表',
    source_label: '统计插件', is_critical: false, plugin_code: 'stats',
    schedule: { freq: 'monthly', monthday: 31, time: '06:00' },
    schedule_overridden: false, schedule_locked: false,
    status: 'disabled',
    last_run_time: '2025-12-31T06:00:00+08:00', last_run_succeeded: true,
    next_run_time: '2026-01-31T06:00:00+08:00',
    next_run_times: [],
  },
];

const scheduleRuns: Record<string, TaskRun[]> = {
  backup: [
    { id: 'run-b1', schedule_code: 'backup', trigger: 'schedule', status: 'succeeded', duration_ms: 252000, message: null, error_code: null, request_id: null, start_time: '2026-01-10T03:00:00+08:00', finish_time: '2026-01-10T03:04:12+08:00' },
    { id: 'run-b2', schedule_code: 'backup', trigger: 'manual', status: 'failed', duration_ms: 30000, message: null, error_code: 'BACKUP_IO', request_id: 'req-demo-1', start_time: '2026-01-09T15:20:00+08:00', finish_time: '2026-01-09T15:20:30+08:00' },
    { id: 'run-b3', schedule_code: 'backup', trigger: 'schedule', status: 'succeeded', duration_ms: 235000, message: null, error_code: null, request_id: null, start_time: '2026-01-09T03:00:00+08:00', finish_time: '2026-01-09T03:03:55+08:00' },
  ],
  'clean-logs': [
    { id: 'run-c1', schedule_code: 'clean-logs', trigger: 'schedule', status: 'failed', duration_ms: 12000, message: '磁盘空间不足，清理没有做完', error_code: null, request_id: 'req-demo-2', start_time: '2026-01-05T09:00:00+08:00', finish_time: '2026-01-05T09:00:12+08:00' },
  ],
};

let runSeq = 100;
let runStartSeq = 100;

/** 定时任务时间结构校验：不合格回 422 TASK_SCHEDULE_INVALID + fields。 */
function scheduleFieldErrors(schedule: TaskSchedule): Record<string, string[]> {
  const fields: Record<string, string[]> = {};
  const push = (key: string, msg: string) => {
    fields[key] = [...(fields[key] ?? []), msg];
  };
  if (!['interval', 'hourly', 'daily', 'weekly', 'monthly'].includes(schedule.freq)) {
    push('schedule.freq', '不认识的时间类型');
    return fields;
  }
  if (schedule.freq === 'interval') {
    const minutes = schedule.interval;
    if (typeof minutes !== 'number' || minutes < 5 || minutes > 60) {
      push('schedule.interval', '间隔要在 5 到 60 分钟之间');
    }
    return fields;
  }
  if (schedule.freq === 'hourly') {
    const match = /^(\d{2}):(\d{2})$/.exec(schedule.time ?? '');
    if (!match || Number(match[2]) > 59) push('schedule.time', '分钟数要在 0 到 59 之间');
  }
  if (schedule.freq === 'daily' || schedule.freq === 'weekly' || schedule.freq === 'monthly') {
    const match = /^(\d{2}):(\d{2})$/.exec(schedule.time ?? '');
    if (!match || Number(match[1]) > 23 || Number(match[2]) > 59) {
      push('schedule.time', '时间要写成「几点几分」');
    }
  }
  if (schedule.freq === 'weekly') {
    const day = schedule.weekday;
    if (typeof day !== 'number' || day < 1 || day > 7) push('schedule.weekday', '星期要在一到日之间');
  }
  if (schedule.freq === 'monthly') {
    const day = schedule.monthday;
    if (typeof day !== 'number' || day < 1 || day > 31) push('schedule.monthday', '日期要在 1 到 31 之间');
  }
  return fields;
}

// ---------- 系统设置 ----------

const settingGroups: SettingGroup[] = [
  { key: 'basic', title: '基本信息', owner: 'host' },
  { key: 'login', title: '登录与安全', owner: 'host' },
  { key: 'users', title: '用户', owner: 'host' },
  { key: 'watermark', title: '水印', owner: 'host' },
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
  login: [
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
    { key: 'secret_key', label: '内部密钥', type: 'password', value: null, has_value: true },
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

let logoSeq = 0;

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
      subtitle: u.dept_name || (u.username ?? ''),
      // UsersPage 的 URL 筛选字段是 keyword。
      link: `/system/user?keyword=${encodeURIComponent(u.username ?? u.nickname)}`,
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
      .filter((l) => (keyword ? (l.nickname ?? '').includes(keyword) || l.username.includes(keyword) : true))
      .filter((l) => (succeeded ? l.succeeded === (succeeded === 'true') : true))
      .filter((l) => (from ? l.login_time >= from : true))
      .filter((l) => (to ? l.login_time <= `${to}T23:59:59+08:00` : true));
    const { page, page_size } = queryPage(url);
    return okV1({
      items: filtered.slice((page - 1) * page_size, page * page_size).map(loginLogView),
      total: filtered.length,
      page,
      page_size,
    });
  }),

  // 契约只定义 POST；导出走 task_id 轮询 /tasks。
  http.post(`${v1Base}/login-logs/export`, ({ request }) => {
    const auth = requirePermission(request, 'system:login-log:export');
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
    const auth = requirePermission(request, 'system:audit-log:index');
    if ('response' in auth) return auth.response;
    // 契约：data 直接是数组。
    return okV1(auditModuleOptions);
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
      .filter((l) => (moduleKey ? l.module === moduleKey : true))
      .filter((l) => (succeeded ? l.succeeded === (succeeded === 'true') : true))
      .filter((l) => (from ? l.create_time >= from : true))
      .filter((l) => (to ? l.create_time <= `${to}T23:59:59+08:00` : true));
    const { page, page_size } = queryPage(url);
    return okV1({
      items: filtered.slice((page - 1) * page_size, page * page_size),
      total: filtered.length,
      page,
      page_size,
    });
  }),

  http.post(`${v1Base}/audit-logs/export`, ({ request }) => {
    const auth = requirePermission(request, 'system:audit-log:export');
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
    const failedOnly = queryParam(url, 'failed_only') === 'true';
    const items = scheduledTasks
      .filter((t) => (keyword ? t.name.includes(keyword) : true))
      .filter((t) => (status ? t.status === status : true))
      .filter((t) => (failedOnly ? t.last_run_succeeded === false : true));
    return okV1({ items, timezone: 'Asia/Shanghai', timezone_label: '北京时间（东八区）' });
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
      schedule?: TaskSchedule | null;
    };
    // 清单锁定的任务改时间 → 409；只改启停不受影响。
    if (task.schedule_locked && 'schedule' in body) {
      return failV1(409, 'TASK_SCHEDULE_LOCKED', '这个任务的时间由系统定，不能改');
    }
    if ('schedule' in body) {
      if (body.schedule === null) {
        // 传 null = 恢复清单默认。
        task.schedule = manifestSchedules[task.code] ?? task.schedule;
        task.schedule_overridden = false;
      } else if (body.schedule) {
        const fields = scheduleFieldErrors(body.schedule);
        if (Object.keys(fields).length > 0) {
          return failV1(422, 'TASK_SCHEDULE_INVALID', '执行时间的写法有问题', fields);
        }
        task.schedule = body.schedule;
        task.schedule_overridden = true;
      }
    }
    if (body.status) task.status = body.status;
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
    const start = new Date().toISOString();
    const runs = (scheduleRuns[task.code] ??= []);
    runs.unshift({
      id: runId,
      schedule_code: task.code,
      trigger: 'manual',
      status: 'succeeded',
      duration_ms: 800,
      message: null,
      error_code: null,
      request_id: `req-run-${runStartSeq++}`,
      start_time: start,
      finish_time: start,
    });
    task.last_run_time = start;
    task.last_run_succeeded = true;
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
    const group = params.group as string;
    const items = settingItems[group];
    if (!items) {
      return failV1(404, 'SETTING_GROUP_NOT_FOUND', '没有这个设置分组');
    }
    return okV1({ group, items });
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
    const known = new Set(items.map((item) => item.key));
    const unknown = Object.keys(body.values ?? {}).filter((key) => !known.has(key));
    if (unknown.length > 0) {
      return failV1(422, 'SETTING_KEY_UNKNOWN', '有设置项不存在', {
        values: unknown.map((key) => `不认识的设置项：${key}`),
      });
    }
    for (const item of items) {
      if (item.key in (body.values ?? {})) {
        const next = body.values[item.key];
        item.value = next;
        item.has_value = next !== null;
      }
    }
    return okV1({ group, items });
  }),

  http.post(`${v1Base}/settings/logo`, ({ request }) => {
    const auth = requirePermission(request, 'system:config:update');
    if ('response' in auth) return auth.response;
    // 契约回 FileObject：id 写进 site_logo，预览走 /files/{id}/download。
    logoSeq += 1;
    const file = {
      id: `file-logo-${logoSeq}`,
      original_name: 'site-logo.png',
      kind: 'image' as const,
      media_type: 'image/png',
      size_bytes: 2048,
      usage_state: 'used' as const,
      usage_label: '系统标志',
      visibility: 'public' as const,
      purpose: 'attachment' as const,
      uploader_id: 'u-admin',
      uploader_name: '管理员',
      create_time: new Date().toISOString(),
    };
    registerMockFile(file);
    return okV1(file);
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
