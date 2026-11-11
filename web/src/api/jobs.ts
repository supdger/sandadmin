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
