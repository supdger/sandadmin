// 遗留（FE0 安装向导）：仍走旧 apiClient + 旧信封。v1 契约对应 /setup/*
// 端点，语义与返回结构不同，安装向导改造属 FE0 范围；不得再扩大依赖面。
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
