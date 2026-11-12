import { adminGet, adminPut, adminUpload } from '@/lib/adminClient';
import type { Id } from './types';

export interface SettingGroup {
  key: string;
  title: string;
  owner?: 'system' | 'plugin';
  plugin_code?: string | null;
}

export interface SettingOption {
  value: string | number;
  label: string;
}

/**
 * GET /settings/{group} 项。key/技术名不展示；has_value=false 的项
 * 后端只回「是否设置过」，页面按只读说明渲染。
 */
export interface SettingItem {
  key: string;
  label: string;
  type: 'text' | 'textarea' | 'number' | 'boolean' | 'select' | 'image';
  value: string | number | boolean | null;
  has_value: boolean;
  options?: SettingOption[] | null;
  remark?: string | null;
  /** select 可清空时，清空后落到的默认值（界面上给说明用）。 */
  default_value?: string | number | null;
}

export function getSettingGroups(): Promise<{ items: SettingGroup[] }> {
  return adminGet<{ items: SettingGroup[] }>('/settings/groups');
}

export function getSettings(group: string): Promise<{ items: SettingItem[] }> {
  return adminGet<{ items: SettingItem[] }>(`/settings/${encodeURIComponent(group)}`);
}

export function updateSettings(
  group: string,
  values: Record<string, string | number | boolean | null>,
): Promise<{ items: SettingItem[] }> {
  return adminPut<{ items: SettingItem[] }>(
    `/settings/${encodeURIComponent(group)}`,
    { values },
  );
}

export function uploadSettingLogo(file: File): Promise<{ file_id: Id; url: string }> {
  const form = new FormData();
  form.append('file', file);
  return adminUpload<{ file_id: Id; url: string }>('/settings/logo', form);
}
