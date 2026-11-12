import { adminGet, adminPost, adminPut } from '@/lib/adminClient';
import type { Id, Page } from './types';

/** schedule.freq 的取值；null schedule 表示时间由系统定，不可改。 */
export type ScheduleFreq = 'interval' | 'hourly' | 'daily' | 'weekly' | 'monthly';

export interface TaskSchedule {
  freq: ScheduleFreq;
  /** freq=interval 时必填：每 N 分钟（5–60）。 */
  interval?: number | null;
  /** hourly/daily/weekly/monthly 的「HH:mm」。hourly 时只用分钟。 */
  time?: string | null;
  /** freq=weekly：1–7，周一到周日。 */
  weekday?: number | null;
  /** freq=monthly：1–31，遇短月落到当月最后一天。 */
  monthday?: number | null;
}

/** GET /scheduled-tasks 行。schedule_locked 的任务不显示「改时间」。 */
export interface ScheduledTask {
  code: Id;
  name: string;
  description: string | null;
  source: { kind: 'system' | 'plugin'; label?: string | null } | null;
  schedule: TaskSchedule | null;
  schedule_locked: boolean;
  status: 'enabled' | 'disabled';
  is_critical: boolean;
  last_run_time: string | null;
  last_run_ok: boolean | null;
  last_run_message?: string | null;
  next_run_time: string | null;
}

export interface ScheduledTaskWrite {
  status?: 'enabled' | 'disabled';
  schedule?: TaskSchedule | null;
}

/** GET /scheduled-tasks/{code}/runs 行；error 为系统故障诊断块。 */
export interface ScheduleRun {
  id: Id;
  trigger: 'manual' | 'auto';
  state: 'running' | 'succeeded' | 'failed';
  start_time: string;
  finish_time: string | null;
  /** 业务失败的大白话；系统故障走 error 块。 */
  message: string | null;
  error?: { code: string; request_id?: string } | null;
}

export function listScheduledTasks(query?: {
  keyword?: string;
  status?: 'enabled' | 'disabled';
  last_failed?: boolean;
}): Promise<{ items: ScheduledTask[] }> {
  return adminGet<{ items: ScheduledTask[] }>('/scheduled-tasks', { query });
}

export function updateScheduledTask(
  code: Id,
  body: ScheduledTaskWrite,
): Promise<ScheduledTask> {
  return adminPut<ScheduledTask>(`/scheduled-tasks/${encodeURIComponent(code)}`, body);
}

/** 手动跑一次；受理后页面轮询 runs 看结果。 */
export function runScheduledTask(code: Id): Promise<{ schedule_run_id: Id }> {
  return adminPost<{ schedule_run_id: Id }>(
    `/scheduled-tasks/${encodeURIComponent(code)}/run`,
  );
}

export function listScheduleRuns(code: Id, page = 1): Promise<Page<ScheduleRun>> {
  return adminGet<Page<ScheduleRun>>(
    `/scheduled-tasks/${encodeURIComponent(code)}/runs`,
    { query: { page } },
  );
}
