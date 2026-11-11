import {
  adminDelete,
  adminGet,
  adminPatch,
  adminPost,
  adminPut,
  type AdminQuery,
} from '@/lib/adminClient';
import type { EmptyResult, Id } from './types';

export type MenuNodeType = 'group' | 'page' | 'link';
export type MenuSourceKind = 'system' | 'plugin' | 'custom';

export interface MenuSource {
  kind: MenuSourceKind;
  plugin_label?: string | null;
}

export interface MenuLocked {
  move: boolean;
  hide: boolean;
}

export interface MenuNode {
  key: string;
  type: MenuNodeType;
  title: string;
  original_title?: string | null;
  icon?: string | null;
  source?: MenuSource;
  visible: boolean;
  locked?: MenuLocked;
  new_until?: string | null;
  rename_suggested?: boolean;
  url?: string | null;
  children?: MenuNode[];
}

export interface MenuTree {
  menu_version: number;
  items: MenuNode[];
}

export interface MenuTreeQuery extends AdminQuery {
  keyword?: string;
  source?: MenuSourceKind;
}

export interface MenuPatchBody {
  title?: string;
  icon?: string | null;
  visible?: boolean | null;
}

export interface MenuMoveBody {
  parent_key: string | null;
  index: number;
  base_version: number;
}

export interface MenuVisibleBody {
  visible: boolean;
  base_version?: number;
}

export interface MenuGroupBody {
  title: string;
  icon?: string | null;
}

export interface MenuLinkBody {
  title: string;
  url: string;
  icon?: string | null;
  parent_key?: string | null;
}

function menuPath(menuKey: string): string {
  return `/menus/${encodeURIComponent(menuKey)}`;
}

export function getMenuTree(query?: MenuTreeQuery): Promise<MenuTree> {
  return adminGet<MenuTree>('/menus/tree', { query });
}

/** 覆盖层修改：标题/图标/可见性（系统、插件菜单走覆盖层，自建菜单直接改）。 */
export function updateMenu(menuKey: string, body: MenuPatchBody): Promise<EmptyResult> {
  return adminPatch<EmptyResult>(menuPath(menuKey), body);
}

/** 只允许删除自建分组/外部链接；系统与插件菜单返回错误。 */
export function deleteMenu(menuKey: Id): Promise<EmptyResult> {
  return adminDelete<EmptyResult>(menuPath(menuKey));
}

export function moveMenu(menuKey: string, body: MenuMoveBody): Promise<EmptyResult> {
  return adminPost<EmptyResult>(`${menuPath(menuKey)}/move`, body);
}

export function setMenuVisible(menuKey: string, body: MenuVisibleBody): Promise<EmptyResult> {
  return adminPut<EmptyResult>(`${menuPath(menuKey)}/visible`, body);
}

export function createMenuGroup(body: MenuGroupBody): Promise<{ key: string }> {
  return adminPost<{ key: string }>('/menus/groups', body);
}

export function createMenuLink(body: MenuLinkBody): Promise<{ key: string }> {
  return adminPost<{ key: string }>('/menus/links', body);
}

export function resetMenus(): Promise<{ menu_version: number }> {
  return adminPost<{ menu_version: number }>('/menus/reset');
}
