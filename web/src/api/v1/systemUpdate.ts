import { adminGet, adminPost, adminUpload } from '@/lib/adminClient';
import type { LifecycleRequest, RequestAccepted } from './lifecycle';

/** 随系统升级一起更新的插件（bundled_plugins 元素）。 */
export interface BundledPlugin {
  key: string;
  /** 插件中文名。 */
  name: string;
}

/** 系统版本与更新状态（GET /system/update/status）。 */
export interface UpdateStatus {
  current_version: string;
  latest_version: string | null;
  update_available: boolean;
  /** 「你会看到的变化」中文条目（3–5 条大白话）。 */
  changes: string[];
  bundled_plugins: BundledPlugin[];
  checked_time: string | null;
  compiled: boolean;
  package_ops_enabled: boolean;
  /** 进行中的系统升级生命周期申请；无则 null。 */
  running_request: LifecycleRequest | null;
}

export function getUpdateStatus(): Promise<UpdateStatus> {
  return adminGet<UpdateStatus>('/system/update/status');
}

export function checkUpdate(): Promise<UpdateStatus> {
  return adminPost<UpdateStatus>('/system/update/check');
}

export function uploadUpdatePackage(file: File): Promise<RequestAccepted> {
  const form = new FormData();
  form.append('package', file);
  return adminUpload<RequestAccepted>('/system/update/upload', form);
}

export interface UpdateStartBody {
  /** 上传包对应的文件引用；在线升级时可为空。 */
  package_file_id?: string;
  target_version?: string;
}

export function startUpdate(body?: UpdateStartBody): Promise<RequestAccepted> {
  return adminPost<RequestAccepted>('/system/update/start', body ?? {});
}
