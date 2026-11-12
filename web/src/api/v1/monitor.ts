import { adminGet } from '@/lib/adminClient';

/**
 * GET /monitor/server 返回的服务器状态。`db_ok`、`server_time`、`version`
 * 是按产品规格要求后端补齐的字段，老接口可能缺省——页面逐个字段兜底
 * 「暂时读不到」。
 */
export interface ServerStatus {
  collected_time: string;
  server_time?: string | null;
  version?: string | null;
  db_ok?: boolean | null;
  db_check_time?: string | null;
  cpu: { usage_percent: number | null };
  memory: { usage_percent: number | null; used_mb: number | null; total_mb: number | null };
  disk: { free_gb: number | null; total_gb: number | null };
  process?: { memory_mb?: number | null; uptime_seconds?: number | null } | null;
}

export function getServerStatus(): Promise<ServerStatus> {
  return adminGet<ServerStatus>('/monitor/server');
}
