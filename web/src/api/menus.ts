import { apiGet } from '@/lib/apiClient';

export interface MenuItem {
  id: string;
  parentId: string | null;
  title: string;
  path: string;
  icon?: string;
  order: number;
  children?: MenuItem[];
}

export function getMyMenus(): Promise<MenuItem[]> {
  return apiGet<MenuItem[]>('/me/menus');
}
