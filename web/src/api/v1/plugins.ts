import { adminGet, adminPost, adminUpload } from '@/lib/adminClient';
import type { RequestAccepted } from './lifecycle';
import type { TaskState } from './types';

/** 插件管理页头信息（构建形态与能力开关）。 */
export interface PluginsOverview {
  /** 编译版本：网页上不能安装或升级，只显示版本信息。 */
  compiled: boolean;
  /** 未编译且超级管理员可网页安装/升级时为 true。 */
  package_ops_enabled: boolean;
  signature_required: boolean;
}

export interface Plugin {
  id: string;
  /** 插件编码，路径参数 plugin_code 取此值。 */
  code: string;
  name: string;
  version: string;
  state: 'installed' | 'uninstalled';
  /** 进行中生命周期申请的状态；无进行中申请为 null。 */
  task_state: TaskState | null;
  /** 上次安装/升级失败且已自动恢复。 */
  rolled_back: boolean;
  signature_verified: boolean;
  description: string | null;
  /** 菜单/权限/设置分类等清单摘要；结构随插件而定，读取时须做类型守卫。 */
  provides: Record<string, unknown> | null;
  install_time: string | null;
}

export interface PluginMarketItem extends Plugin {
  latest_version: string;
  installed: boolean;
  upgrade_available: boolean;
}

export function getPluginsOverview(): Promise<PluginsOverview> {
  return adminGet<PluginsOverview>('/plugins/overview');
}

export function listMarketPlugins(): Promise<{ items: PluginMarketItem[] }> {
  return adminGet<{ items: PluginMarketItem[] }>('/plugins/market');
}

export function listInstalledPlugins(): Promise<{ items: Plugin[] }> {
  return adminGet<{ items: Plugin[] }>('/plugins/installed');
}

export function getPlugin(pluginCode: string): Promise<Plugin> {
  return adminGet<Plugin>(`/plugins/${encodeURIComponent(pluginCode)}`);
}

export function installPlugin(pluginCode: string): Promise<RequestAccepted> {
  return adminPost<RequestAccepted>(`/plugins/${encodeURIComponent(pluginCode)}/install`);
}

export function upgradePlugin(pluginCode: string): Promise<RequestAccepted> {
  return adminPost<RequestAccepted>(`/plugins/${encodeURIComponent(pluginCode)}/upgrade`);
}

export function uninstallPlugin(pluginCode: string, purgeData: boolean): Promise<RequestAccepted> {
  return adminPost<RequestAccepted>(`/plugins/${encodeURIComponent(pluginCode)}/uninstall`, {
    purge_data: purgeData,
  });
}

export function upgradeAllPlugins(): Promise<RequestAccepted> {
  return adminPost<RequestAccepted>('/plugins/upgrade-all');
}

export type PluginUploadMode = 'install' | 'upgrade';

export function uploadPluginPackage(file: File, mode: PluginUploadMode): Promise<RequestAccepted> {
  const form = new FormData();
  form.append('package', file);
  form.append('mode', mode);
  return adminUpload<RequestAccepted>('/plugins/upload', form);
}
