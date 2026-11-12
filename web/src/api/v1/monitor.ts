import { adminGet } from '@/lib/adminClient';

/**
 * GET /monitor/server 返回的服务器状态（契约 ServerStatus）。
 * `db_ok`、`db_checked_time`、`server_time`、`version` 老接口可能缺省——
 * 页面逐个字段兜底「暂时读不到」。
 */
export interface ServerStatus {
  collected_time: string;
  server_time?: string | null;
  version?: string | null;
  db_ok?: boolean | null;
  /** 数据库连通的最近一次检测时间（契约字段名 db_checked_time）。 */
  db_checked_time?: string | null;
  cpu: { usage_percent: number | null; cores?: number | null };
  memory: { usage_percent: number | null; used_mb: number | null; total_mb: number | null };
  disk: { free_gb: number | null; total_gb: number | null };
  process?: { memory_mb?: number | null; uptime_seconds?: number | null } | null;
}

export function getServerStatus(): Promise<ServerStatus> {
  return adminGet<ServerStatus>('/monitor/server');
}
