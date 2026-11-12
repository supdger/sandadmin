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
  /** 乐观锁版本：编辑提交时按原值回传 base_version。 */
  update_time: string;
  children: Dept[];
}

export interface DeptWrite {
  parent_id?: Id | null;
  name: string;
  leader_id?: Id | null;
  phone?: string | null;
  sort?: number;
  status?: UserStatus;
  /** 编辑读到的 update_time 原样回传；不一致返回 409 STALE_VERSION。 */
  base_version?: string;
}

export interface DeptTreeQuery extends AdminQuery {
  keyword?: string;
  status?: UserStatus;
}

/** 删除部门预检查结果：affected_roles 里的角色删除后会降级成「只看自己的」。 */
export interface DeptDeleteCheck {
  user_count: number;
  child_count: number;
  affected_roles: { id: Id; name: string }[];
}

function deptPath(deptId: Id): string {
  return `/depts/${encodeURIComponent(deptId)}`;
}

export function getDeptTree(query?: DeptTreeQuery): Promise<{ items: Dept[] }> {
  return adminGet<{ items: Dept[] }>('/depts/tree', { query });
}

export function createDept(body: DeptWrite, idempotencyKey?: string): Promise<Dept> {
  return adminPost<Dept>('/depts', body, { idempotencyKey });
}

export function updateDept(deptId: Id, body: DeptWrite): Promise<Dept> {
  return adminPut<Dept>(deptPath(deptId), body);
}

export function deptDeleteCheck(deptId: Id): Promise<DeptDeleteCheck> {
  return adminGet<DeptDeleteCheck>(`${deptPath(deptId)}/delete-check`);
}

export function deleteDept(deptId: Id): Promise<EmptyResult> {
  return adminDelete<EmptyResult>(deptPath(deptId));
}
