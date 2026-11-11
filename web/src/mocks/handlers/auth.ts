import { http, type HttpHandler } from 'msw';

import { env } from '@/config/env';
import { fail, ok } from './_shared';

const base = env.apiBaseUrl;

const MOCK_PASSWORD = 'Admin@123456';
const CAPTCHA_AFTER_FAILURES = 3;
const LOCK_AFTER_FAILURES = 10;
const LOCK_MINUTES = 15;
const CLICK_TOLERANCE_PX = 40;
const CAPTCHA_WIDTH = 300;
const CAPTCHA_HEIGHT = 160;
const CAPTCHA_CHAR_COUNT = 3;

export interface MockUser {
  username: string;
  nickname: string;
  mustChangePassword: boolean;
  /** Permission strings reported by /me; `['*']` is the admin wildcard. */
  permissions: string[];
  /**
   * Expected password. `undefined` means the account exists but cannot log
   * in — used for demo accounts whose password comes from an env var that
   * may not be configured.
   */
  password?: string;
  // OpenAPI User fields surfaced by /me for the personal domain.
  phone?: string;
  email?: string | null;
  dept_id?: string | null;
  dept_name?: string | null;
  post_id?: string | null;
  role_ids?: string[];
  is_admin?: boolean;
  is_super?: boolean;
}

interface CaptchaEntry {
  /** Flat [x1, y1, x2, y2, x3, y3] character centers in image coordinates. */
  centers: number[];
  verified: boolean;
}

// Demo accounts beyond admin/newbie get their password from env vars so no
// real credential ever lands in the repo. When the var is unset the account
// simply fails login like a wrong password.
const USERS: Record<string, MockUser> = {
  admin: {
    username: 'admin',
    nickname: '管理员',
    mustChangePassword: false,
    permissions: ['*'],
    password: MOCK_PASSWORD,
    phone: '13800000001',
    email: 'admin@example.com',
    dept_id: 'dept-1',
    dept_name: '总经办',
    role_ids: ['role-super'],
    is_admin: true,
    is_super: true,
  },
  newbie: {
    username: 'newbie',
    nickname: '新同事',
    mustChangePassword: true,
    permissions: ['*'],
    password: MOCK_PASSWORD,
    phone: '13800000002',
    email: 'newbie@example.com',
    dept_id: 'dept-1',
    dept_name: '总经办',
    role_ids: ['role-admin'],
    is_admin: true,
    is_super: false,
  },
  // Read-only demo: menus and pages are visible, every write is denied.
  viewer: {
    username: 'viewer',
    nickname: '只读用户',
    mustChangePassword: false,
    permissions: [
      'workbench:view',
      'preferences:view',
      'system:dict:index',
      'system:file:index',
      'system:announcement:index',
    ],
    password: env.mockViewerPassword ?? undefined,
    phone: '13800000003',
    email: 'viewer@example.com',
    dept_id: 'dept-2',
    dept_name: '行政部',
    role_ids: [],
    is_admin: false,
    is_super: false,
  },
  // No-permission demo: /me/menus is empty and every protected route 403s.
  guest: {
    username: 'guest',
    nickname: '访客',
    mustChangePassword: false,
    permissions: [],
    password: env.mockGuestPassword ?? undefined,
    phone: '13800000004',
    email: null,
    dept_id: 'dept-3',
    dept_name: '市场部',
    role_ids: [],
    is_admin: false,
    is_super: false,
  },
};

const failures = new Map<string, number>();
const lockedUntil = new Map<string, number>();
const captchas = new Map<string, CaptchaEntry>();
const tokenToUser = new Map<string, MockUser>([
  ['mock-admin-token', USERS.admin],
  ['mock-newbie-token', USERS.newbie],
  ['mock-viewer-token', USERS.viewer],
  ['mock-guest-token', USERS.guest],
]);

let captchaSeq = 0;

const CHAR_POOL = '安装验证后台管理数据安全用户系统时间沙盒';
const INK_COLORS = [
  'rgb(22,93,255)',
  'rgb(60,120,40)',
  'rgb(180,80,40)',
  'rgb(120,60,160)',
  'rgb(30,30,30)',
];

/** Tiny deterministic PRNG so a captcha's layout is stable per id. */
function mulberry32(seed: number): () => number {
  let a = seed >>> 0;
  return () => {
    a += 0x6d2b79f5;
    let t = a;
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

function hashId(id: string): number {
  let h = 0;
  for (let i = 0; i < id.length; i += 1) {
    h = (Math.imul(h, 31) + id.charCodeAt(i)) >>> 0;
  }
  return h;
}

function createCaptcha(): { captchaId: string; imageDataUrl: string } {
  captchaSeq += 1;
  const captchaId = `mock-captcha-${captchaSeq}`;
  const rand = mulberry32(hashId(captchaId));

  const centers: number[] = [];
  const chars: string[] = [];
  const usedX: number[] = [];
  for (let i = 0; i < CAPTCHA_CHAR_COUNT; i += 1) {
    // Spread characters across the canvas so centers stay tappable.
    let x = 0;
    for (let attempt = 0; attempt < 10; attempt += 1) {
      x = Math.round(50 + rand() * (CAPTCHA_WIDTH - 100));
      if (usedX.every((prev) => Math.abs(prev - x) > 70)) break;
    }
    usedX.push(x);
    const y = Math.round(60 + rand() * (CAPTCHA_HEIGHT - 110));
    centers.push(x, y);
    chars.push(CHAR_POOL[Math.floor(rand() * CHAR_POOL.length)]);
  }
  captchas.set(captchaId, { centers, verified: false });

  const noise = Array.from({ length: 14 }, () => {
    const cx = Math.round(rand() * CAPTCHA_WIDTH);
    const cy = Math.round(rand() * CAPTCHA_HEIGHT);
    const r = Math.round(2 + rand() * 4);
    return `<circle cx="${cx}" cy="${cy}" r="${r}" fill="rgb(200,205,215)"/>`;
  }).join('');

  const glyphs = chars
    .map((ch, i) => {
      const cx = centers[i * 2];
      const cy = centers[i * 2 + 1];
      const rotate = Math.round(rand() * 40 - 20);
      const fill = INK_COLORS[i % INK_COLORS.length];
      return `<text x="${cx}" y="${cy + 12}" text-anchor="middle" font-size="34" font-family="sans-serif" font-weight="600" fill="${fill}" transform="rotate(${rotate} ${cx} ${cy})">${ch}</text>`;
    })
    .join('');

  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${CAPTCHA_WIDTH}" height="${CAPTCHA_HEIGHT}"><rect width="${CAPTCHA_WIDTH}" height="${CAPTCHA_HEIGHT}" fill="rgb(247,248,250)"/>${noise}${glyphs}</svg>`;
  return { captchaId, imageDataUrl: `data:image/svg+xml;utf8,${encodeURIComponent(svg)}` };
}

function lockMinutesLeft(username: string, now: number): number {
  const until = lockedUntil.get(username) ?? 0;
  return Math.max(1, Math.ceil((until - now) / 60_000));
}

function bumpFailure(username: string): { count: number; justLocked: boolean } {
  const count = (failures.get(username) ?? 0) + 1;
  failures.set(username, count);
  if (count >= LOCK_AFTER_FAILURES) {
    lockedUntil.set(username, Date.now() + LOCK_MINUTES * 60_000);
    return { count, justLocked: true };
  }
  return { count, justLocked: false };
}

export default [
  http.post(`${base}/auth/login`, async ({ request }) => {
    const body = (await request.json()) as {
      username?: string;
      password?: string;
      captchaId?: string;
      captchaAnswer?: number[];
    };
    const username = body.username ?? '';
    const now = Date.now();

    const until = lockedUntil.get(username);
    if (until && until > now) {
      return fail(4009, `失败次数太多，账号已临时锁定，请 ${lockMinutesLeft(username, now)} 分钟后再试`);
    }

    const failed = failures.get(username) ?? 0;
    const entry = body.captchaId ? captchas.get(body.captchaId) : undefined;
    const captchaOk = Boolean(entry?.verified);

    if (failed >= CAPTCHA_AFTER_FAILURES && !captchaOk) {
      const bump = bumpFailure(username);
      if (bump.justLocked) {
        return fail(4009, `失败次数太多，账号已锁定 ${LOCK_MINUTES} 分钟，请稍后再试`);
      }
      return fail(4001, '失败次数较多，请先完成下方验证再登录');
    }

    // The captcha is single-use: consume it whatever the outcome.
    if (body.captchaId) {
      captchas.delete(body.captchaId);
    }

    const user = USERS[username];
    if (user && user.password !== undefined && body.password === user.password) {
      failures.delete(username);
      const token = `mock-${username}-token`;
      tokenToUser.set(token, user);
      return ok({ token, mustChangePassword: user.mustChangePassword }, '登录成功');
    }

    const bump = bumpFailure(username);
    if (bump.justLocked) {
      return fail(4009, `失败次数太多，账号已锁定 ${LOCK_MINUTES} 分钟，请稍后再试`);
    }
    if (bump.count >= CAPTCHA_AFTER_FAILURES) {
      return fail(4001, '用户名或密码不正确，请先完成下方验证再登录');
    }
    return fail(4002, '用户名或密码不正确');
  }),

  http.post(`${base}/auth/logout`, () => ok(null)),

  http.get(`${base}/auth/captcha`, () => ok(createCaptcha())),

  http.post(`${base}/auth/captcha/verify`, async ({ request }) => {
    const body = (await request.json()) as { captchaId?: string; points?: number[] };
    const entry = body.captchaId ? captchas.get(body.captchaId) : undefined;
    const points = body.points ?? [];

    const withinTolerance =
      entry !== undefined &&
      points.length === CAPTCHA_CHAR_COUNT * 2 &&
      entry.centers.every((expected, i) => {
        // Compare pairwise (x, y) — order matters.
        if (i % 2 === 1) return true;
        const dx = points[i] - expected;
        const dy = points[i + 1] - entry.centers[i + 1];
        return Math.hypot(dx, dy) <= CLICK_TOLERANCE_PX;
      });

    if (withinTolerance) {
      entry.verified = true;
      return ok(true);
    }
    // Force the client to fetch a fresh image after a miss.
    if (body.captchaId) {
      captchas.delete(body.captchaId);
    }
    return fail(4004, '点的位置不对，请换一张重新点选');
  }),

  http.get(`${base}/auth/password/policy`, () =>
    ok({
      minLength: 8,
      requireMixedCase: true,
      requireDigit: true,
      requireSymbol: true,
      historyCheckCount: 3,
    }),
  ),

  http.post(`${base}/auth/password/force-change`, async ({ request }) => {
    const auth = request.headers.get('authorization') ?? '';
    if (!auth.replace(/^Bearer\s+/i, '')) {
      return fail(4010, '请先登录');
    }
    const body = (await request.json()) as { oldPassword?: string; newPassword?: string };
    if (body.oldPassword !== MOCK_PASSWORD) {
      return fail(4003, '当前密码不正确');
    }
    return ok(null, '密码已修改');
  }),

  http.get(`${base}/me`, ({ request }) => {
    const auth = request.headers.get('authorization') ?? '';
    const token = auth.replace(/^Bearer\s+/i, '');
    const user = tokenToUser.get(token);
    if (!user) {
      return fail(4010, '登录已过期，请重新登录');
    }
    return ok({
      id: `user-${user.username}`,
      username: user.username,
      nickname: user.nickname,
      mustChangePassword: user.mustChangePassword,
      permissions: [...user.permissions],
      phone: user.phone,
      email: user.email ?? null,
      dept_id: user.dept_id ?? null,
      dept_name: user.dept_name ?? null,
      post_id: user.post_id ?? null,
      role_ids: user.role_ids ?? [],
      is_admin: user.is_admin ?? false,
      is_super: user.is_super ?? false,
    });
  }),
] satisfies HttpHandler[];

/** Resolve a bearer token to its mock user; shared by sibling handlers. */
export function mockUserForToken(token: string): MockUser | undefined {
  return tokenToUser.get(token);
}

/** Test hooks: deterministic access to the mock's internal state. */
export const __authMock = {
  reset(): void {
    failures.clear();
    lockedUntil.clear();
    captchas.clear();
    captchaSeq = 0;
  },
  captchaCenters(captchaId: string): number[] | null {
    return captchas.get(captchaId)?.centers ?? null;
  },
};
