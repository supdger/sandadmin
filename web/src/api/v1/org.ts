import { adminGet, type AdminQuery } from '@/lib/adminClient';
import type { Id, UserStatus } from './types';

export interface UserOption {
  id: Id;
  nickname: string;
  avatar: string | null;
  dept_id: Id;
  dept_name: string | null;
  status: UserStatus;
}

export interface DeptOption {
  id: Id;
  parent_id: Id | null;
  name: string;
  sort: number;
}

export interface UserOptionQuery extends AdminQuery {
  keyword?: string;
  dept_id?: Id;
}

/** 已登录即可用的精简选人清单（不分页，含已停用）。 */
export function getUserOptions(query?: UserOptionQuery): Promise<{ items: UserOption[] }> {
  return adminGet<{ items: UserOption[] }>('/org/user-options', { query });
}

/** 已登录即可用的精简选部门清单（只回启用部门，平铺按 sort）。 */
export function getDeptOptions(): Promise<{ items: DeptOption[] }> {
  return adminGet<{ items: DeptOption[] }>('/org/dept-options');
}
