import { adminGet, adminPost, type AdminQuery } from '@/lib/adminClient';
import type { Id, Page, TaskAccepted } from './types';

/** GET /login-logs 列表行：ip/登录设备地址只进详情与导出，不进列表。 */
export interface LoginLog {
  id: Id;
  user_id?: Id | null;
  nickname: string | null;
  username?: string | null;
  succeeded: boolean;
  /** 后端大白话失败原因；成功或未知时为 null，页面按规则兜底。 */
  reason: string | null;
  ip?: string | null;
  location: string | null;
  device_label: string | null;
  login_time: string;
}

export interface LoginLogDetail extends LoginLog {
  ip: string | null;
}

export interface LoginLogQuery extends AdminQuery {
  keyword?: string;
  succeeded?: boolean;
  login_time_from?: string;
  login_time_to?: string;
  page?: number;
  page_size?: number;
}

export function listLoginLogs(query?: LoginLogQuery): Promise<Page<LoginLog>> {
  return adminGet<Page<LoginLog>>('/login-logs', { query });
}

export function getLoginLog(logId: Id): Promise<LoginLogDetail> {
  return adminGet<LoginLogDetail>(`/login-logs/${encodeURIComponent(logId)}`);
}

/** 导出跟随当前筛选；受理后轮询 /tasks/{id} 拿文件。 */
export function exportLoginLogs(query?: LoginLogQuery): Promise<TaskAccepted> {
  return adminPost<TaskAccepted>('/login-logs/export', query ?? {});
}
