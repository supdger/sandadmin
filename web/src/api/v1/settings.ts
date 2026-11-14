import { adminGet, adminPut, adminUpload } from '@/lib/adminClient';
import type { FileObject } from './files';

export interface SettingGroup {
  key: string;
  title: string;
  /** host=宿主内置组；plugin=插件组（组副标走「一般不用改」按组 key 判）。 */
  owner?: 'host' | 'plugin';
  plugin_code?: string | null;
}

export interface SettingOption {
  value: string | number;
  label: string;
}

/**
 * GET /settings/{group} 项（契约 SettingItem）。key/技术名不展示；
 * type=password 或 has_value=false 的敏感项后端只回「是否设置过」，
 * 页面按只读说明渲染。
 */
export interface SettingItem {
  key: string;
  label: string;
  type: 'text' | 'number' | 'boolean' | 'select' | 'password' | 'image';
  value: string | number | boolean | null;
  has_value: boolean;
  options?: SettingOption[] | null;
  remark?: string | null;
  /** select 可清空时，清空后落到的默认值（界面上给说明用）。 */
  default_value?: string | number | null;
}

/** 契约：GET/PUT 都回 {group, items}，不是裸数组。 */
export interface SettingsPayload {
  group: string;
  items: SettingItem[];
}

export function getSettingGroups(): Promise<{ items: SettingGroup[] }> {
  return adminGet<{ items: SettingGroup[] }>('/settings/groups');
}

export function getSettings(group: string): Promise<SettingsPayload> {
  return adminGet<SettingsPayload>(`/settings/${encodeURIComponent(group)}`);
}

export function updateSettings(
  group: string,
  values: Record<string, string | number | boolean | null>,
): Promise<SettingsPayload> {
  return adminPut<SettingsPayload>(`/settings/${encodeURIComponent(group)}`, { values });
}

/**
 * POST /settings/logo 回契约 FileObject：把返回的 id（file_id，int）
 * 写进 site_logo 设置项保存；预览/渲染走 /files/{id}/download。
 */
export function uploadSettingLogo(file: File): Promise<FileObject> {
  const form = new FormData();
  form.append('file', file);
  return adminUpload<FileObject>('/settings/logo', form);
}
