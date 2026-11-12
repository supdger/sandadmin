import { downloadTask, getTask } from '@/api/v1/tasks';
import type { TaskAccepted } from '@/api/v1/types';
import { saveDownloadedFile } from '@/pages/org/shared/download';

const POLL_MS = 600;
const MAX_POLLS = 30;

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

export interface ExportOutcome {
  ok: boolean;
  /** 失败原因（后端大白话），超时为空。 */
  message?: string;
}

/**
 * 导出类接口的通用跑腿：受理 → 轮询 /tasks/{id} → 成功就触发下载。
 * 与 UsersPage 的 runExport 同构，只是不绑定某个页面的文案。
 */
export async function runExport(
  start: () => Promise<TaskAccepted>,
  filename: string,
): Promise<ExportOutcome> {
  const accepted = await start();
  for (let i = 0; i < MAX_POLLS; i += 1) {
    await sleep(POLL_MS);
    const task = await getTask(accepted.task_id);
    if (task.state === 'succeeded') {
      const file = await downloadTask(accepted.task_id);
      saveDownloadedFile(file, filename);
      return { ok: true };
    }
    if (task.state === 'failed' || task.state === 'cancelled') {
      return { ok: false, message: task.error?.message ?? undefined };
    }
  }
  return { ok: false };
}
