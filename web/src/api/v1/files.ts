import {
  adminDelete,
  adminDownload,
  adminGet,
  adminPost,
  adminUpload,
  type AdminQuery,
  type DownloadedFile,
} from '@/lib/adminClient';
import type { Id, Page, TaskAccepted } from './types';

export { getTask } from './tasks';

export interface FileObject {
  id: Id;
  original_name: string;
  kind: 'image' | 'document' | 'sheet' | 'archive' | 'other';
  media_type: string;
  size_bytes: number;
  usage_state: 'used' | 'unused' | 'unknown';
  usage_label: string | null;
  visibility: 'private' | 'public';
  purpose: 'attachment' | 'export' | 'package' | 'plugin';
  uploader_id: Id | null;
  uploader_name: string | null;
  create_time: string;
}

export type PageFile = Page<FileObject>;

export interface FileListQuery extends AdminQuery {
  keyword?: string;
  kind?: FileObject['kind'];
  usage_state?: FileObject['usage_state'];
  uploader_id?: Id;
  create_time_from?: string;
  create_time_to?: string;
  page?: number;
  page_size?: number;
}

export interface FileUploadOptions {
  usage?: string;
  visibility?: FileObject['visibility'];
}

export function listFiles(query?: FileListQuery): Promise<PageFile> {
  return adminGet<PageFile>('/files', { query });
}

export function getFile(fileId: Id): Promise<FileObject> {
  return adminGet<FileObject>(`/files/${encodeURIComponent(fileId)}`);
}

export function uploadFile(file: File, options?: FileUploadOptions): Promise<FileObject> {
  const form = new FormData();
  form.append('file', file);
  if (options?.usage) form.append('usage', options.usage);
  if (options?.visibility) form.append('visibility', options.visibility);
  return adminUpload<FileObject>('/files', form);
}

export function deleteFile(fileId: Id): Promise<unknown> {
  return adminDelete(`/files/${encodeURIComponent(fileId)}`);
}

/** 授权下载：返回文件二进制与 Content-Disposition 文件名，由调用方决定如何保存/预览。 */
export function downloadFile(fileId: Id): Promise<DownloadedFile> {
  return adminDownload(`/files/${encodeURIComponent(fileId)}/download`);
}

export function cleanupFiles(): Promise<TaskAccepted> {
  return adminPost<TaskAccepted>('/files/cleanup');
}
