import { http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { z } from 'zod';

import { env } from '@/config/env';
import { server } from '@/mocks/server';
import { ApiError, apiDelete, apiDownload, apiGet, apiPost, apiUpload } from './apiClient';
import { clearToken, setToken } from './authToken';

const base = env.apiBaseUrl;

function envelope(body: Record<string, unknown> | null, init?: { status?: number }) {
  return HttpResponse.json(body, init);
}

describe('apiClient', () => {
  it('unwraps data from a {code:200} envelope', async () => {
    server.use(
      http.get(`${base}/test/ok`, () =>
        envelope({ code: 200, message: 'ok', data: { hello: 'world' }, request_id: 'r-1' }),
      ),
    );

    await expect(apiGet<{ hello: string }>('/test/ok')).resolves.toEqual({ hello: 'world' });
  });

  it('throws ApiError with code, message, fields and requestId on business failure', async () => {
    server.use(
      http.get(`${base}/test/fail`, () =>
        envelope({
          code: 4002,
          message: '用户名或密码不正确',
          data: null,
          fields: { username: ['不能为空'] },
          request_id: 'req-42',
        }),
      ),
    );

    const err = await apiGet('/test/fail').catch((e: unknown) => e);
    expect(err).toBeInstanceOf(ApiError);
    const apiErr = err as ApiError;
    expect(apiErr.code).toBe(4002);
    expect(apiErr.message).toBe('用户名或密码不正确');
    expect(apiErr.fields).toEqual({ username: ['不能为空'] });
    expect(apiErr.requestId).toBe('req-42');
    // Business envelope errors are not system failures but still carry
    // request diagnostics.
    expect(apiErr.system).toBe(false);
    expect(apiErr.method).toBe('GET');
    expect(apiErr.url).toBe(`${base}/test/fail`);
    expect(apiErr.status).toBe(200);
  });

  it('surfaces the backend message verbatim on a write rejection', async () => {
    server.use(
      http.post(`${base}/test/write-denied`, () =>
        envelope({
          code: 4030,
          message: '当前账号为只读权限，无法修改偏好',
          data: null,
          request_id: 'req-4030',
        }),
      ),
    );

    const err = await apiPost('/test/write-denied', {}).catch((e: unknown) => e);
    expect(err).toBeInstanceOf(ApiError);
    const apiErr = err as ApiError;
    expect(apiErr.code).toBe(4030);
    expect(apiErr.message).toBe('当前账号为只读权限，无法修改偏好');
    expect(apiErr.system).toBe(false);
  });

  it('validates the payload through a zod schema when given one', async () => {
    server.use(
      http.get(`${base}/test/schema`, () =>
        envelope({ code: 200, message: 'ok', data: { count: 3 } }),
      ),
    );

    const schema = z.object({ count: z.number() });
    await expect(apiGet('/test/schema', schema)).resolves.toEqual({ count: 3 });
  });

  it('sends the Authorization header when a token exists', async () => {
    setToken('t-123');
    let seenAuth: string | null = null;
    server.use(
      http.post(`${base}/test/auth`, ({ request }) => {
        seenAuth = request.headers.get('authorization');
        return envelope({ code: 200, message: 'ok', data: true });
      }),
    );

    await apiPost('/test/auth', {});
    expect(seenAuth).toBe('Bearer t-123');
    clearToken();
  });

  it('throws ApiError on a non-envelope response', async () => {
    server.use(http.get(`${base}/test/broken`, () => new HttpResponse('<html>oops</html>')));

    const err = await apiGet('/test/broken').catch((e: unknown) => e);
    expect(err).toBeInstanceOf(ApiError);
    expect((err as ApiError).message).not.toContain('oops');
    expect((err as ApiError).system).toBe(true);
  });

  it('marks network failures as system errors with request diagnostics', async () => {
    server.use(http.get(`${base}/test/netfail`, () => HttpResponse.error()));

    const err = await apiGet('/test/netfail').catch((e: unknown) => e);
    expect(err).toBeInstanceOf(ApiError);
    const apiErr = err as ApiError;
    expect(apiErr.system).toBe(true);
    expect(apiErr.code).toBe(-1);
    expect(apiErr.method).toBe('GET');
    expect(apiErr.url).toBe(`${base}/test/netfail`);
    expect(apiErr.message).toBe('系统出了点问题，请稍后再试');
  });

  it('marks HTTP 5xx as system errors even with an envelope body', async () => {
    server.use(
      http.get(`${base}/test/http500`, () =>
        envelope(
          { code: 5000, message: 'boom', data: null, request_id: 'r-500' },
          { status: 500 },
        ),
      ),
    );

    const err = await apiGet('/test/http500').catch((e: unknown) => e);
    expect(err).toBeInstanceOf(ApiError);
    const apiErr = err as ApiError;
    expect(apiErr.system).toBe(true);
    expect(apiErr.status).toBe(500);
    expect(apiErr.requestId).toBe('r-500');
    expect(apiErr.method).toBe('GET');
    expect(apiErr.url).toBe(`${base}/test/http500`);
  });

  it('marks payload schema mismatches as system errors', async () => {
    server.use(
      http.get(`${base}/test/schema-bad`, () =>
        envelope({ code: 200, message: 'ok', data: { count: 'NaN' } }),
      ),
    );

    const err = await apiGet('/test/schema-bad', z.object({ count: z.number() })).catch(
      (e: unknown) => e,
    );
    expect(err).toBeInstanceOf(ApiError);
    const apiErr = err as ApiError;
    expect(apiErr.system).toBe(true);
    expect(apiErr.code).toBe(-1);
  });

  it('unwraps data from an OpenAPI {data, request_id} envelope', async () => {
    server.use(
      http.get(`${base}/test/v2-ok`, () =>
        HttpResponse.json({ data: { count: 3 }, request_id: 'r-v2' }),
      ),
    );

    const schema = z.object({ count: z.number() });
    await expect(apiGet('/test/v2-ok', schema)).resolves.toEqual({ count: 3 });
  });

  it('throws ApiError with a string code on an OpenAPI failure envelope', async () => {
    server.use(
      http.post(`${base}/test/v2-fail`, () =>
        HttpResponse.json(
          {
            code: 'VALIDATION_FAILED',
            message: '用户名不能为空',
            fields: { username: ['不能为空'] },
            request_id: 'req-v2',
          },
          { status: 422 },
        ),
      ),
    );

    const err = await apiPost('/test/v2-fail', {}).catch((e: unknown) => e);
    expect(err).toBeInstanceOf(ApiError);
    const apiErr = err as ApiError;
    expect(apiErr.code).toBe('VALIDATION_FAILED');
    expect(apiErr.message).toBe('用户名不能为空');
    expect(apiErr.fields).toEqual({ username: ['不能为空'] });
    expect(apiErr.requestId).toBe('req-v2');
    expect(apiErr.system).toBe(false);
    expect(apiErr.status).toBe(422);
  });

  it('marks HTTP 5xx with an OpenAPI error body as a system error', async () => {
    server.use(
      http.get(`${base}/test/v2-500`, () =>
        HttpResponse.json(
          {
            code: 'INTERNAL_ERROR',
            message: 'boom',
            fields: {},
            request_id: 'r-v2-500',
          },
          { status: 500 },
        ),
      ),
    );

    const err = await apiGet('/test/v2-500').catch((e: unknown) => e);
    expect(err).toBeInstanceOf(ApiError);
    const apiErr = err as ApiError;
    expect(apiErr.system).toBe(true);
    expect(apiErr.code).toBe(-1);
    expect(apiErr.status).toBe(500);
    expect(apiErr.requestId).toBe('r-v2-500');
  });

  it('sends DELETE without a body and unwraps the OpenAPI envelope', async () => {
    let seenMethod: string | null = null;
    server.use(
      http.delete(`${base}/test/v2-delete`, ({ request }) => {
        seenMethod = request.method;
        return HttpResponse.json({ data: {}, request_id: 'r-del' });
      }),
    );

    await expect(apiDelete('/test/v2-delete')).resolves.toEqual({});
    expect(seenMethod).toBe('DELETE');
  });

  it('posts FormData without a manual Content-Type and unwraps the envelope', async () => {
    let seenType: string | null = null;
    server.use(
      http.post(`${base}/test/v2-upload`, ({ request }) => {
        seenType = request.headers.get('content-type');
        return HttpResponse.json({ data: { id: 'f-1' }, request_id: 'r-up' });
      }),
    );

    const form = new FormData();
    // A string entry keeps the jsdom FormData compatible with the fetch
    // polyfill; a jsdom Blob would break vitest's FormData conversion.
    form.append('file', 'file-bytes');
    await expect(apiUpload<{ id: string }>('/test/v2-upload', form)).resolves.toEqual({
      id: 'f-1',
    });
    // The browser must set the multipart boundary itself.
    expect(seenType).toContain('multipart/form-data');
    expect(seenType).toContain('boundary=');
  });

  it('throws ApiError when an upload is rejected', async () => {
    server.use(
      http.post(`${base}/test/v2-upload-fail`, () =>
        HttpResponse.json(
          {
            code: 'FILE_TOO_LARGE',
            message: '文件超过 100MB 上限',
            fields: {},
            request_id: 'r-up-fail',
          },
          { status: 413 },
        ),
      ),
    );

    const err = await apiUpload('/test/v2-upload-fail', new FormData()).catch(
      (e: unknown) => e,
    );
    expect(err).toBeInstanceOf(ApiError);
    const apiErr = err as ApiError;
    expect(apiErr.code).toBe('FILE_TOO_LARGE');
    expect(apiErr.message).toBe('文件超过 100MB 上限');
    expect(apiErr.system).toBe(false);
    expect(apiErr.status).toBe(413);
  });

  it('returns a Blob for binary downloads', async () => {
    server.use(
      http.get(
        `${base}/test/v2-download`,
        () =>
          new HttpResponse('binary', {
            headers: { 'Content-Type': 'application/octet-stream' },
          }),
      ),
    );

    const blob = await apiDownload('/test/v2-download');
    expect(blob).toBeInstanceOf(Blob);
    await expect(blob.text()).resolves.toBe('binary');
  });

  it('parses the JSON envelope and throws ApiError on download errors', async () => {
    server.use(
      http.get(`${base}/test/v2-download-fail`, () =>
        HttpResponse.json(
          {
            code: 'FILE_NOT_FOUND',
            message: '文件不存在',
            fields: {},
            request_id: 'r-dl',
          },
          { status: 404 },
        ),
      ),
    );

    const err = await apiDownload('/test/v2-download-fail').catch((e: unknown) => e);
    expect(err).toBeInstanceOf(ApiError);
    const apiErr = err as ApiError;
    expect(apiErr.code).toBe('FILE_NOT_FOUND');
    expect(apiErr.system).toBe(false);
    expect(apiErr.status).toBe(404);
  });
});
