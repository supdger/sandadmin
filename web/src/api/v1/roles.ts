import { adminDelete, adminGet, adminPost, adminPut, type AdminQuery } from '@/lib/adminClient';
import type { PermScope, EmptyResult, Id, Page, UserStatus } from './types';

export interface Role {
  id: Id;
  name: string;
  remark: string | null;
  is_system: boolean;
  is_super: boolean;
  status: UserStatus;
  data_scope: PermScope;
  user_count: number;
  update_time: string;
}

export interface RoleWrite {
  name: string;
  remark?: string | null;
  status?: UserStatus;
}

export interface RoleListQuery extends AdminQuery {
  keyword?: string;
  status?: UserStatus;
  page?: number;
  page_size?: number;
}

export interface RolePermissions {
  data_scope: PermScope;
  dept_ids: Id[];
  permission_codes: string[];
  user_count: number;
  readonly: boolean;
  base_version: number;
}

export interface RolePermissionsWrite {
  data_scope: PermScope;
  dept_ids: Id[];
  permission_codes: string[];
  base_version: number;
}

export interface RoleCopyBody {
  name: string;
}

function rolePath(roleId: Id): string {
  return `/roles/${encodeURIComponent(roleId)}`;
}

export function listRoles(query?: RoleListQuery): Promise<Page<Role>> {
  return adminGet<Page<Role>>('/roles', { query });
}

export function createRole(body: RoleWrite, idempotencyKey?: string): Promise<Role> {
  return adminPost<Role>('/roles', body, { idempotencyKey });
}

export function getRole(roleId: Id): Promise<Role> {
  return adminGet<Role>(rolePath(roleId));
}

export function updateRole(roleId: Id, body: RoleWrite): Promise<Role> {
  return adminPut<Role>(rolePath(roleId), body);
}

/** 还有人在用时必须带上替换角色。换角色和删除由后端放在同一个事务里。 */
export function deleteRole(
  roleId: Id,
  replacementRoleId?: string,
): Promise<{ moved_count: number; replacement_role_name: string | null }> {
  return adminDelete<{ moved_count: number; replacement_role_name: string | null }>(
    rolePath(roleId),
    replacementRoleId ? { replacement_role_id: replacementRoleId } : {},
  );
}

export function copyRole(roleId: Id, body: RoleCopyBody, idempotencyKey?: string): Promise<Role> {
  return adminPost<Role>(`${rolePath(roleId)}/copy`, body, { idempotencyKey });
}

export function updateRoleStatus(
  roleId: Id,
  body: { status: UserStatus },
): Promise<EmptyResult> {
  return adminPut<EmptyResult>(`${rolePath(roleId)}/status`, body);
}

export function getRolePermissions(roleId: Id): Promise<RolePermissions> {
  return adminGet<RolePermissions>(`${rolePath(roleId)}/permissions`);
}

export function saveRolePermissions(
  roleId: Id,
  body: RolePermissionsWrite,
): Promise<{ affected_users: number }> {
  return adminPut<{ affected_users: number }>(`${rolePath(roleId)}/permissions`, body);
}
