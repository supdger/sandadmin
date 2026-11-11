import { HttpResponse } from 'msw';

import { env } from '@/config/env';
import { mockUserForToken } from './auth';
import { authzVersion } from './_v1data';

export const v1Base = `${env.apiBaseUrl}/admin/v1`;

function randomRequestId(): string {
  return `v1-${Math.random().toString(36).slice(2, 10)}`;
}

/** v1 成功信封：`{ data, request_id }`——没有 `code`/`message`。 */
export function okV1<T>(data: T, init?: { status?: number }) {
  return HttpResponse.json(
    { data, request_id: randomRequestId() },
    {
      status: init?.status ?? 200,
      headers: { 'x-authz-version': String(authzVersion()) },
    },
  );
}

/** v1 失败信封：真实 HTTP 状态 + 字符串错误码。 */
export function failV1(
  status: number,
  code: string,
  message: string,
  fields?: Record<string, string[]>,
) {
  return HttpResponse.json(
    {
      code,
      message,
      ...(fields ? { fields } : {}),
      request_id: randomRequestId(),
    },
    {
      status,
      headers: { 'x-authz-version': String(authzVersion()) },
    },
  );
}

export function bearerToken(request: Request): string {
  return (request.headers.get('authorization') ?? '').replace(/^Bearer\s+/i, '');
}

export type MockPrincipal = NonNullable<ReturnType<typeof mockUserForToken>>;

/** 把 bearer token 解析成 mock 用户；失败返回 null（走 401）。 */
export function requireUser(request: Request): { user: MockPrincipal } | { response: Response } {
  const user = mockUserForToken(bearerToken(request));
  if (!user) {
    return { response: failV1(401, 'UNAUTHENTICATED', '登录已过期，请重新登录') };
  }
  return { user };
}

/** `'*'` 或显式授权即可通过；不满足返回 403。 */
export function requirePermission(
  request: Request,
  permission: string | string[],
): { user: MockPrincipal } | { response: Response } {
  const auth = requireUser(request);
  if ('response' in auth) {
    return auth;
  }
  const { user } = auth;
  const required = Array.isArray(permission) ? permission : [permission];
  const granted = user.permissions;
  const ok =
    granted.includes('*') ||
    required.some((code) => granted.includes(code));
  if (!ok) {
    return {
      response: failV1(403, 'PERMISSION_DENIED', '没有执行该操作的权限'),
    };
  }
  return { user };
}

/** 查询串辅助：取参数第一个值并去空白。 */
export function queryParam(url: URL, key: string): string {
  return (url.searchParams.get(key) ?? '').trim();
}

export function queryPage(url: URL): { page: number; page_size: number } {
  const page = Math.max(1, Number(url.searchParams.get('page')) || 1);
  const pageSize = Math.min(
    100,
    Math.max(1, Number(url.searchParams.get('page_size')) || 20),
  );
  return { page, page_size: pageSize };
}

// 聚合器会拍平每个兄弟文件的 default export——辅助模块不贡献 handler。
export default [] satisfies import('msw').HttpHandler[];
