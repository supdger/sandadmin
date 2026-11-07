import type { ZodType } from 'zod';

import { env } from '@/config/env';
import i18n from '@/i18n/i18n';
import { getToken } from './authToken';

export interface ApiErrorShape {
  code: number;
  message: string;
  fields?: Record<string, string[]>;
  requestId?: string;
}

/** Business-level failure. `code` is the envelope code, not an HTTP status. */
export class ApiError extends Error {
  code: number;
  fields?: Record<string, string[]>;
  requestId?: string;

  constructor(shape: ApiErrorShape) {
    super(shape.message);
    this.name = 'ApiError';
    this.code = shape.code;
    this.fields = shape.fields;
    this.requestId = shape.requestId;
  }
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

  let res: Response;
  try {
    res = await fetch(joinUrl(env.apiBaseUrl, path), {
      method,
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
    });
  } catch {
    throw new ApiError({ code: -1, message: i18n.t('errors.network') });
  }

  const headerRequestId = res.headers.get('x-request-id') ?? undefined;

  let payload: Envelope | null = null;
  const text = await res.text();
  if (text) {
    try {
      payload = JSON.parse(text) as Envelope;
    } catch {
      payload = null;
    }
  }

  if (!payload || typeof payload !== 'object' || typeof payload.code !== 'number') {
    throw new ApiError({
      code: -1,
      message: i18n.t('errors.server'),
      requestId: headerRequestId,
    });
  }

  const requestId = payload.request_id ?? headerRequestId;

  if (payload.code !== 200) {
    throw new ApiError({
      code: payload.code,
      message: payload.message || i18n.t('errors.server'),
      fields: payload.fields,
      requestId,
    });
  }

  const data = payload.data as T;
  return schema ? schema.parse(data) : data;
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
