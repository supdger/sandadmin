import { http, type HttpHandler } from 'msw';

import { mockUserForToken, type MockUser } from './auth';
import { announcementMarkRead, announcementReadTime, inboxAnnouncements } from './data';
import { failV1, okV1, v1Base } from './_v1';

const base = v1Base;

// Shared helpers

function bearerToken(request: Request): string {
  return (request.headers.get('authorization') ?? '').replace(/^Bearer\s+/i, '');
}

function requireUser(request: Request): { token: string; user: MockUser } | null {
  const token = bearerToken(request);
  const user = mockUserForToken(token);
  return user ? { token, user } : null;
}

function unauthorized() {
  return failV1(401, 'UNAUTHORIZED', '登录已过期，请重新登录');
}

function hasPermission(user: MockUser, code: string): boolean {
  return user.permissions.includes('*') || user.permissions.includes(code);
}

function iso(daysAgo: number, hour = 10, minute = 0): string {
  const date = new Date();
  date.setDate(date.getDate() - daysAgo);
  date.setHours(hour, minute, 0, 0);
  return date.toISOString();
}

// /me/profile + avatar upload + password endpoints

interface ProfileState {
  nickname: string;
  phone: string;
  email: string | null;
  avatar_file_id: string | null;
  password_changed_time: string;
}

const PROFILE_DEFAULTS: Record<string, Omit<ProfileState, 'password_changed_time'>> = {
  admin: {
    nickname: '管理员',
    phone: '13800000001',
    email: 'admin@example.com',
    avatar_file_id: null,
  },
  newbie: {
    nickname: '新同事',
    phone: '13800000002',
    email: 'newbie@example.com',
    avatar_file_id: null,
  },
  viewer: {
    nickname: '只读用户',
    phone: '13800000003',
    email: 'viewer@example.com',
    avatar_file_id: null,
  },
  guest: {
    nickname: '访客',
    phone: '13800000004',
    email: null,
    avatar_file_id: null,
  },
};

const profiles = new Map<string, ProfileState>();

function profileFor(user: MockUser): ProfileState {
  const key = user.username;
  let state = profiles.get(key);
  if (!state) {
    const defaults = PROFILE_DEFAULTS[key] ?? {
      nickname: user.nickname,
      phone: '',
      email: null,
      avatar_file_id: null,
    };
    state = { ...defaults, password_changed_time: iso(30) };
    profiles.set(key, state);
  }
  return state;
}

function profilePayload(user: MockUser, state: ProfileState) {
  return {
    id: `user-${user.username}`,
    username: user.username,
    nickname: state.nickname,
    avatar: null,
    avatar_file_id: state.avatar_file_id,
    phone: state.phone,
    email: state.email,
    // 只读的组织上下文：部门/岗位/角色名；不含 auth/identity/wallet 字段。
    dept_name: user.dept_name ?? null,
    post_name: user.post_name ?? null,
    role_names: user.role_names ?? [],
    password_changed_time: state.password_changed_time,
  };
}

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

// Sessions — per-user list, current device pinned to the first entry

interface SessionEntry {
  id: string;
  device_label: string | null;
  location: string | null;
  ip: string | null;
  user_agent: string | null;
  create_time: string;
  last_active_time: string;
  expire_time: string | null;
  current: boolean;
}

const sessionsByUser = new Map<string, SessionEntry[]>();

function sessionsFor(user: MockUser): SessionEntry[] {
  let list = sessionsByUser.get(user.username);
  if (!list) {
    const devices: Array<Omit<SessionEntry, 'current' | 'id'>> = [
      {
        device_label: 'MacBook Pro · Chrome',
        location: '上海 · 内部网络',
        ip: '192.0.2.21',
        user_agent: 'Mozilla/5.0 (Macintosh) Chrome/130',
        create_time: iso(12),
        last_active_time: iso(0, 9, 12),
        expire_time: null,
      },
      {
        device_label: 'iPhone · Safari',
        location: '上海 · 内部网络',
        ip: '192.0.2.35',
        user_agent: 'Mozilla/5.0 (iPhone) Safari/17',
        create_time: iso(5),
        last_active_time: iso(0, 8, 40),
        expire_time: null,
      },
      {
        device_label: 'Windows 台式机 · Edge',
        location: '北京 · 内部网络',
        ip: '198.51.100.8',
        user_agent: 'Mozilla/5.0 (Windows) Edge/130',
        create_time: iso(20),
        last_active_time: iso(1, 18, 5),
        expire_time: null,
      },
      {
        device_label: 'iPad · Safari',
        location: '广州 · 外网',
        ip: '203.0.113.9',
        user_agent: 'Mozilla/5.0 (iPad) Safari/17',
        create_time: iso(30),
        last_active_time: iso(3, 11, 22),
        expire_time: iso(-30, 0, 0),
      },
    ];
    list = devices.map((d, i) => ({
      ...d,
      id: `sess-${user.username}-${i + 1}`,
      current: i === 0,
    }));
    sessionsByUser.set(user.username, list);
  }
  return list;
}

// Shortcuts — per-user manual override; default is the auto list

const AUTO_SHORTCUTS = [
  { menu_key: 'workbench', title: '工作台', icon: 'home', link: '/' },
  { menu_key: 'preferences', title: '偏好设置', icon: 'setting', link: '/preferences' },
];

const shortcutOverrides = new Map<string, string[]>();

// Notifications — seeded mixed inbox, read state kept per user

interface NotificationSeed {
  kind: 'notification' | 'announcement';
  source_code: string | null;
  text: string;
  title: string | null;
  announcement_id: string | null;
  link: string | null;
  create_time: string;
}

const NOTIFICATION_SEEDS: NotificationSeed[] = [
  { kind: 'notification', source_code: 'system', text: '你的密码已在另一台设备上修改，其余登录已退出', title: null, announcement_id: null, link: '/profile?tab=security', create_time: iso(0, 8, 0) },
  { kind: 'notification', source_code: 'system', text: '系统将于本周日凌晨 2:00 至 4:00 进行维护', title: null, announcement_id: null, link: null, create_time: iso(0, 9, 30) },
  { kind: 'notification', source_code: 'sand-ai', text: '‘AI 助手’插件已升级到 1.2.0', title: null, announcement_id: null, link: null, create_time: iso(1, 14, 10) },
  { kind: 'notification', source_code: 'workflow', text: '你有 1 条流程审批等你处理', title: null, announcement_id: null, link: '/todos', create_time: iso(1, 16, 45) },
  { kind: 'notification', source_code: 'system', text: '新用户「李四」已完成注册', title: null, announcement_id: null, link: null, create_time: iso(2, 10, 5) },
  { kind: 'notification', source_code: 'system', text: '「运营部」本月考勤已汇总完成', title: null, announcement_id: null, link: null, create_time: iso(2, 15, 20) },
  { kind: 'notification', source_code: 'system', text: '你的登录密码将在 7 天后到期，请及时修改', title: null, announcement_id: null, link: '/profile?tab=security', create_time: iso(3, 9, 0) },
  { kind: 'notification', source_code: 'system', text: '管理员重置了你的登录密码', title: null, announcement_id: null, link: '/profile?tab=security', create_time: iso(4, 11, 30) },
  { kind: 'notification', source_code: 'system', text: '「选项管理」模块数据已完成本周备份', title: null, announcement_id: null, link: null, create_time: iso(5, 8, 15) },
  { kind: 'notification', source_code: 'system', text: '新版本 v1.4.0 已发布，包含性能优化', title: null, announcement_id: null, link: null, create_time: iso(6, 17, 40) },
  { kind: 'notification', source_code: 'system', text: '「王五」提交了请假申请，等待审批', title: null, announcement_id: null, link: '/todos', create_time: iso(7, 13, 25) },
  { kind: 'notification', source_code: 'system', text: '系统检测到一次异常登录尝试，已记录日志', title: null, announcement_id: null, link: null, create_time: iso(8, 23, 50) },
  { kind: 'notification', source_code: 'system', text: '「客服部」团建报名截止时间为下周五', title: null, announcement_id: null, link: null, create_time: iso(9, 10, 0) },
  { kind: 'notification', source_code: 'system', text: '季度安全培训材料已更新，请查收', title: null, announcement_id: null, link: null, create_time: iso(10, 14, 35) },
  { kind: 'notification', source_code: 'system', text: '「新员工培训」课程表已发布', title: null, announcement_id: null, link: null, create_time: iso(12, 9, 20) },
  { kind: 'notification', source_code: 'system', text: '上个月的系统使用报告已生成', title: null, announcement_id: null, link: null, create_time: iso(15, 16, 10) },
  { kind: 'notification', source_code: 'system', text: '「赵六」的账号已停用', title: null, announcement_id: null, link: null, create_time: iso(18, 11, 45) },
  { kind: 'notification', source_code: 'system', text: '存储空间使用率达到 70%，请关注', title: null, announcement_id: null, link: null, create_time: iso(20, 8, 30) },
  { kind: 'notification', source_code: 'system', text: '年度信息安全考试即将开始', title: null, announcement_id: null, link: null, create_time: iso(25, 15, 55) },
  { kind: 'notification', source_code: 'system', text: '「周报模板」已更新到最新版本', title: null, announcement_id: null, link: null, create_time: iso(30, 10, 25) },
];

interface InboxItem extends NotificationSeed {
  id: string;
  kind: 'notification' | 'announcement';
  announcement_id: string | null;
  read_time: string | null;
  /** 公告置顶标记；通知条目无此值。 */
  pinned?: boolean;
}

function inboxFor(user: MockUser): InboxItem[] {
  const items: InboxItem[] = NOTIFICATION_SEEDS.map((seed, i) => ({
    id: `ntf-${String(i + 1).padStart(3, '0')}`,
    kind: seed.kind,
    source_code: seed.source_code,
    text: seed.text,
    title: seed.title,
    announcement_id: seed.announcement_id,
    link: seed.link,
    create_time: seed.create_time,
    read_time: null,
  }));
  for (const ann of inboxAnnouncements(user)) {
    items.push({
      id: ann.id,
      kind: 'announcement',
      source_code: null,
      text: ann.title,
      title: ann.title,
      announcement_id: ann.id,
      link: null,
      create_time: ann.published_time,
      read_time: null,
      pinned: ann.pinned,
    });
  }
  // Pinned announcements float to the top, then newest first.
  items.sort((a, b) => {
    if (Boolean(a.pinned) !== Boolean(b.pinned)) {
      return a.pinned ? -1 : 1;
    }
    return b.create_time.localeCompare(a.create_time);
  });
  return items;
}

const readNotifications = new Map<string, Set<string>>();

function readSet(map: Map<string, Set<string>>, key: string): Set<string> {
  let set = map.get(key);
  if (!set) {
    set = new Set();
    map.set(key, set);
  }
  return set;
}

// Dashboard payloads — permission-filtered

function dashboardTodos(user: MockUser) {
  const items = [
    {
      source_key: 'workflow',
      source_label: '审批',
      title: '「请假申请」等待你审批',
      create_time: iso(1, 9, 0),
      overdue: false,
      link: '/todos',
    },
    {
      source_key: 'iam',
      source_label: '应用',
      title: '‘移动报销’申请了更多权限，待你同意',
      create_time: iso(2, 11, 20),
      overdue: true,
      link: '/todos',
    },
  ];
  if (hasPermission(user, 'system:user:index')) {
    items.unshift({
      source_key: 'system',
      source_label: '系统',
      title: '有 2 个账号待激活',
      create_time: iso(0, 8, 30),
      overdue: false,
      link: '/todos',
    });
  }
  return { items, total: items.length };
}

function dashboardSummary(user: MockUser) {
  const cards = [];
  if (hasPermission(user, 'system:user:index')) {
    cards.push({
      key: 'users',
      value: 128,
      unit: '人',
      sub: { text: '本月新增 6 人', tone: 'plain' },
      link: '/permission/user',
      error: null,
    });
  }
  if (hasPermission(user, 'system:login-log:index')) {
    cards.push({
      key: 'logins_today',
      value: 36,
      unit: '人',
      sub: { text: '比昨天多 4 人', tone: 'up' },
      link: '/system/login-log',
      error: null,
    });
  }
  if (hasPermission(user, 'sand_ai:record:index')) {
    cards.push({
      key: 'ai_usage_month',
      value: 2340,
      unit: '次',
      sub: { text: '比上月多 12%', tone: 'up' },
      link: '/plugin/sand-ai/records',
      error: null,
    });
  }
  if (hasPermission(user, 'workflow:todo:index')) {
    cards.push({
      key: 'workflow_pending',
      value: 5,
      unit: '件',
      sub: { text: '2 件已超时', tone: 'warning' },
      link: '/workflow/todo',
      error: null,
    });
  }
  return { cards };
}

function dashboardCharts(range: string, user: MockUser) {
  const days = range === '7d' ? 7 : range === '90d' ? 90 : 30;
  const charts = [];
  if (hasPermission(user, 'system:login-log:index')) {
    charts.push({
      key: 'login_trend',
      unit: '人',
      points: Array.from({ length: days }, (_, i) => ({
        day: new Date(Date.now() - (days - 1 - i) * 86_400_000).toISOString().slice(0, 10),
        value: 20 + ((i * 7) % 18),
      })),
    });
  }
  if (hasPermission(user, 'sand_ai:record:index')) {
    charts.push({
      key: 'ai_usage',
      unit: '次',
      points: Array.from({ length: days }, (_, i) => ({
        day: new Date(Date.now() - (days - 1 - i) * 86_400_000).toISOString().slice(0, 10),
        value: 40 + ((i * 13) % 60),
      })),
    });
  }
  return { charts };
}

// Handlers

export default [
  // 个人资料
  http.get(`${base}/me/profile`, ({ request }) => {
    const auth = requireUser(request);
    if (!auth) return unauthorized();
    return okV1(profilePayload(auth.user, profileFor(auth.user)));
  }),

  http.put(`${base}/me/profile`, async ({ request }) => {
    const auth = requireUser(request);
    if (!auth) return unauthorized();
    const body = (await request.json()) as {
      nickname?: string;
      phone?: string;
      email?: string | null;
      avatar_file_id?: string | null;
    };
    const nickname = (body.nickname ?? '').trim();
    const phone = body.phone ?? '';
    const email = body.email ?? null;
    const fields: Record<string, string[]> = {};
    if (!nickname) {
      fields.nickname = ['请填写姓名'];
    } else if (nickname.length > 20) {
      fields.nickname = ['姓名最多 20 个字'];
    }
    if (!/^\d{11}$/.test(phone)) {
      fields.phone = ['手机号要 11 位数字，请检查后再填'];
    } else {
      // Duplicate phone across the other demo accounts.
      const taken = Object.entries(PROFILE_DEFAULTS).some(
        ([name, p]) => name !== auth.user.username && p.phone === phone,
      );
      const current = profileFor(auth.user);
      if (taken || Object.values(profiles).some((p) => p.phone === phone && p !== current)) {
        fields.phone = ['这个手机号已经被别人用了'];
      }
    }
    if (email !== null && email !== '' && !EMAIL_RE.test(email)) {
      fields.email = ['邮箱格式不对，例如 name@company.com'];
    }
    if (Object.keys(fields).length > 0) {
      return failV1(422, 'VALIDATION_FAILED', '提交的内容没有通过检查', fields);
    }
    const state = profileFor(auth.user);
    state.nickname = nickname;
    state.phone = phone;
    state.email = email === '' ? null : email;
    if (body.avatar_file_id !== undefined) {
      state.avatar_file_id = body.avatar_file_id;
    }
    return okV1(profilePayload(auth.user, state));
  }),

  // 上传走统一 `/files`（data.ts 文件域 handler），头像校验在前端做。

  // 密码
  http.get(`${base}/auth/password-policy`, ({ request }) => {
    const auth = requireUser(request);
    if (!auth) return unauthorized();
    const isAdminTier = auth.user.is_super === true || auth.user.is_admin === true;
    return okV1({
      tier: isAdminTier ? 'admin' : 'normal',
      min_length: isAdminTier ? 10 : 8,
      require_letter: true,
      require_digit: true,
      forbid_weak: true,
      forbid_same_as_username: true,
      forbid_same_as_previous: 3,
    });
  }),

  http.post(`${base}/auth/password/change`, async ({ request }) => {
    const auth = requireUser(request);
    if (!auth) return unauthorized();
    const body = (await request.json()) as { old_password?: string; new_password?: string };
    if (body.old_password !== auth.user.password) {
      return failV1(400, 'OLD_PASSWORD_INCORRECT', '当前密码不对，请再输一遍', {
        old_password: ['当前密码不对，请再输一遍'],
      });
    }
    const policy = await (async () => {
      const isAdminTier = auth.user.is_super === true || auth.user.is_admin === true;
      return { min: isAdminTier ? 10 : 8 };
    })();
    const next = body.new_password ?? '';
    if (next.length < policy.min) {
      return failV1(422, 'VALIDATION_FAILED', '密码太短了', {
        new_password: [`密码太短了，至少要 ${policy.min} 位`],
      });
    }
    // Success: every other session of this user is revoked; current stays.
    sessionsByUser.set(auth.user.username, sessionsFor(auth.user).filter((s) => s.current));
    const state = profileFor(auth.user);
    state.password_changed_time = new Date().toISOString().slice(0, 10);
    return okV1({ changed: true });
  }),

  // 登录设备
  http.get(`${base}/me/sessions`, ({ request }) => {
    const auth = requireUser(request);
    if (!auth) return unauthorized();
    const list = sessionsFor(auth.user).filter(
      (s) => !s.expire_time || new Date(s.expire_time).getTime() > Date.now(),
    );
    return okV1({ items: list });
  }),

  http.delete(`${base}/me/sessions`, ({ request }) => {
    const auth = requireUser(request);
    if (!auth) return unauthorized();
    const list = sessionsFor(auth.user);
    const kept = list.filter((s) => s.current);
    const removed = list.length - kept.length;
    sessionsByUser.set(auth.user.username, kept);
    return okV1({ removed });
  }),

  http.delete(`${base}/me/sessions/:sessionId`, ({ request, params }) => {
    const auth = requireUser(request);
    if (!auth) return unauthorized();
    const session = sessionsFor(auth.user).find((s) => s.id === String(params.sessionId));
    if (!session) {
      return failV1(404, 'NOT_FOUND', '这台设备已经不在登录列表里了');
    }
    if (session.current) {
      return failV1(400, 'SESSION_CURRENT', '不能退出当前登录的设备');
    }
    const sessionId = String(params.sessionId);
    sessionsByUser.set(
      auth.user.username,
      sessionsFor(auth.user).filter((s) => s.id !== sessionId),
    );
    return okV1({ removed: true });
  }),

  // 工作台
  http.get(`${base}/dashboard/onboarding`, ({ request }) => {
    const auth = requireUser(request);
    if (!auth) return unauthorized();
    const isAdmin = auth.user.is_super === true || auth.user.is_admin === true;
    if (!isAdmin) {
      return okV1({ done: true, steps: [] });
    }
    return okV1({
      done: false,
      steps: [
        { key: 'add_user', done: true },
        { key: 'set_roles', done: false },
        { key: 'connect_ai', done: false },
      ],
    });
  }),

  http.get(`${base}/dashboard/summary`, ({ request }) => {
    const auth = requireUser(request);
    if (!auth) return unauthorized();
    return okV1(dashboardSummary(auth.user));
  }),

  http.get(`${base}/dashboard/todos`, ({ request }) => {
    const auth = requireUser(request);
    if (!auth) return unauthorized();
    const url = new URL(request.url);
    const limit = Math.min(Number(url.searchParams.get('limit')) || 5, 50);
    const all = dashboardTodos(auth.user);
    return okV1({ items: all.items.slice(0, limit), total: all.total });
  }),

  http.get(`${base}/dashboard/todos/all`, ({ request }) => {
    const auth = requireUser(request);
    if (!auth) return unauthorized();
    const url = new URL(request.url);
    const page = Math.max(1, Number(url.searchParams.get('page')) || 1);
    const pageSize = Math.min(Number(url.searchParams.get('page_size')) || 20, 100);
    const all = dashboardTodos(auth.user);
    const start = (page - 1) * pageSize;
    return okV1({
      items: all.items.slice(start, start + pageSize),
      total: all.total,
      page,
      page_size: pageSize,
    });
  }),

  http.get(`${base}/dashboard/charts`, ({ request }) => {
    const auth = requireUser(request);
    if (!auth) return unauthorized();
    const url = new URL(request.url);
    const range = url.searchParams.get('range') ?? '7d';
    return okV1(dashboardCharts(range, auth.user));
  }),

  http.get(`${base}/dashboard/activities`, ({ request }) => {
    const auth = requireUser(request);
    if (!auth) return unauthorized();
    if (!hasPermission(auth.user, 'system:audit-log:index')) {
      return okV1({ items: [] });
    }
    return okV1({
      items: [
        { actor_name: '管理员', action_text: '修改了「选项管理」配置', target_label: '选项管理', create_time: iso(0, 9, 40) },
        { actor_name: '系统', action_text: '完成了每晚数据备份', target_label: null, create_time: iso(0, 3, 0) },
        { actor_name: '王五', action_text: '提交了请假申请', target_label: '请假申请', create_time: iso(1, 14, 22) },
        { actor_name: '管理员', action_text: '重置了「李四」的密码', target_label: '李四', create_time: iso(2, 10, 15) },
        { actor_name: '系统', action_text: '检测到一次异常登录并已记录', target_label: null, create_time: iso(3, 23, 5) },
      ],
    });
  }),

  http.get(`${base}/dashboard/notices`, ({ request }) => {
    const auth = requireUser(request);
    if (!auth) return unauthorized();
    const items = [];
    if (hasPermission(auth.user, 'system:plugin:index')) {
      items.push({
        notice_key: 'plugin-upgrade',
        level: 'warning',
        text_key: 'pluginUpgrade',
        params: { count: 2 },
        action: { label_key: 'goUpgrade', link: '/system/plugin' },
      });
    }
    if (!hasPermission(auth.user, 'sand_ai:provider:index')) {
      items.push({
        notice_key: 'ai-provider-missing',
        level: 'info',
        text_key: 'aiProviderMissing',
        params: null,
        action: { label_key: 'goConnect', link: '/plugin/sand-ai/provider' },
      });
    }
    return okV1({ items });
  }),

  http.post(`${base}/dashboard/notices/:noticeKey/dismiss`, () => okV1({ dismissed: true })),

  http.get(`${base}/dashboard/shortcuts`, ({ request }) => {
    const auth = requireUser(request);
    if (!auth) return unauthorized();
    const override = shortcutOverrides.get(auth.user.username);
    if (override) {
      const items = override
        .map((key) => AUTO_SHORTCUTS.find((s) => s.menu_key === key))
        .filter((s): s is NonNullable<typeof s> => Boolean(s));
      return okV1({ mode: 'manual', items });
    }
    return okV1({ mode: 'auto', items: AUTO_SHORTCUTS });
  }),

  http.put(`${base}/dashboard/shortcuts`, async ({ request }) => {
    const auth = requireUser(request);
    if (!auth) return unauthorized();
    const body = (await request.json()) as { menu_keys?: string[] };
    const keys = (body.menu_keys ?? []).slice(0, 8);
    shortcutOverrides.set(auth.user.username, keys);
    const items = keys
      .map((key) => AUTO_SHORTCUTS.find((s) => s.menu_key === key))
      .filter((s): s is NonNullable<typeof s> => Boolean(s));
    return okV1({ mode: 'manual', items });
  }),

  http.delete(`${base}/dashboard/shortcuts`, ({ request }) => {
    const auth = requireUser(request);
    if (!auth) return unauthorized();
    shortcutOverrides.delete(auth.user.username);
    return okV1({ mode: 'auto', items: AUTO_SHORTCUTS });
  }),

  http.post(`${base}/dashboard/visits`, () => okV1({ recorded: true })),

  // 通知中心
  http.get(`${base}/notifications`, ({ request }) => {
    const auth = requireUser(request);
    if (!auth) return unauthorized();
    const url = new URL(request.url);
    const unreadOnly = url.searchParams.get('unread_only') === 'true';
    const page = Math.max(1, Number(url.searchParams.get('page')) || 1);
    const pageSize = Math.min(Number(url.searchParams.get('page_size')) || 20, 100);

    const ntfRead = readSet(readNotifications, auth.user.username);
    const items = inboxFor(auth.user)
      .map((item) => {
        const readTime =
          item.kind === 'announcement'
            ? announcementReadTime(item.id, auth.user.username)
            : ntfRead.has(item.id)
              ? iso(0, 12, 0)
              : null;
        return { ...item, read_time: readTime };
      })
      .filter((item) => !unreadOnly || item.read_time === null);
    const start = (page - 1) * pageSize;
    return okV1({
      items: items.slice(start, start + pageSize),
      total: items.length,
      page,
      page_size: pageSize,
    });
  }),

  http.get(`${base}/notifications/unread-count`, ({ request }) => {
    const auth = requireUser(request);
    if (!auth) return unauthorized();
    const ntfRead = readSet(readNotifications, auth.user.username);
    const count = inboxFor(auth.user).filter((item) =>
      item.kind === 'announcement'
        ? announcementReadTime(item.id, auth.user.username) === null
        : !ntfRead.has(item.id),
    ).length;
    return okV1({ count });
  }),

  http.post(`${base}/notifications/:notificationId/read`, ({ request, params }) => {
    const auth = requireUser(request);
    if (!auth) return unauthorized();
    readSet(readNotifications, auth.user.username).add(String(params.notificationId));
    return okV1({ read: true });
  }),

  http.post(`${base}/notifications/read-all`, ({ request }) => {
    const auth = requireUser(request);
    if (!auth) return unauthorized();
    const ntfRead = readSet(readNotifications, auth.user.username);
    for (const item of inboxFor(auth.user)) {
      if (item.kind === 'announcement') {
        announcementMarkRead(item.id, auth.user.username);
      } else {
        ntfRead.add(item.id);
      }
    }
    return okV1({ read: true });
  }),

] satisfies HttpHandler[];
