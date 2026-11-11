import { apiDelete, apiDownload, apiGet, apiPost, apiUpload } from '@/lib/apiClient';

export interface FileObject {
  id: string;
  original_name: string;
  kind: 'image' | 'document' | 'sheet' | 'archive' | 'other';
  media_type: string;
  size_bytes: number;
  usage_state: 'used' | 'unused' | 'unknown';
  usage_label: string | null;
  visibility: 'private' | 'public';
  purpose: 'attachment' | 'export' | 'package' | 'plugin';
  uploader_id: string | null;
  uploader_name: string | null;
  create_time: string;
}

export interface PageFile {
  items: FileObject[];
  total: number;
  page: number;
  page_size: number;
}

export interface FileListParams {
  keyword?: string;
  kind?: string;
  usage_state?: string;
  create_time_from?: string;
  create_time_to?: string;
  page?: number;
  page_size?: number;
}

export interface FileUploadOptions {
  usage?: string;
  visibility?: 'private' | 'public';
}

export interface TaskAccepted {
  task_id: string;
  state: 'queued';
}

export interface TaskResult {
  id: string;
  type: string;
  state: 'queued' | 'running' | 'succeeded' | 'failed' | 'cancelled';
  progress: number | null;
  stage: string | null;
  result: Record<string, unknown> | null;
  error: { code: string; message: string } | null;
  create_time: string;
  start_time: string | null;
  finish_time: string | null;
}

export function listFiles(params: FileListParams): Promise<PageFile> {
  const sp = new URLSearchParams();
  if (params.keyword?.trim()) sp.set('keyword', params.keyword.trim());
  if (params.kind) sp.set('kind', params.kind);
  if (params.usage_state) sp.set('usage_state', params.usage_state);
  if (params.create_time_from) sp.set('create_time_from', params.create_time_from);
  if (params.create_time_to) sp.set('create_time_to', params.create_time_to);
  if (params.page) sp.set('page', String(params.page));
  if (params.page_size) sp.set('page_size', String(params.page_size));
  const query = sp.toString();
  return apiGet<PageFile>(`/files${query ? `?${query}` : ''}`);
}

export function getFile(fileId: string): Promise<FileObject> {
  return apiGet<FileObject>(`/files/${encodeURIComponent(fileId)}`);
}

export function uploadFile(file: File, options?: FileUploadOptions): Promise<FileObject> {
  const form = new FormData();
  form.append('file', file);
  if (options?.usage) form.append('usage', options.usage);
  if (options?.visibility) form.append('visibility', options.visibility);
  return apiUpload<FileObject>('/files', form);
}

export function deleteFile(fileId: string): Promise<unknown> {
  return apiDelete(`/files/${encodeURIComponent(fileId)}`);
}

/** 授权下载：返回文件二进制，由调用方决定如何保存/预览。 */
export function downloadFile(fileId: string): Promise<Blob> {
  return apiDownload(`/files/${encodeURIComponent(fileId)}/download`);
}

export function cleanupFiles(): Promise<TaskAccepted> {
  return apiPost<TaskAccepted>('/files/cleanup');
}

export function getTask(taskId: string): Promise<TaskResult> {
  return apiGet<TaskResult>(`/tasks/${encodeURIComponent(taskId)}`);
}
