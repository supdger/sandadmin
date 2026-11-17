import type { ZodType } from 'zod';

import { env } from '@/config/env';
import i18n from '@/i18n/i18n';
import { getToken } from './authToken';
import { eventBus } from './eventBus';

/**
 * 1.x admin 契约（`/api/admin/v1`）客户端。全局联动走 eventBus：
 * request:begin/end 供全局 loading 计数，401 发 session:expired，
 * SUDO_REQUIRED 发 sudo:required。Cookie 会话经
 * `credentials: 'same-origin'`；Bearer 兜底是 FE0 遗留登录流的
 * 过渡（开发 mock 与免登录入口还在用），随 authToken 一起退役。
 */

const ADMIN_V1_PATH = '/admin/v1';

type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

/** 查询值原样序列化；数组按重复键序列化。 */
export type AdminQueryValue = string | number | boolean | null | undefined;
export type AdminQuery = Record<string, AdminQueryValue | AdminQueryValue[]>;

export interface AdminRequestOptions<T> {
  query?: AdminQuery;
  /** 覆盖写方法自动生成的 Idempotency-Key。 */
  idempotencyKey?: string;
  /** 可选：对 `data` 应用的 zod schema；不匹配视为系统错误。 */
  schema?: ZodType<T>;
  signal?: AbortSignal;
}

export interface DownloadedFile {
  blob: Blob;
  /** 从 Content-Disposition 解析；响应头缺失时为 null。 */
  fileName: string | null;
}

/**
 * admin 契约的请求失败。`code` 是后端稳定错误码，传输层失败时用合成码；
 * `message` 已可直接展示——业务错误是后端的大白话原因，
 * 基础设施类错误是统一的系统兜底文案。
 */
export class AdminApiError extends Error {
  code: string;
  fields?: Record<string, string[]>;
  httpStatus: number;
  requestId?: string;
  method: string;
  url: string;
  /** 传输/5xx/响应不可解析为 true；4xx 业务错误为 false。 */
  system: boolean;
  /** 429/503 的 Retry-After 头换算毫秒；没有该头为 undefined。 */
  retryAfterMs?: number;

  constructor(shape: {
    code: string;
    message: string;
    fields?: Record<string, string[]>;
    httpStatus: number;
    requestId?: string;
    method: string;
    url: string;
    system?: boolean;
    retryAfterMs?: number;
  }) {
    super(shape.message);
    this.name = 'AdminApiError';
    this.code = shape.code;
    this.fields = shape.fields;
    this.httpStatus = shape.httpStatus;
    this.requestId = shape.requestId;
    this.method = shape.method;
    this.url = shape.url;
    this.system = shape.system ?? false;
    this.retryAfterMs = shape.retryAfterMs;
  }
}

export function isAdminApiError(error: unknown): error is AdminApiError {
  return error instanceof AdminApiError;
}

/** 基础设施类失败（网络、HTTP 5xx、响应不可读）。 */
export function isAdminSystemError(error: unknown): error is AdminApiError {
  return error instanceof AdminApiError && error.system;
}

type AuthzListener = () => void;

const authzListeners = new Set<AuthzListener>();
let lastAuthzVersion: string | null = null;

/**
 * 后端在影响权限的写操作上递增 `X-Authz-Version`；页面订阅这里以失效
 * `['me']` 和菜单查询。首次观测到的值只建立基线——监听器在后续变化时触发。
 */
export function subscribeAuthzVersion(listener: AuthzListener): () => void {
  authzListeners.add(listener);
  return () => {
    authzListeners.delete(listener);
  };
}

/** 测试钩子：用例之间重置 authz 基线。 */
export function resetAuthzVersion(): void {
  lastAuthzVersion = null;
  authzListeners.clear();
}

function joinUrl(base: string, path: string): string {
  const b = base.replace(/\/+$/, '');
  const suffix = path.startsWith('/') ? path : `/${path}`;
  return `${b}${suffix}`;
}

function adminBaseUrl(): string {
  return joinUrl(env.apiBaseUrl, ADMIN_V1_PATH);
}

function buildUrl(path: string, query?: AdminQuery): string {
  const url = joinUrl(adminBaseUrl(), path);
  if (!query) {
    return url;
  }
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(query)) {
    const values = Array.isArray(value) ? value : [value];
    for (const item of values) {
      if (item === null || item === undefined || item === '') {
        continue;
      }
      params.append(key, String(item));
    }
  }
  const qs = params.toString();
  return qs ? `${url}?${qs}` : url;
}

function trackAuthzVersion(res: Response): void {
  const version = res.headers.get('x-authz-version');
  if (version === null) {
    return;
  }
  if (lastAuthzVersion !== null && lastAuthzVersion !== version) {
    for (const listener of authzListeners) {
      listener();
    }
  }
  lastAuthzVersion = version;
}

function systemError(init: {
  code?: string;
  requestId?: string;
  method: string;
  url: string;
  httpStatus: number;
  retryAfterMs?: number;
}): AdminApiError {
  const network = init.code === 'NETWORK_ERROR';
  return new AdminApiError({
    code: init.code ?? 'INTERNAL_ERROR',
    message: i18n.t(network ? 'errors.network.message' : 'errors.system.message'),
    httpStatus: init.httpStatus,
    requestId: init.requestId,
    method: init.method,
    url: init.url,
    system: true,
    retryAfterMs: init.retryAfterMs,
  });
}

function readRetryAfter(res: Response): number | undefined {
  const value = res.headers.get('retry-after');
  if (value === null) {
    return undefined;
  }
  const seconds = Number(value);
  return Number.isFinite(seconds) && seconds >= 0 ? seconds * 1000 : undefined;
}

/** 401 分流用的契约错误码；HTTP 401 本身也按会话失效处理。 */
const SESSION_EXPIRED_CODES = new Set(['UNAUTHENTICATED', 'SESSION_EXPIRED']);

/**
 * 把该全局动作的错误发到事件总线；业务错误照常抛给调用方。
 * 503+Retry-After 不在此列——它是要重试的临时故障，不当登出。
 */
function dispatchAdminError(error: AdminApiError): void {
  if (error.httpStatus === 401 || SESSION_EXPIRED_CODES.has(error.code)) {
    eventBus.emit('session:expired', { message: error.message });
  }
  if (error.code === 'SUDO_REQUIRED') {
    eventBus.emit('sudo:required', { error });
  }
}

interface SuccessEnvelope {
  code?: string;
  data?: unknown;
  request_id?: string;
}

interface ErrorEnvelope {
  code?: string;
  message?: string;
  fields?: Record<string, string[]>;
  request_id?: string;
}

async function request<T>(
  method: HttpMethod,
  path: string,
  body: unknown,
  options?: AdminRequestOptions<T>,
): Promise<T> {
  const url = buildUrl(path, options?.query);
  eventBus.emit('request:begin', { url, method });
  try {
    const headers: Record<string, string> = { Accept: 'application/json' };
    if (body !== undefined) {
      headers['Content-Type'] = 'application/json';
    }
    if (method !== 'GET') {
      headers['Idempotency-Key'] = options?.idempotencyKey ?? crypto.randomUUID();
    }
    const token = getToken();
    if (token) {
      headers.Authorization = `Bearer ${token}`;
    }

    let res: Response;
    try {
      res = await fetch(url, {
        method,
        headers,
        credentials: 'same-origin',
        body: body === undefined ? undefined : JSON.stringify(body),
        signal: options?.signal,
      });
    } catch {
      throw systemError({ code: 'NETWORK_ERROR', method, url, httpStatus: 0 });
    }

    trackAuthzVersion(res);

    const headerRequestId = res.headers.get('x-request-id') ?? undefined;
    const status = res.status;
    const retryAfterMs = readRetryAfter(res);

    let text = '';
    try {
      text = await res.text();
    } catch {
      throw systemError({ requestId: headerRequestId, method, url, httpStatus: status, retryAfterMs });
    }

    let payload: unknown = null;
    if (text) {
      try {
        payload = JSON.parse(text);
      } catch {
        payload = null;
      }
    }

    if (!res.ok) {
      const errPayload = (payload ?? {}) as ErrorEnvelope;
      const requestId = errPayload.request_id ?? headerRequestId;
      if (status >= 500 || typeof errPayload.code !== 'string') {
        const error = systemError({
          code: typeof errPayload.code === 'string' ? errPayload.code : undefined,
          requestId,
          method,
          url,
          httpStatus: status,
          retryAfterMs,
        });
        dispatchAdminError(error);
        throw error;
      }
      const error = new AdminApiError({
        code: errPayload.code,
        message: errPayload.message || i18n.t('errors.system.message'),
        fields: errPayload.fields,
        httpStatus: status,
        requestId,
        method,
        url,
        system: false,
        retryAfterMs,
      });
      dispatchAdminError(error);
      throw error;
    }

    // 成功信封唯一合法形态：{code:'OK', message, data, request_id}；
    // 成功判定只看 code==='OK'，"只要存在 data 就算成功"的旧宽松判定已撤。
    if (
      typeof payload !== 'object' ||
      payload === null ||
      Array.isArray(payload) ||
      (payload as SuccessEnvelope).code !== 'OK'
    ) {
      const headerId =
        typeof payload === 'object' && payload !== null
          ? ((payload as SuccessEnvelope).request_id ?? headerRequestId)
          : headerRequestId;
      throw systemError({ requestId: headerId, method, url, httpStatus: status });
    }

    const envelope = payload as SuccessEnvelope;
    const data = envelope.data as T;
    try {
      return options?.schema ? options.schema.parse(data) : data;
    } catch {
      throw systemError({ requestId: envelope.request_id ?? headerRequestId, method, url, httpStatus: status });
    }
  } finally {
    eventBus.emit('request:end', { url, method });
  }
}

async function requestDownload(
  path: string,
  options?: AdminRequestOptions<Blob>,
): Promise<DownloadedFile> {
  const url = buildUrl(path, options?.query);
  eventBus.emit('request:begin', { url, method: 'GET' });
  try {
    const headers: Record<string, string> = { Accept: '*/*' };
    const token = getToken();
    if (token) {
      headers.Authorization = `Bearer ${token}`;
    }

    let res: Response;
    try {
      res = await fetch(url, {
        method: 'GET',
        headers,
        credentials: 'same-origin',
        signal: options?.signal,
      });
    } catch {
      throw systemError({ code: 'NETWORK_ERROR', method: 'GET', url, httpStatus: 0 });
    }

    trackAuthzVersion(res);
    const status = res.status;
    const retryAfterMs = readRetryAfter(res);
    const contentType = res.headers.get('content-type') ?? '';

    // 下载端点的错误响应仍走 JSON 信封。
    if (!res.ok || contentType.includes('application/json')) {
      let payload: ErrorEnvelope | null = null;
      try {
        payload = (await res.json()) as ErrorEnvelope;
      } catch {
        payload = null;
      }
      const requestId = payload?.request_id ?? res.headers.get('x-request-id') ?? undefined;
      if (status >= 500 || !payload || typeof payload.code !== 'string') {
        const error = systemError({ requestId, method: 'GET', url, httpStatus: status, retryAfterMs });
        dispatchAdminError(error);
        throw error;
      }
      const error = new AdminApiError({
        code: payload.code,
        message: payload.message || i18n.t('errors.system.message'),
        fields: payload.fields,
        httpStatus: status,
        requestId,
        method: 'GET',
        url,
        system: status >= 500,
        retryAfterMs,
      });
      dispatchAdminError(error);
      throw error;
    }

    const disposition = res.headers.get('content-disposition') ?? '';
    const match = /filename\*=UTF-8''([^;]+)|filename="?([^";]+)"?/i.exec(disposition);
    const fileName = match ? decodeURIComponent(match[1] ?? match[2] ?? '') : null;
    return { blob: await res.blob(), fileName };
  } finally {
    eventBus.emit('request:end', { url, method: 'GET' });
  }
}

export function adminGet<T>(path: string, options?: AdminRequestOptions<T>): Promise<T> {
  return request('GET', path, undefined, options);
}

export function adminPost<T>(
  path: string,
  body?: unknown,
  options?: AdminRequestOptions<T>,
): Promise<T> {
  return request('POST', path, body, options);
}

export function adminPut<T>(
  path: string,
  body?: unknown,
  options?: AdminRequestOptions<T>,
): Promise<T> {
  return request('PUT', path, body, options);
}

export function adminPatch<T>(
  path: string,
  body?: unknown,
  options?: AdminRequestOptions<T>,
): Promise<T> {
  return request('PATCH', path, body, options);
}

export function adminDelete<T>(
  path: string,
  body?: unknown,
  options?: AdminRequestOptions<T>,
): Promise<T> {
  return request('DELETE', path, body, options);
}

/** multipart 上传（如 /files）；FormData 不带手写 Content-Type，浏览器自带 boundary。 */
export async function adminUpload<T>(
  path: string,
  form: FormData,
  options?: AdminRequestOptions<T>,
): Promise<T> {
  const url = buildUrl(path, options?.query);
  eventBus.emit('request:begin', { url, method: 'POST' });
  try {
    const headers: Record<string, string> = {
      Accept: 'application/json',
      'Idempotency-Key': options?.idempotencyKey ?? crypto.randomUUID(),
    };
    const token = getToken();
    if (token) {
      headers.Authorization = `Bearer ${token}`;
    }

    let res: Response;
    try {
      res = await fetch(url, {
        method: 'POST',
        headers,
        credentials: 'same-origin',
        body: form,
        signal: options?.signal,
      });
    } catch {
      throw systemError({ code: 'NETWORK_ERROR', method: 'POST', url, httpStatus: 0 });
    }

    trackAuthzVersion(res);
    const headerRequestId = res.headers.get('x-request-id') ?? undefined;
    const status = res.status;
    const retryAfterMs = readRetryAfter(res);

    let payload: unknown = null;
    try {
      payload = await res.json();
    } catch {
      payload = null;
    }

    if (!res.ok) {
      const errPayload = (payload ?? {}) as ErrorEnvelope;
      const requestId = errPayload.request_id ?? headerRequestId;
      if (status >= 500 || typeof errPayload.code !== 'string') {
        const error = systemError({
          code: typeof errPayload.code === 'string' ? errPayload.code : undefined,
          requestId,
          method: 'POST',
          url,
          httpStatus: status,
          retryAfterMs,
        });
        dispatchAdminError(error);
        throw error;
      }
      const error = new AdminApiError({
        code: errPayload.code,
        message: errPayload.message || i18n.t('errors.system.message'),
        fields: errPayload.fields,
        httpStatus: status,
        requestId,
        method: 'POST',
        url,
        system: false,
        retryAfterMs,
      });
      dispatchAdminError(error);
      throw error;
    }

    if (
      typeof payload !== 'object' ||
      payload === null ||
      Array.isArray(payload) ||
      (payload as SuccessEnvelope).code !== 'OK'
    ) {
      throw systemError({ requestId: headerRequestId, method: 'POST', url, httpStatus: status });
    }
    const envelope = payload as SuccessEnvelope;
    const data = envelope.data as T;
    try {
      return options?.schema ? options.schema.parse(data) : data;
    } catch {
      throw systemError({
        requestId: envelope.request_id ?? headerRequestId,
        method: 'POST',
        url,
        httpStatus: status,
      });
    }
  } finally {
    eventBus.emit('request:end', { url, method: 'POST' });
  }
}

/** 二进制下载（如 /tasks/{id}/download）；失败抛 AdminApiError。 */
export function adminDownload(path: string, options?: AdminRequestOptions<Blob>): Promise<DownloadedFile> {
  return requestDownload(path, options);
}
