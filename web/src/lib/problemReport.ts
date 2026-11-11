import i18n from '@/i18n/i18n';

import type { ApiError } from './apiClient';

function currentPageUrl(): string {
  return typeof window === 'undefined' ? '' : window.location.href;
}

/**
 * Plain-text diagnostic block behind the "copy problem info" action. It only
 * carries the timestamp, page URL, request method/URL, HTTP status and request
 * id — never tokens, passwords or profile data.
 */
export function buildProblemReport(error: ApiError): string {
  const lines = [
    `${i18n.t('errors.system.report.time')}: ${new Date().toISOString()}`,
    `${i18n.t('errors.system.report.page')}: ${currentPageUrl()}`,
  ];
  if (error.method) {
    lines.push(`${i18n.t('errors.system.report.method')}: ${error.method}`);
  }
  if (error.url) {
    lines.push(`${i18n.t('errors.system.report.path')}: ${error.url}`);
  }
  if (typeof error.status === 'number') {
    lines.push(`${i18n.t('errors.system.report.status')}: ${error.status}`);
  }
  if (error.requestId) {
    lines.push(`${i18n.t('errors.system.report.requestId')}: ${error.requestId}`);
  }
  return lines.join('\n');
}
