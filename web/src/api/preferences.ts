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
