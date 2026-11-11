import type { ZodType } from 'zod';

import { env } from '@/config/env';
import i18n from '@/i18n/i18n';
import { getToken } from './authToken';

export interface ApiErrorShape {
  /** Envelope code: a number on the legacy envelope, a string on the OpenAPI one. */
  code: number | string;
  message: string;
  fields?: Record<string, string[]>;
  requestId?: string;
  /** HTTP method used for the request (e.g. 'GET'). */
  method?: string;
  /** Fully joined request URL (api base + path). */
  url?: string;
  /** HTTP status when a response was received. */
  status?: number;
  /**
   * True for infrastructural failures (network, HTTP 5xx, unreadable or
   * schema-mismatched payloads); false/omitted for business envelope errors.
   */
  system?: boolean;
}

/**
 * Request failure. `code` is the envelope code for business errors and -1 for
 * infrastructural ones — it is never an HTTP status and must not be shown to
 * users as such.
 */
export class ApiError extends Error {
  code: number | string;
  fields?: Record<string, string[]>;
  requestId?: string;
  method?: string;
  url?: string;
  status?: number;
  system: boolean;

  constructor(shape: ApiErrorShape) {
    super(shape.message);
    this.name = 'ApiError';
    this.code = shape.code;
    this.fields = shape.fields;
    this.requestId = shape.requestId;
    this.method = shape.method;
    this.url = shape.url;
    this.status = shape.status;
    this.system = shape.system ?? false;
  }
}

/** True when the failure is infrastructural rather than a business error. */
export function isSystemApiError(error: unknown): error is ApiError {
  return error instanceof ApiError && error.system;
}

/**
 * User-facing text for a failed request. `ApiError.message` is already safe
 * to show: the backend's plain-language reason on business errors and the
 * unified system copy on infrastructural ones. Anything else gets the
 * caller's fallback.
 */
export function apiErrorMessage(error: unknown, fallback: string): string {
  return error instanceof ApiError ? error.message : fallback;
}

/**
 * Both response envelopes are accepted side by side: the legacy one carries a
 * numeric `code` (200 = success), the OpenAPI one omits `code` on success and
 * carries a string error code on failure.
 */
interface Envelope {
  code?: number | string;
  message?: string;
  data?: unknown;
  fields?: Record<string, string[]>;
  request_id?: string;
}

type HttpMethod = 'GET' | 'POST' | 'PUT' | 'DELETE';

interface RequestContext {
  method: string;
  url: string;
}

function joinUrl(base: string, path: string): string {
  const b = base.replace(/\/+$/, '');
  const p = path.startsWith('/') ? path : `/${path}`;
  return `${b}${p}`;
}

function systemError(init: {
  requestId?: string;
  method: string;
  url: string;
  status?: number;
}): ApiError {
  return new ApiError({
    code: -1,
    message: i18n.t('errors.system.message'),
    system: true,
    ...init,
  });
}

async function fetchRaw(
  method: HttpMethod,
  path: string,
  init: { json?: unknown; form?: FormData; accept?: string },
): Promise<{ res: Response } & RequestContext> {
  const headers: Record<string, string> = { Accept: init.accept ?? 'application/json' };
  if (init.json !== undefined) {
    headers['Content-Type'] = 'application/json';
  }
  const token = getToken();
  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  const url = joinUrl(env.apiBaseUrl, path);

  let res: Response;
  try {
    res = await fetch(url, {
      method,
      headers,
      // FormData goes out without a manual Content-Type so the browser can
      // attach the multipart boundary itself.
      body: init.json !== undefined ? JSON.stringify(init.json) : init.form,
    });
  } catch {
    throw systemError({ method, url });
  }
  return { res, method, url };
}

async function unwrapJson<T>(
  res: Response,
  ctx: RequestContext,
  schema?: ZodType<T>,
): Promise<T> {
  const { method, url } = ctx;
  const status = res.status;
  const headerRequestId = res.headers.get('x-request-id') ?? undefined;

  let payload: Envelope | null = null;
  let text = '';
  try {
    text = await res.text();
  } catch {
    throw systemError({ requestId: headerRequestId, method, url, status });
  }
  if (text) {
    try {
      payload = JSON.parse(text) as Envelope;
    } catch {
      payload = null;
    }
  }

  const requestId = payload?.request_id ?? headerRequestId;

  if (!payload || typeof payload !== 'object') {
    throw systemError({ requestId, method, url, status });
  }

  // HTTP 5xx is infrastructural even when a body slipped through.
  if (status >= 500) {
    throw systemError({ requestId, method, url, status });
  }

  const parsePayload = (): T => {
    try {
      const data: unknown = payload?.data;
      return schema ? schema.parse(data) : (data as T);
    } catch {
      throw systemError({ requestId, method, url, status });
    }
  };

  // Legacy envelope: a numeric code, 200 means success.
  if (typeof payload.code === 'number') {
    if (payload.code !== 200) {
      throw new ApiError({
        code: payload.code,
        message: payload.message || i18n.t('errors.system.message'),
        fields: payload.fields,
        requestId,
        method,
        url,
        status,
      });
    }
    return parsePayload();
  }

  // OpenAPI envelope: a string code marks a business failure.
  if (typeof payload.code === 'string') {
    throw new ApiError({
      code: payload.code,
      message: payload.message || i18n.t('errors.system.message'),
      fields: payload.fields,
      requestId,
      method,
      url,
      status,
    });
  }

  // OpenAPI success envelope: `data` without `code`/`message`.
  if (!('code' in payload) && 'data' in payload) {
    return parsePayload();
  }

  throw systemError({ requestId, method, url, status });
}

async function request<T>(
  method: HttpMethod,
  path: string,
  init: { json?: unknown; form?: FormData },
  schema?: ZodType<T>,
): Promise<T> {
  const { res, ...ctx } = await fetchRaw(method, path, init);
  return unwrapJson(res, ctx, schema);
}

export function apiGet<T>(path: string, schema?: ZodType<T>): Promise<T> {
  return request('GET', path, {}, schema);
}

export function apiPost<T>(path: string, body?: unknown, schema?: ZodType<T>): Promise<T> {
  return request('POST', path, { json: body }, schema);
}

export function apiPut<T>(path: string, body?: unknown, schema?: ZodType<T>): Promise<T> {
  return request('PUT', path, { json: body }, schema);
}

export function apiDelete<T>(path: string, schema?: ZodType<T>): Promise<T> {
  return request('DELETE', path, {}, schema);
}

export function apiUpload<T>(path: string, form: FormData, schema?: ZodType<T>): Promise<T> {
  return request('POST', path, { form }, schema);
}

export async function apiDownload(path: string): Promise<Blob> {
  const { res, method, url } = await fetchRaw('GET', path, { accept: '*/*' });
  const status = res.status;

  // Errors still arrive as the JSON envelope even on binary endpoints.
  if ((res.headers.get('content-type') ?? '').includes('application/json')) {
    await unwrapJson<unknown>(res, { method, url });
    // A JSON success body carries no blob to hand back.
    throw systemError({
      requestId: res.headers.get('x-request-id') ?? undefined,
      method,
      url,
      status,
    });
  }

  try {
    return await res.blob();
  } catch {
    throw systemError({
      requestId: res.headers.get('x-request-id') ?? undefined,
      method,
      url,
      status,
    });
  }
}
