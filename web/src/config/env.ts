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
  enableMocks: boolean;
}

function readString(value: string | undefined, fallback: string): string {
  const trimmed = value?.trim();
  return trimmed ? trimmed : fallback;
}

function readBool(value: string | undefined, fallback: boolean): boolean {
  if (value === undefined || value.trim() === '') {
    return fallback;
  }
  return value === 'true' || value === '1';
}

export const env: AppEnv = {
  apiBaseUrl: readString(import.meta.env.VITE_API_BASE_URL, '/api'),
  appName: readString(import.meta.env.VITE_APP_NAME, 'SandAdmin'),
  appLogo: readString(import.meta.env.VITE_APP_LOGO, '') || null,
  copyright: readString(
    import.meta.env.VITE_APP_COPYRIGHT,
    '© 2026 青岛沙盒创意网络科技有限公司',
  ),
  defaultLocale: readString(import.meta.env.VITE_DEFAULT_LOCALE, 'zh-CN'),
  // Mocks default on in dev, off in production builds.
  enableMocks: readBool(import.meta.env.VITE_ENABLE_MOCKS, import.meta.env.DEV),
};
