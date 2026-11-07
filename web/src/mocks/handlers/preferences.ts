import { http, type HttpHandler } from 'msw';

import { env } from '@/config/env';
import { mockUserForToken } from './auth';
import { fail, ok } from './_shared';

const base = env.apiBaseUrl;

// Business code for "authenticated but the permission check failed".
const CODE_FORBIDDEN = 4030;

let stored: Record<string, unknown> = { theme: 'auto' };

function bearerToken(request: Request): string {
  return (request.headers.get('authorization') ?? '').replace(/^Bearer\s+/i, '');
}

/** `*` is the admin wildcard; otherwise the exact permission string is required. */
function can(user: { permissions: string[] }, permission: string): boolean {
  return user.permissions.includes('*') || user.permissions.includes(permission);
}

export default [
  http.get(`${base}/me/preferences`, ({ request }) => {
    const user = mockUserForToken(bearerToken(request));
    if (!user) {
      return fail(4010, '登录已过期，请重新登录');
    }
    if (!can(user, 'preferences:view')) {
      return fail(CODE_FORBIDDEN, '当前账号没有查看偏好的权限');
    }
    return ok({ ...stored });
  }),

  http.put(`${base}/me/preferences`, async ({ request }) => {
    const user = mockUserForToken(bearerToken(request));
    if (!user) {
      return fail(4010, '登录已过期，请重新登录');
    }
    // The read-only demo account holds preferences:view but not :write.
    if (!can(user, 'preferences:write')) {
      return fail(CODE_FORBIDDEN, '当前账号为只读权限，无法修改偏好');
    }
    const body = (await request.json()) as Record<string, unknown>;
    stored = { ...stored, ...body };
    return ok(null);
  }),
] satisfies HttpHandler[];
