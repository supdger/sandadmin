import { env } from '@/config/env';

/** 文件下载/预览地址：img/链接直接可用（同源 Cookie 会话带凭据）。 */
export function fileDownloadUrl(fileId: string): string {
  return `${env.apiBaseUrl}/admin/v1/files/${encodeURIComponent(fileId)}/download`;
}
