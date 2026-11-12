import { adminDelete, adminGet, adminPost, adminPut } from '@/lib/adminClient';
import { setToken } from '@/lib/authToken';
import type { Id } from './types';

/** GET /me/profile — editable fields plus the read-only org context rows. */
export interface UserProfile {
  id: Id;
  username: string | null;
  nickname: string;
  avatar: string | null;
  avatar_file_id: Id | null;
  phone: string;
  email: string | null;
  dept_name: string | null;
  post_name: string | null;
  role_names: string[];
  password_changed_time: string;
}

/** PUT /me/profile body — snake_case per the OpenAPI contract. */
export interface UserProfileWrite {
  nickname: string;
  phone: string;
  email?: string | null;
  avatar_file_id?: Id | null;
}

/** GET /auth/password-policy — server-side hint only, real checks are server-side. */
export interface PasswordRule {
  tier: 'normal' | 'admin' | 'temp';
  min_length: number;
  require_letter: boolean;
  require_digit: boolean;
  forbid_weak: boolean;
  forbid_same_as_username: boolean;
  forbid_same_as_previous: number;
}

/** GET /me/sessions item — one signed-in device. ip/user_agent 接口照收，页面一律不显示。 */
export interface Session {
  id: Id;
  device_label: string | null;
  location: string | null;
  ip: string | null;
  user_agent: string | null;
  create_time: string;
  last_active_time: string;
  expire_time: string | null;
  current: boolean;
}

export interface SessionList {
  items: Session[];
}

export function getMyProfile(): Promise<UserProfile> {
  return adminGet<UserProfile>('/me/profile');
}

export function putMyProfile(body: UserProfileWrite): Promise<UserProfile> {
  return adminPut<UserProfile>('/me/profile', body);
}

export function getPasswordRule(): Promise<PasswordRule> {
  return adminGet<PasswordRule>('/auth/password-policy');
}

/** POST /auth/password/change — 改密后旧令牌全部失效，响应带回新令牌。 */
export interface PasswordChangeResult {
  changed: boolean;
  token?: string;
}

export async function changePassword(body: {
  old_password: string;
  new_password: string;
}): Promise<PasswordChangeResult> {
  const data = await adminPost<PasswordChangeResult>('/auth/password/change', body);
  if (typeof data.token === 'string' && data.token !== '') {
    setToken(data.token);
  }
  return data;
}

export function getMySessions(): Promise<SessionList> {
  return adminGet<SessionList>('/me/sessions');
}

export function deleteMySession(sessionId: Id): Promise<unknown> {
  return adminDelete(`/me/sessions/${encodeURIComponent(sessionId)}`);
}

export function deleteOtherSessions(): Promise<unknown> {
  return adminDelete('/me/sessions');
}
