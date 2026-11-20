/**
 * Runtime configuration read from Vite environment variables.
 * Everything has a sensible default so a bare clone can boot without .env.
 */

export interface AppEnv {
  apiBaseUrl: string;
  appName: string;
  appLogo: string | null;
  copyright: string;
  defaultLocale: string;
  /**
   * v1 写操作必带的 CSRF 标记头（服务端 CsrfMiddleware 只认这一对）。
   * 必须与后端 SAND_CSRF_HEADER_NAME / SAND_CSRF_HEADER_VALUE 一致，
   * 后端只要求存在时值可以是任意非空串（默认沿用 XHR 惯例值）。
   */
  csrfHeaderName: string;
  csrfHeaderValue: string;
  // SAND-DEV-BEGIN
  enableMocks: boolean;
  /**
   * Passwords for the extra MSW demo accounts (`viewer` = read-only,
   * `guest` = no permissions). `null` disables that account's login —
   * keep them in .env.local, never commit real values.
   */
  mockViewerPassword: string | null;
  mockGuestPassword: string | null;
  // SAND-DEV-END
}

function readString(value: string | undefined, fallback: string): string {
  const trimmed = value?.trim();
  return trimmed ? trimmed : fallback;
}

// SAND-DEV-BEGIN
// 只有 mock 账号块在读布尔环境变量；生产剔除后该函数无消费方。
function readBool(value: string | undefined, fallback: boolean): boolean {
  if (value === undefined || value.trim() === '') {
    return fallback;
  }
  return value === 'true' || value === '1';
}
// SAND-DEV-END

export const env: AppEnv = {
  apiBaseUrl: readString(import.meta.env.VITE_API_BASE_URL, '/api'),
  appName: readString(import.meta.env.VITE_APP_NAME, 'SandAdmin'),
  appLogo: readString(import.meta.env.VITE_APP_LOGO, '') || null,
  copyright: readString(
    import.meta.env.VITE_APP_COPYRIGHT,
    '© 2026 青岛沙盒创意网络科技有限公司',
  ),
  defaultLocale: readString(import.meta.env.VITE_DEFAULT_LOCALE, 'zh-CN'),
  csrfHeaderName: readString(import.meta.env.VITE_CSRF_HEADER_NAME, 'X-Requested-With'),
  csrfHeaderValue: readString(import.meta.env.VITE_CSRF_HEADER_VALUE, 'XMLHttpRequest'),
  // SAND-DEV-BEGIN
  // Mocks default on in dev, off in production builds.
  enableMocks: readBool(import.meta.env.VITE_ENABLE_MOCKS, import.meta.env.DEV),
  mockViewerPassword: readString(import.meta.env.VITE_MOCK_VIEWER_PASSWORD, '') || null,
  mockGuestPassword: readString(import.meta.env.VITE_MOCK_GUEST_PASSWORD, '') || null,
  // SAND-DEV-END
};
