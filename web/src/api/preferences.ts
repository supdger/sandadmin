// 遗留（FE0 偏好设置页）：仍走旧 apiClient + 旧信封。v1 契约的 Preferences
// 字段集合不同（theme=density/tabs_enabled/sider_open/locale），页面改造属 FE0 范围。
import { apiGet, apiPut } from '@/lib/apiClient';

export interface Preferences {
  theme: 'light' | 'dark' | 'auto';
  [k: string]: unknown;
}

export function getPreferences(): Promise<Preferences> {
  return apiGet<Preferences>('/me/preferences');
}

export function putPreferences(p: Preferences): Promise<void> {
  return apiPut('/me/preferences', p);
}
