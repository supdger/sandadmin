// 遗留（FE0 登录流）：仍走旧 apiClient + 旧信封。v1 契约的登录是 Cookie
// 会话制且返回结构不同（{user, must_change_password, first_setup} 无 token），
// 登录页改造属 FE0 范围；本文件不得再扩大对旧 client 的依赖。
// /me 已由 api/v1/me.ts 接管（adminClient + v1 信封）。
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
