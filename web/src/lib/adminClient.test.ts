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
  isAdminSystemError,
  resetAuthzVersion,
  subscribeAuthzVersion,
} from './adminClient';
import { clearToken, setToken } from './authToken';

const base = `${env.apiBaseUrl}/admin/v1`;

function ok(data: unknown, init?: { headers?: Record<string, string> }) {
  return HttpResponse.json({ data, request_id: 'req-1' }, init);
}

function fail(
  status: number,
  body: Record<string, unknown>,
  init?: { headers?: Record<string, string> },
) {
  return HttpResponse.json(body, { status, ...init });
}

describe('adminClient', () => {
  it('unwraps data from the {data, request_id} envelope', async () => {
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
    expect((netErr as AdminApiError).httpStatus).toBe(0);

    server.use(http.get(`${base}/test/html`, () => new HttpResponse('<html>nope</html>')));
    const htmlErr = await adminGet('/test/html').catch((e: unknown) => e);
    expect(htmlErr).toBeInstanceOf(AdminApiError);
    expect((htmlErr as AdminApiError).system).toBe(true);
  });

  it('rejects a 200 payload missing the data key', async () => {
    server.use(http.get(`${base}/test/no-data`, () => HttpResponse.json({ request_id: 'r' })));
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
});
