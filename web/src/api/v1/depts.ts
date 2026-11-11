import { adminDelete, adminGet, adminPost, adminPut, type AdminQuery } from '@/lib/adminClient';
import type { EmptyResult, Id, UserStatus } from './types';

export interface Dept {
  id: Id;
  parent_id: Id | null;
  name: string;
  leader_id: Id | null;
  leader_name: string | null;
  leader_avatar: string | null;
  leader_status: UserStatus | null;
  phone: string | null;
  sort: number;
  status: UserStatus;
  user_count: number;
  children: Dept[];
}

export interface DeptWrite {
  parent_id?: Id | null;
  name: string;
  leader_id?: Id | null;
  phone?: string | null;
  sort?: number;
  status?: UserStatus;
}

export interface DeptTreeQuery extends AdminQuery {
  keyword?: string;
  status?: UserStatus;
}

function deptPath(deptId: Id): string {
  return `/depts/${encodeURIComponent(deptId)}`;
}

export function getDeptTree(query?: DeptTreeQuery): Promise<{ items: Dept[] }> {
  return adminGet<{ items: Dept[] }>('/depts/tree', { query });
}

export function createDept(body: DeptWrite): Promise<Dept> {
  return adminPost<Dept>('/depts', body);
}

export function updateDept(deptId: Id, body: DeptWrite): Promise<Dept> {
  return adminPut<Dept>(deptPath(deptId), body);
}

export function deleteDept(deptId: Id): Promise<EmptyResult> {
  return adminDelete<EmptyResult>(deptPath(deptId));
}
