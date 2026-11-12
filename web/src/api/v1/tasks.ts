import { adminDownload, adminGet, type AdminQuery } from '@/lib/adminClient';
import type { DownloadedFile } from '@/lib/adminClient';
import type { Id, Page, TaskState } from './types';

export interface Task {
  id: Id;
  type: string;
  state: TaskState;
  progress: number | null;
  stage: string | null;
  result: ({ file_id?: string } & Record<string, unknown>) | null;
  error: { code: string; message: string } | null;
  create_time: string;
  start_time: string | null;
  finish_time: string | null;
}

export interface TaskListQuery extends AdminQuery {
  type?: string;
  state?: TaskState;
  mine?: boolean;
  page?: number;
  page_size?: number;
}

export function listTasks(query?: TaskListQuery): Promise<Page<Task>> {
  return adminGet<Page<Task>>('/tasks', { query });
}

export function getTask(taskId: Id): Promise<Task> {
  return adminGet<Task>(`/tasks/${encodeURIComponent(taskId)}`);
}

/** 只有 state=succeeded 的任务可下载；否则抛 TASK_RUN_NOT_DOWNLOADABLE。 */
export function downloadTask(taskId: Id): Promise<DownloadedFile> {
  return adminDownload(`/tasks/${encodeURIComponent(taskId)}/download`);
}
