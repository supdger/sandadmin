import { adminGet } from '@/lib/adminClient';
import type { TaskState } from './types';

/**
 * 生命周期申请（sand_system_lifecycle_request）：插件安装/升级/卸载、
 * 批量升级、系统升级。网页只登记，独立进程执行；进度经
 * GET /lifecycle-requests/{request_id} 轮询。
 */

export type LifecycleKind =
  | 'plugin_install'
  | 'plugin_upgrade'
  | 'plugin_uninstall'
  | 'plugin_upgrade_batch'
  | 'system_upgrade';

/** 系统升级固定阶段标识；插件类申请的 stage_key 为 null。 */
export type LifecycleStageKey = 'check' | 'backup' | 'download' | 'switch' | 'verify';

export interface LifecycleRequestItem {
  code: string;
  from_version: string | null;
  to_version: string | null;
  state: TaskState;
}

export interface LifecycleRequest {
  id: string;
  kind: LifecycleKind;
  /** 插件编码；系统升级为 host，批量升级为 *。 */
  target_code: string;
  source: 'official' | 'upload';
  from_version: string | null;
  to_version: string | null;
  purge_data: boolean;
  /** 批量申请（plugin_upgrade_batch）的逐插件进度；单项为 null。 */
  items: LifecycleRequestItem[] | null;
  state: TaskState;
  /** 执行阶段的人话描述，页面原样展示，不自己编。 */
  stage: string | null;
  stage_key: LifecycleStageKey | null;
  progress: number;
  /** 失败且已自动恢复。 */
  rolled_back: boolean;
  error_code: string | null;
  error_message: string | null;
  create_time: string;
  start_time: string | null;
  finish_time: string | null;
}

/** 生命周期申请受理回执；进度用 request_id 轮询。 */
export interface RequestAccepted {
  request_id: string;
  state: 'queued';
}

export function getLifecycleRequest(requestId: string): Promise<LifecycleRequest> {
  return adminGet<LifecycleRequest>(`/lifecycle-requests/${encodeURIComponent(requestId)}`);
}
