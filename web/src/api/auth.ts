import { apiGet, apiPost } from '@/lib/apiClient';
import { clearToken, setToken } from '@/lib/authToken';

export interface LoginParams {
  username: string;
  password: string;
  captchaId?: string;
  captchaAnswer?: number[];
}

export interface LoginResult {
  token: string;
  mustChangePassword: boolean;
}

export interface CurrentUser {
  id: string;
  username: string;
  nickname: string;
  avatar?: string;
  mustChangePassword: boolean;
  permissions: string[];
  // Optional fields mirror the OpenAPI User schema; older responses may omit them.
  avatar_file_id?: string | null;
  phone?: string;
  email?: string | null;
  dept_id?: string | null;
  dept_name?: string | null;
  post_id?: string | null;
  role_ids?: string[];
  is_admin?: boolean;
  is_super?: boolean;
}

export interface PasswordPolicy {
  minLength: number;
  requireMixedCase: boolean;
  requireDigit: boolean;
  requireSymbol: boolean;
  historyCheckCount: number;
}

export async function login(p: LoginParams): Promise<LoginResult> {
  const data = await apiPost<LoginResult>('/auth/login', p);
  setToken(data.token);
  return data;
}

export async function logout(): Promise<void> {
  try {
    await apiPost('/auth/logout');
  } finally {
    clearToken();
  }
}

/** The UI only fetches a captcha once the failure counter reaches 3. */
export function getCaptcha(): Promise<{ captchaId: string; imageDataUrl: string }> {
  return apiGet('/auth/captcha');
}

export function verifyCaptcha(captchaId: string, points: number[]): Promise<boolean> {
  return apiPost<boolean>('/auth/captcha/verify', { captchaId, points });
}

export function getPasswordPolicy(): Promise<PasswordPolicy> {
  return apiGet('/auth/password/policy');
}

export function forceChangePassword(oldPassword: string, newPassword: string): Promise<void> {
  return apiPost('/auth/password/force-change', { oldPassword, newPassword });
}

export function getMe(): Promise<CurrentUser> {
  return apiGet<CurrentUser>('/me');
}
