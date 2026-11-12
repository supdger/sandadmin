import { adminGet, adminPost, adminPut } from '@/lib/adminClient';
import type { Id, Page } from './types';

/** schedule.freq 的取值；结构对应 sand_system_schedule.schedule jsonb。 */
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

/**
 * GET /scheduled-tasks 行（契约 ScheduledTask）。schedule 总是返回
 * 生效时间结构（覆盖值或清单默认）；schedule_overridden 标是否改过；
 * schedule_locked=清单锁定，锁定任务不出现「改时间」。
 */
export interface ScheduledTask {
  code: string;
  name: string;
  description: string | null;
  /** 来源名（宿主/插件名），来自静态清单。 */
  source_label: string | null;
  is_critical: boolean;
  /** 插件任务的所属插件编码；宿主内置任务为 null。 */
  plugin_code: string | null;
  schedule: TaskSchedule | null;
  /** true=保存过页面覆盖；false=用的是清单默认。 */
  schedule_overridden: boolean;
  /** 清单声明不可改时为 true。 */
  schedule_locked: boolean;
  status: 'enabled' | 'disabled';
  last_run_time: string | null;
  /** 上次执行是否成功；从未执行为 null。 */
  last_run_succeeded: boolean | null;
  next_run_time: string | null;
  /** 未来三次执行预览；停用/无有效时间结构时为空数组。 */
  next_run_times: string[];
}

/** PUT 入参：schedule 传 null 恢复清单默认；locked 任务返回 409。 */
export interface ScheduledTaskWrite {
  status?: 'enabled' | 'disabled';
  schedule?: TaskSchedule | null;
}

/**
 * GET /scheduled-tasks/{code}/runs 行（契约 TaskRun）。
 * 业务失败看 message 人话；系统内部失败看 error_code + request_id。
 */
export interface TaskRun {
  id: Id;
  schedule_code: string;
  /** schedule=到点自动执行；manual=页面手动触发。 */
  trigger: 'schedule' | 'manual';
  status: 'running' | 'succeeded' | 'failed';
  /** start 到 finish 的毫秒数；执行中为 null。 */
  duration_ms: number | null;
  /** 失败原因摘要（人话）；系统内部失败时为通用提示。 */
  message: string | null;
  /** 失败类别/排查码；interrupted=进程中断被标记。 */
  error_code: string | null;
  /** 排查用请求追踪编号。 */
  request_id: string | null;
  start_time: string;
  finish_time: string | null;
}

/** GET /scheduled-tasks 响应：items 之外带设置时区，行内钟点按它解释。 */
export interface ScheduledTaskList {
  items: ScheduledTask[];
  /** 设置时区（IANA，如 Asia/Shanghai）：schedule 钟点与 next_run_time 按它算。 */
  timezone: string;
  /** 设置时区中文名（如「北京时间（东八区）」）。 */
  timezone_label: string;
}

export function listScheduledTasks(query?: {
  keyword?: string;
  status?: 'enabled' | 'disabled';
  /** true=只看上次执行没有成功的任务（last_run_succeeded=false）。 */
  failed_only?: boolean;
}): Promise<ScheduledTaskList> {
  return adminGet<ScheduledTaskList>('/scheduled-tasks', { query });
}

export function updateScheduledTask(
  code: string,
  body: ScheduledTaskWrite,
): Promise<ScheduledTask> {
  return adminPut<ScheduledTask>(`/scheduled-tasks/${encodeURIComponent(code)}`, body);
}

/** 手动跑一次；受理后页面轮询 runs 看结果。 */
export function runScheduledTask(code: string): Promise<{ schedule_run_id: Id }> {
  return adminPost<{ schedule_run_id: Id }>(
    `/scheduled-tasks/${encodeURIComponent(code)}/run`,
  );
}

export function listScheduleRuns(code: string, page = 1): Promise<Page<TaskRun>> {
  return adminGet<Page<TaskRun>>(
    `/scheduled-tasks/${encodeURIComponent(code)}/runs`,
    { query: { page } },
  );
}
