import type { ZodType } from 'zod';

import { env } from '@/config/env';
import i18n from '@/i18n/i18n';
import { getToken } from './authToken';

export interface ApiErrorShape {
  code: number;
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
  code: number;
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

interface Envelope {
  code?: number;
  message?: string;
  data?: unknown;
  fields?: Record<string, string[]>;
  request_id?: string;
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

async function request<T>(
  method: 'GET' | 'POST' | 'PUT',
  path: string,
  body?: unknown,
  schema?: ZodType<T>,
): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json' };
  if (body !== undefined) {
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
      body: body === undefined ? undefined : JSON.stringify(body),
    });
  } catch {
    throw systemError({ method, url });
  }

  const headerRequestId = res.headers.get('x-request-id') ?? undefined;
  const status = res.status;

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

  if (!payload || typeof payload !== 'object' || typeof payload.code !== 'number') {
    throw systemError({ requestId, method, url, status });
  }

  // HTTP 5xx is infrastructural even when a body slipped through.
  if (status >= 500) {
    throw systemError({ requestId, method, url, status });
  }

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

  const data = payload.data as T;
  try {
    return schema ? schema.parse(data) : data;
  } catch {
    throw systemError({ requestId, method, url, status });
  }
}

export function apiGet<T>(path: string, schema?: ZodType<T>): Promise<T> {
  return request('GET', path, undefined, schema);
}

export function apiPost<T>(path: string, body?: unknown, schema?: ZodType<T>): Promise<T> {
  return request('POST', path, body, schema);
}

export function apiPut<T>(path: string, body?: unknown, schema?: ZodType<T>): Promise<T> {
  return request('PUT', path, body, schema);
}
