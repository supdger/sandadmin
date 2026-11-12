import { adminGet, adminPost, type AdminQuery } from '@/lib/adminClient';
import type { Id, Page, TaskAccepted } from './types';

/**
 * GET /audit-logs 列表行。action_text 是后端生成的人话（如「停用了用户张三」），
 * 页面不拼、不改写；details 只在抽屉里展示改前/改后。
 */
export interface AuditLog {
  id: Id;
  actor_id?: Id | null;
  actor_name: string | null;
  action_text: string;
  module_key: string | null;
  module_label: string | null;
  succeeded: boolean;
  create_time: string;
}

export interface AuditLogChange {
  label: string;
  before: string | null;
  after: string | null;
}

export interface AuditLogDetail extends AuditLog {
  changes?: AuditLogChange[] | null;
  remark?: string | null;
}

export interface AuditModuleOption {
  key: string;
  label: string;
}

export interface AuditLogQuery extends AdminQuery {
  keyword?: string;
  actor_id?: Id;
  module?: string;
  succeeded?: boolean;
  create_time_from?: string;
  create_time_to?: string;
  page?: number;
  page_size?: number;
}

export function listAuditLogs(query?: AuditLogQuery): Promise<Page<AuditLog>> {
  return adminGet<Page<AuditLog>>('/audit-logs', { query });
}

export function getAuditLog(logId: Id): Promise<AuditLogDetail> {
  return adminGet<AuditLogDetail>(`/audit-logs/${encodeURIComponent(logId)}`);
}

export function getAuditModuleOptions(): Promise<{ items: AuditModuleOption[] }> {
  return adminGet<{ items: AuditModuleOption[] }>('/audit-logs/module-options');
}

/** 导出跟随当前筛选；受理后轮询 /tasks/{id} 拿文件。 */
export function exportAuditLogs(query?: AuditLogQuery): Promise<TaskAccepted> {
  return adminPost<TaskAccepted>('/audit-logs/export', query ?? {});
}
