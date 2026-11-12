// 遗留（FE0 安装向导的任务轮询）：仍走旧 apiClient + 旧信封。
// v1 契约的后台任务是 /tasks/{task_id}（api/v1/tasks.ts），安装流改造属 FE0 范围。
import { apiGet } from '@/lib/apiClient';

export interface JobRun {
  id: string;
  status: 'pending' | 'running' | 'success' | 'failed';
  stage: string;
  progress: number;
  error?: string;
}

export function getJob(id: string): Promise<JobRun> {
  return apiGet<JobRun>(`/jobs/${encodeURIComponent(id)}`);
}
