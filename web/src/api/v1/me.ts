import { adminGet } from '@/lib/adminClient';
import type { User } from './users';

/** 侧栏菜单与工作台快捷入口共用的查询键；菜单写操作成功后失效它。 */
export const MY_MENUS_QUERY_KEY = ['me', 'menus'] as const;

export type MenuNodeKind = 'group' | 'page' | 'link';

/** /me/menus 返回的精简菜单节点（带站内 path 或外链 url）。 */
export interface MyMenuNode {
  key: string;
  type: MenuNodeKind;
  title: string;
  icon: string | null;
  path: string | null;
  url: string | null;
  children?: MyMenuNode[];
}

export interface MyMenuTree {
  menu_version: number;
  items: MyMenuNode[];
}

export function getMe(): Promise<User> {
  return adminGet<User>('/me');
}

export function getMyMenus(): Promise<MyMenuTree> {
  return adminGet<MyMenuTree>('/me/menus');
}
