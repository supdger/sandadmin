import { adminGet, adminPost, type AdminQuery } from '@/lib/adminClient';
import type { Id, Page, TaskAccepted } from './types';

/**
 * GET /login-logs 列表行（契约 LoginLog）：列表不返回 ip、
 * 原始 user_agent 与 user_id——这些只在详情里出现。
 */
export interface LoginLog {
  id: Id;
  /** 输入的账号原文，账号不存在也记录。 */
  username: string;
  nickname: string | null;
  succeeded: boolean;
  /** 失败原因枚举码；成功为 null。 */
  reason: string | null;
  /** 后端中文人话失败原因；成功或未识别为 null，页面显示为空/兜底。 */
  reason_text: string | null;
  /** 城市级地点（NOT NULL）；内网由后端给内部网络标记。 */
  location: string;
  /** 设备描述（NOT NULL），如「Mac 上的 Chrome」。 */
  device_label: string;
  login_time: string;
}

/** 详情：LoginLog + ip、user_agent（登录设备地址原文）、user_id。 */
export interface LoginLogDetail extends LoginLog {
  /** 对应账号编号；查不到用户（账号不存在）时为 null，「查看用户」不渲染。 */
  user_id: Id | null;
  /** 登录来源地址，仅详情与导出返回。 */
  ip: string;
  /** 原始浏览器字符串，即详情「登录设备地址」。 */
  user_agent: string;
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
