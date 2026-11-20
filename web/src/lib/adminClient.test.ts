import { http, HttpResponse } from 'msw';
import { describe, expect, it, vi } from 'vitest';

import { env } from '@/config/env';
import { server } from '@/mocks/server';
import {
  AdminApiError,
  adminDelete,
  adminDownload,
  adminGet,
  adminPatch,
  adminPost,
  adminPut,
  adminUpload,
  isAdminSystemError,
  resetAuthzVersion,
  subscribeAuthzVersion,
} from './adminClient';
import { clearToken, setToken } from './authToken';
import { onAppEvent } from './eventBus';

const base = `${env.apiBaseUrl}/admin/v1`;

function ok(data: unknown, init?: { headers?: Record<string, string> }) {
  return HttpResponse.json(
    { code: 'OK', message: '操作成功', data, request_id: 'req-1' },
    init,
  );
}

function fail(
  status: number,
  body: Record<string, unknown>,
  init?: { headers?: Record<string, string> },
) {
  return HttpResponse.json(body, { status, ...init });
}

describe('adminClient', () => {
  it('unwraps data from the {code:OK, data, request_id} envelope', async () => {
    server.use(http.get(`${base}/test/ok`, () => ok({ hello: 'world' })));
    await expect(adminGet<{ hello: string }>('/test/ok')).resolves.toEqual({
      hello: 'world',
    });
  });

  it('maps a business error to AdminApiError with contract fields', async () => {
    server.use(
      http.post(`${base}/test/conflict`, () =>
        fail(409, {
          code: 'ROLE_NAME_TAKEN',
          message: '这个角色名称已经有人用了，请换一个',
          fields: { name: ['这个角色名称已经有人用了，请换一个'] },
          request_id: 'req-409',
        }),
      ),
    );

    const err = await adminPost('/test/conflict', {}).catch((e: unknown) => e);
    expect(err).toBeInstanceOf(AdminApiError);
    const apiErr = err as AdminApiError;
    expect(apiErr.code).toBe('ROLE_NAME_TAKEN');
    expect(apiErr.message).toBe('这个角色名称已经有人用了，请换一个');
    expect(apiErr.fields).toEqual({ name: ['这个角色名称已经有人用了，请换一个'] });
    expect(apiErr.httpStatus).toBe(409);
    expect(apiErr.requestId).toBe('req-409');
    expect(apiErr.system).toBe(false);
    expect(apiErr.method).toBe('POST');
  });

  it('treats HTTP 500 as a system error even with an error envelope', async () => {
    server.use(
      http.get(`${base}/test/http500`, () =>
        fail(500, {
          code: 'INTERNAL_ERROR',
          message: '内部细节不应透出',
          fields: {},
          request_id: 'req-500',
        }),
      ),
    );

    const err = await adminGet('/test/http500').catch((e: unknown) => e);
    expect(err).toBeInstanceOf(AdminApiError);
    const apiErr = err as AdminApiError;
    expect(apiErr.system).toBe(true);
    expect(apiErr.message).toBe('系统出了点问题，请稍后再试');
    expect(apiErr.httpStatus).toBe(500);
    expect(apiErr.requestId).toBe('req-500');
    expect(isAdminSystemError(err)).toBe(true);
  });

  it('treats network failure and non-envelope bodies as system errors', async () => {
    server.use(http.get(`${base}/test/netfail`, () => HttpResponse.error()));
    const netErr = await adminGet('/test/netfail').catch((e: unknown) => e);
    expect(netErr).toBeInstanceOf(AdminApiError);
    expect((netErr as AdminApiError).system).toBe(true);
    expect((netErr as AdminApiError).code).toBe('NETWORK_ERROR');
    expect((netErr as AdminApiError).message).toBe('网络好像断了，请检查网络后再试');
    expect((netErr as AdminApiError).httpStatus).toBe(0);

    server.use(http.get(`${base}/test/html`, () => new HttpResponse('<html>nope</html>')));
    const htmlErr = await adminGet('/test/html').catch((e: unknown) => e);
    expect(htmlErr).toBeInstanceOf(AdminApiError);
    expect((htmlErr as AdminApiError).system).toBe(true);
  });

  it('rejects a 200 payload without the OK code', async () => {
    server.use(
      http.get(`${base}/test/no-data`, () =>
        HttpResponse.json({ data: { hello: 'world' }, request_id: 'r' }),
      ),
    );
    const err = await adminGet('/test/no-data').catch((e: unknown) => e);
    expect(err).toBeInstanceOf(AdminApiError);
    expect((err as AdminApiError).system).toBe(true);
  });

  it('sends Idempotency-Key on writes but not on reads', async () => {
    const seen: Record<string, string | null> = {};
    server.use(
      http.post(`${base}/test/write`, ({ request }) => {
        seen.post = request.headers.get('idempotency-key');
        return ok({});
      }),
      http.put(`${base}/test/write`, ({ request }) => {
        seen.put = request.headers.get('idempotency-key');
        return ok({});
      }),
      http.patch(`${base}/test/write`, ({ request }) => {
        seen.patch = request.headers.get('idempotency-key');
        return ok({});
      }),
      http.delete(`${base}/test/write`, ({ request }) => {
        seen.delete = request.headers.get('idempotency-key');
        return ok({});
      }),
      http.get(`${base}/test/read`, ({ request }) => {
        seen.get = request.headers.get('idempotency-key');
        return ok({});
      }),
    );

    await adminPost('/test/write', {});
    await adminPut('/test/write', {});
    await adminPatch('/test/write', {});
    await adminDelete('/test/write');
    await adminGet('/test/read');

    for (const method of ['post', 'put', 'patch', 'delete'] as const) {
      expect(seen[method]).toMatch(/^[0-9a-f-]{36}$/i);
    }
    expect(seen.get).toBeNull();
  });

  it('sends the configured CSRF marker header on every request', async () => {
    const seen: Record<string, string | null> = {};
    const capture =
      (key: string) =>
      ({ request }: { request: Request }) => {
        seen[key] = request.headers.get(env.csrfHeaderName);
        return ok({});
      };
    server.use(
      http.post(`${base}/test/csrf`, capture('post')),
      http.put(`${base}/test/csrf`, capture('put')),
      http.patch(`${base}/test/csrf`, capture('patch')),
      http.delete(`${base}/test/csrf`, capture('delete')),
      http.get(`${base}/test/csrf`, capture('get')),
    );

    await adminPost('/test/csrf', {});
    await adminPut('/test/csrf', {});
    await adminPatch('/test/csrf', {});
    await adminDelete('/test/csrf');
    await adminGet('/test/csrf');

    for (const method of ['post', 'put', 'patch', 'delete', 'get'] as const) {
      expect(seen[method]).toBe(env.csrfHeaderValue);
    }
  });

  it('sends the CSRF marker header on multipart uploads', async () => {
    let seen: string | null = null;
    server.use(
      http.post(`${base}/test/upload-csrf`, ({ request }) => {
        seen = request.headers.get(env.csrfHeaderName);
        return ok({});
      }),
    );
    await adminUpload('/test/upload-csrf', new FormData());
    expect(seen).toBe(env.csrfHeaderValue);
  });

  it('honours an explicit idempotencyKey override', async () => {
    let seen: string | null = null;
    server.use(
      http.post(`${base}/test/idem`, ({ request }) => {
        seen = request.headers.get('idempotency-key');
        return ok({});
      }),
    );
    await adminPost('/test/idem', {}, { idempotencyKey: 'fixed-key-1' });
    expect(seen).toBe('fixed-key-1');
  });

  it('sends the bearer token and serializes query params verbatim', async () => {
    setToken('t-abc');
    let seenUrl = '';
    let seenAuth: string | null = null;
    server.use(
      http.get(`${base}/test/query`, ({ request }) => {
        seenUrl = request.url;
        seenAuth = request.headers.get('authorization');
        return ok({});
      }),
    );

    await adminGet('/test/query', {
      query: {
        keyword: '张三',
        status: 'enabled',
        page: 2,
        page_size: 50,
        empty: '',
        nothing: null,
        flag: true,
      },
    });

    expect(seenAuth).toBe('Bearer t-abc');
    const parsed = new URL(seenUrl, 'http://localhost');
    expect(parsed.pathname).toBe(`${base}/test/query`);
    expect(parsed.searchParams.get('keyword')).toBe('张三');
    expect(parsed.searchParams.get('status')).toBe('enabled');
    expect(parsed.searchParams.get('page')).toBe('2');
    expect(parsed.searchParams.get('page_size')).toBe('50');
    expect(parsed.searchParams.get('flag')).toBe('true');
    expect(parsed.searchParams.has('empty')).toBe(false);
    expect(parsed.searchParams.has('nothing')).toBe(false);
    clearToken();
  });

  it('notifies authz subscribers only when X-Authz-Version changes', () => {
    resetAuthzVersion();
    const listener = vi.fn();
    const unsubscribe = subscribeAuthzVersion(listener);

    server.use(
      http.get(`${base}/test/v1`, () =>
        ok({}, { headers: { 'x-authz-version': '3' } }),
      ),
      http.get(`${base}/test/v2`, () =>
        ok({}, { headers: { 'x-authz-version': '3' } }),
      ),
      http.get(`${base}/test/v3`, () =>
        ok({}, { headers: { 'x-authz-version': '4' } }),
      ),
    );

    return (async () => {
      await adminGet('/test/v1');
      await adminGet('/test/v2');
      expect(listener).not.toHaveBeenCalled();
      await adminGet('/test/v3');
      expect(listener).toHaveBeenCalledTimes(1);
      unsubscribe();
      resetAuthzVersion();
    })();
  });

  it('downloads binary content and parses the filename', async () => {
    server.use(
      http.get(`${base}/test/file`, () =>
        new HttpResponse('a,b\n1,2', {
          headers: {
            'content-type': 'text/csv; charset=utf-8',
            'content-disposition': 'attachment; filename="users.csv"',
          },
        }),
      ),
    );

    const file = await adminDownload('/test/file');
    expect(file.fileName).toBe('users.csv');
    expect(await file.blob.text()).toBe('a,b\n1,2');
  });

  it('maps download error envelopes to AdminApiError', async () => {
    server.use(
      http.get(`${base}/test/file-err`, () =>
        fail(409, {
          code: 'TASK_RUN_NOT_DOWNLOADABLE',
          message: '结果文件已过期或不存在',
          fields: {},
          request_id: 'req-dl',
        }),
      ),
    );

    const err = await adminDownload('/test/file-err').catch((e: unknown) => e);
    expect(err).toBeInstanceOf(AdminApiError);
    expect((err as AdminApiError).code).toBe('TASK_RUN_NOT_DOWNLOADABLE');
    expect((err as AdminApiError).httpStatus).toBe(409);
  });

  it('accepts the full success envelope with code OK', async () => {
    server.use(
      http.get(`${base}/test/ok-full`, () =>
        HttpResponse.json({ code: 'OK', data: { id: '1' }, request_id: 'req-ok' }),
      ),
    );
    await expect(adminGet<{ id: string }>('/test/ok-full')).resolves.toEqual({ id: '1' });
  });

  it('emits request begin/end events around a request', async () => {
    const events: string[] = [];
    const offBegin = onAppEvent('request:begin', () => events.push('begin'));
    const offEnd = onAppEvent('request:end', () => events.push('end'));
    server.use(http.get(`${base}/test/lifecycle`, () => ok({})));
    await adminGet('/test/lifecycle');
    expect(events).toEqual(['begin', 'end']);
    offBegin();
    offEnd();
  });

  it('emits session:expired on a 401 error envelope', async () => {
    const handler = vi.fn();
    const unsubscribe = onAppEvent('session:expired', handler);
    server.use(
      http.get(`${base}/test/expired`, () =>
        fail(401, { code: 'SESSION_EXPIRED', message: '登录已过期，请重新登录' }),
      ),
    );
    await adminGet('/test/expired').catch(() => undefined);
    expect(handler).toHaveBeenCalledWith({ message: '登录已过期，请重新登录' });
    unsubscribe();
  });

  it('emits sudo:required when the contract returns SUDO_REQUIRED', async () => {
    const handler = vi.fn();
    const unsubscribe = onAppEvent('sudo:required', handler);
    server.use(
      http.post(`${base}/test/sudo`, () =>
        fail(403, { code: 'SUDO_REQUIRED', message: '该操作需要再次验证' }),
      ),
    );
    const err = await adminPost('/test/sudo', {}).catch((e: unknown) => e);
    expect(handler).toHaveBeenCalledWith({ error: err });
    unsubscribe();
  });

  it('parses Retry-After on 429 into retryAfterMs', async () => {
    server.use(
      http.get(`${base}/test/limited`, () =>
        fail(
          429,
          { code: 'RATE_LIMITED', message: '操作太频繁了，请稍后再试' },
          { headers: { 'retry-after': '30' } },
        ),
      ),
    );
    const err = await adminGet('/test/limited').catch((e: unknown) => e);
    expect(err).toBeInstanceOf(AdminApiError);
    expect((err as AdminApiError).retryAfterMs).toBe(30_000);
  });
});
