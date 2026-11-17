import { adminPut } from '@/lib/adminClient';

/**
 * 定时任务执行时间结构（对应 sand_system_schedule.schedule jsonb）：
 * interval 为分钟数；time 为 HH:MM；weekday 1-7；monthday 1-31。
 */
export interface TaskSchedule {
  freq: 'interval' | 'hourly' | 'daily' | 'weekly' | 'monthly';
  interval?: number;
  time?: string;
  weekday?: number;
  monthday?: number;
}

/** PUT /scheduled-tasks/{code} 返回的任务项。 */
export interface ScheduledTask {
  code: string;
  name: string;
  description: string | null;
  /** 来源说明：宿主或插件编码。 */
  source_label: string | null;
  is_critical: boolean;
  plugin_code: string | null;
  schedule: TaskSchedule | null;
  status: 'enabled' | 'disabled';
  schedule_locked: boolean;
  last_run_time: string | null;
  last_run_succeeded: boolean | null;
  next_run_time: string | null;
}

/**
 * 覆盖写体：schedule 传对象改执行时间、传 null 恢复清单默认；
 * status 只改启停。两键都省略等于不改。
 */
export interface ScheduledTaskOverride {
  schedule?: TaskSchedule | null;
  status?: 'enabled' | 'disabled';
}

export function updateScheduledTask(
  code: string,
  override: ScheduledTaskOverride,
): Promise<ScheduledTask> {
  return adminPut<ScheduledTask>(`/scheduled-tasks/${encodeURIComponent(code)}`, override);
}
