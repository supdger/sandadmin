import { adminDelete, adminGet, adminPost, adminPut, type AdminQuery } from '@/lib/adminClient';
import type { EmptyResult, Id, Page, TaskAccepted, UserStatus } from './types';

export interface User {
  id: Id;
  username: string | null;
  nickname: string;
  avatar: string | null;
  avatar_file_id: Id | null;
  phone: string;
  email: string | null;
  status: UserStatus;
  is_admin: boolean;
  is_super: boolean;
  must_change_password: boolean;
  permissions: string[];
  dept_id: Id;
  dept_name: string | null;
  post_id: Id | null;
  post_name: string | null;
  role_ids: Id[];
  role_names: string[];
  last_login_time: string | null;
  create_time: string;
}

export interface UserDetail extends User {
  dept_path: string | null;
  post: { id: Id; name: string } | null;
  roles: { id: Id; name: string }[];
}

export interface UserWrite {
  username?: string | null;
  nickname: string;
  password: string;
  phone: string;
  email?: string | null;
  dept_id: Id;
  post_id?: Id | null;
  role_ids: Id[];
  status?: UserStatus;
}

/** 更新用户入参：字段同 UserWrite 但不含 password，改密码走 reset-password。 */
export type UserUpdate = Omit<UserWrite, 'password'>;

export interface DisableCheckItem {
  source: string;
  kind: string;
  count: number;
  message: string;
}

export interface UserListQuery extends AdminQuery {
  keyword?: string;
  status?: UserStatus;
  dept_id?: Id;
  role_id?: Id;
  post_id?: Id;
  page?: number;
  page_size?: number;
}

export interface UserExportBody {
  keyword?: string;
  status?: UserStatus;
  dept_id?: Id;
  role_id?: Id;
  post_id?: Id;
}

function userPath(userId: Id): string {
  return `/users/${encodeURIComponent(userId)}`;
}

export function listUsers(query?: UserListQuery): Promise<Page<User>> {
  return adminGet<Page<User>>('/users', { query });
}

export function createUser(body: UserWrite): Promise<User> {
  return adminPost<User>('/users', body);
}

export function getUser(userId: Id): Promise<UserDetail> {
  return adminGet<UserDetail>(userPath(userId));
}

export function updateUser(userId: Id, body: UserUpdate): Promise<User> {
  return adminPut<User>(userPath(userId), body);
}

export function deleteUser(
  userId: Id,
  options?: { transferToUserId?: Id | null },
): Promise<EmptyResult> {
  const body =
    options?.transferToUserId === undefined
      ? undefined
      : { transfer_to_user_id: options.transferToUserId };
  return adminDelete<EmptyResult>(userPath(userId), body);
}

export function updateUserStatus(
  userId: Id,
  body: { status: UserStatus; transfer_to_user_id?: Id | null },
): Promise<EmptyResult> {
  return adminPut<EmptyResult>(`${userPath(userId)}/status`, body);
}

export function getUserDisableCheck(userId: Id): Promise<DisableCheckItem[]> {
  return adminGet<DisableCheckItem[]>(`${userPath(userId)}/disable-check`);
}

export function resetUserPassword(userId: Id, password: string): Promise<EmptyResult> {
  return adminPost<EmptyResult>(`${userPath(userId)}/reset-password`, { password });
}

export function assignUserRoles(userId: Id, roleIds: Id[]): Promise<EmptyResult> {
  return adminPut<EmptyResult>(`${userPath(userId)}/roles`, { role_ids: roleIds });
}

export function exportUsers(body?: UserExportBody): Promise<TaskAccepted> {
  return adminPost<TaskAccepted>('/users/export', body ?? {});
}
