import { http, type HttpHandler } from 'msw';

import { env } from '@/config/env';
import { fail, ok } from './_shared';

const base = env.apiBaseUrl;

const MOCK_PASSWORD = 'Admin@123456';

// Failure counters per username — >=3 failed attempts requires a captcha.
const failures = new Map<string, number>();
const tokenToUser = new Map<string, { username: string; nickname: string; mustChangePassword: boolean }>();

const CAPTCHA_SVG = `<svg xmlns="http://www.w3.org/2000/svg" width="300" height="160">
  <rect width="300" height="160" fill="#e8f0fe"/>
  <circle cx="80" cy="60" r="18" fill="#1664ff" opacity="0.7"/>
  <circle cx="150" cy="100" r="18" fill="#f80" opacity="0.7"/>
  <circle cx="220" cy="50" r="18" fill="#3ac76d" opacity="0.7"/>
  <text x="150" y="150" text-anchor="middle" font-size="14" fill="#555">click the three dots in order</text>
</svg>`;

export default [
  http.post(`${base}/auth/login`, async ({ request }) => {
    const body = (await request.json()) as {
      username?: string;
      password?: string;
      captchaId?: string;
      captchaAnswer?: number[];
    };
    const username = body.username ?? '';
    const failed = failures.get(username) ?? 0;

    if (failed >= 3 && !body.captchaId) {
      return fail(4001, '失败次数太多，请先完成验证码');
    }

    const known = username === 'admin' || username === 'newbie';
    if (known && body.password === MOCK_PASSWORD) {
      failures.delete(username);
      const token = `mock-token-${username}`;
      const mustChangePassword = username === 'newbie';
      tokenToUser.set(token, { username, nickname: username === 'admin' ? '管理员' : '新同事', mustChangePassword });
      return ok({ token, mustChangePassword }, '登录成功');
    }

    failures.set(username, failed + 1);
    return fail(4002, '用户名或密码不正确');
  }),

  http.post(`${base}/auth/logout`, () => ok(null)),

  http.get(`${base}/auth/captcha`, () =>
    ok({
      captchaId: 'mock-captcha-1',
      imageDataUrl: `data:image/svg+xml;utf8,${encodeURIComponent(CAPTCHA_SVG)}`,
    }),
  ),

  http.post(`${base}/auth/captcha/verify`, () => ok(true)),

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
      permissions: ['system.user.list'],
    });
  }),
] satisfies HttpHandler[];
