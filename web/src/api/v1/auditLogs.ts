import { adminGet, adminPost, type AdminQuery } from '@/lib/adminClient';
import type { Id, Page, TaskAccepted } from './types';

/**
 * GET /audit-logs 列表行/详情（契约 AuditLog）。
 * action_text 是后端生成的人话（如「停用了用户张三」），页面不拼、不改写。
 * changes 是对象 map：键→改后值，或键→{before, after}；键的中文名看
 * change_labels，未知键直接回显键名兜底。
 */
export interface AuditLog {
  id: Id;
  actor_type: 'user' | 'system';
  actor_id: Id | null;
  /** 冗余姓名，用户删除后仍可读。 */
  actor_name: string;
  /** 所属功能标识（module 查询参数同源）。 */
  module: string;
  /** 功能中文名（派生展示），可能为 null。 */
  module_label: string | null;
  action: string;
  action_text: string;
  target_type: string;
  target_id: Id;
  target_label: string;
  succeeded: boolean;
  /** 改前/改后差异；口令/密钥类字段后端已替换为「已修改」标记。 */
  changes: Record<string, unknown> | null;
  /** 改动键 → 中文显示名（后端补充的字段；未给则回显键名）。 */
  change_labels?: Record<string, string> | null;
  ip: string | null;
  /** 城市级地点；空字符串表示没有地点。 */
  location: string;
  /** 设备描述；空字符串表示没有设备。 */
  device_label: string;
  /** 原始浏览器串：只进「复制问题信息」，页面不渲染。 */
  user_agent?: string | null;
  request_id: string;
  create_time: string;
}

export interface AuditModuleOption {
  /** 功能标识（宿主模块键或插件编码）。 */
  key: string;
  /** 功能中文名。 */
  name: string;
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

export function getAuditLog(logId: Id): Promise<AuditLog> {
  return adminGet<AuditLog>(`/audit-logs/${encodeURIComponent(logId)}`);
}

/** 契约：data 直接是选项数组 [{key, name}]，不是 {items}。 */
export function getAuditModuleOptions(): Promise<AuditModuleOption[]> {
  return adminGet<AuditModuleOption[]>('/audit-logs/module-options');
}

/** 导出跟随当前筛选；受理后轮询 /tasks/{id} 拿文件。 */
export function exportAuditLogs(query?: AuditLogQuery): Promise<TaskAccepted> {
  return adminPost<TaskAccepted>('/audit-logs/export', query ?? {});
}
