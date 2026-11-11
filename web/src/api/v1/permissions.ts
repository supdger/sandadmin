import { adminGet } from '@/lib/adminClient';

export interface PermissionAction {
  code: string;
  action: string;
  label: string;
  description?: string | null;
}

export interface PermissionMenu {
  menu_key: string;
  title: string;
  description?: string | null;
  actions: PermissionAction[];
}

export interface PermissionGroup {
  group_key: string;
  group_title: string;
  menus: PermissionMenu[];
}

export function getPermissionTree(): Promise<{ items: PermissionGroup[] }> {
  return adminGet<{ items: PermissionGroup[] }>('/permission-tree');
}
