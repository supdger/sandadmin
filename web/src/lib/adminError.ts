import i18n from '@/i18n/i18n';

import { messageForErrorCode } from '@/api/errorMessages';
import { AdminApiError } from '@/lib/adminClient';

/**
 * 用户可见的错误文案。业务错误用后端的大白话；并发冲突统一成产品定死的那句，
 * 避免各页各写一套。非 AdminApiError 返回调用方兜底。
 */
export function adminErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof AdminApiError && error.code === 'STALE_VERSION') {
    return messageForErrorCode(error.code);
  }
  return error instanceof AdminApiError ? error.message : fallback;
}

/**
 * 写操作的错误文案：并发冲突（STALE_VERSION）用产品定死的口径，
 * 其余照旧——业务错误展示后端大白话，系统错误展示统一兜底。
 */
export function adminSaveErrorMessage(
  error: unknown,
  fallback: string,
  conflict: string,
): string {
  if (error instanceof AdminApiError && error.code === 'STALE_VERSION') {
    return conflict;
  }
  return adminErrorMessage(error, fallback);
}

/**
 * 「复制问题信息」用的纯文本诊断块，与 lib/problemReport 同构，
 * 只带时间、页面、请求方法/路径、HTTP 状态和请求编号。
 */
export function adminProblemReport(error: AdminApiError): string {
  const lines = [
    `${i18n.t('errors.system.report.time')}: ${new Date().toISOString()}`,
    `${i18n.t('errors.system.report.page')}: ${typeof window === 'undefined' ? '' : window.location.href}`,
  ];
  if (error.method) {
    lines.push(`${i18n.t('errors.system.report.method')}: ${error.method}`);
  }
  if (error.url) {
    lines.push(`${i18n.t('errors.system.report.path')}: ${error.url}`);
  }
  if (typeof error.httpStatus === 'number' && error.httpStatus > 0) {
    lines.push(`${i18n.t('errors.system.report.status')}: ${error.httpStatus}`);
  }
  if (error.requestId) {
    lines.push(`${i18n.t('errors.system.report.requestId')}: ${error.requestId}`);
  }
  return lines.join('\n');
}
