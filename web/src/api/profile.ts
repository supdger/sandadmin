import { apiDelete, apiGet, apiPost, apiPut, apiUpload } from '@/lib/apiClient';

/** GET /me/profile — editable profile fields plus read-only context. */
export interface UserProfile {
  id: string;
  username: string | null;
  nickname: string;
  avatar: string | null;
  avatar_file_id: string | null;
  phone: string;
  email: string | null;
  password_changed_time: string;
}

/** PUT /me/profile body — snake_case per the OpenAPI contract. */
export interface UserProfileWrite {
  nickname: string;
  phone: string;
  email?: string | null;
  avatar_file_id?: string | null;
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

/** GET /me/sessions item — one signed-in device. */
export interface Session {
  id: string;
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
  return apiGet<UserProfile>('/me/profile');
}

export function putMyProfile(body: UserProfileWrite): Promise<UserProfile> {
  return apiPut<UserProfile>('/me/profile', body);
}

/** POST /files multipart — returns the stored file id used as avatar_file_id. */
export function uploadFile(file: File): Promise<{ id: string }> {
  const form = new FormData();
  form.append('file', file);
  return apiUpload<{ id: string }>('/files', form);
}

export function getPasswordRule(): Promise<PasswordRule> {
  return apiGet<PasswordRule>('/auth/password-policy');
}

export function changePassword(body: {
  old_password: string;
  new_password: string;
}): Promise<unknown> {
  return apiPost('/auth/password/change', body);
}

export function getMySessions(): Promise<SessionList> {
  return apiGet<SessionList>('/me/sessions');
}

export function deleteMySession(sessionId: string): Promise<unknown> {
  return apiDelete(`/me/sessions/${encodeURIComponent(sessionId)}`);
}

export function deleteOtherSessions(): Promise<unknown> {
  return apiDelete('/me/sessions');
}
