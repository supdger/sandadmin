import i18n from '@/i18n/i18n';

import { AdminApiError } from '@/lib/adminClient';

/**
 * 用户可见的错误文案。AdminApiError.message 已经是安全文案：业务错误是后端
 * 的大白话原因，系统错误是统一兜底文案；非 AdminApiError 返回调用方兜底。
 */
export function adminErrorMessage(error: unknown, fallback: string): string {
  return error instanceof AdminApiError ? error.message : fallback;
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
