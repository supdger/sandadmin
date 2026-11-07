import { apiGet, apiPost } from '@/lib/apiClient';

export type InstallStatus = { installed: boolean; lockExists: boolean };

export function getInstallStatus(): Promise<InstallStatus> {
  return apiGet<InstallStatus>('/install/status');
}

export function startInstall(p: {
  adminUsername: string;
  adminPassword: string;
  adminNickname: string;
  siteName?: string;
}): Promise<{ jobId: string }> {
  return apiPost<{ jobId: string }>('/install/start', p);
}
