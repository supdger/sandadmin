import { downloadFile } from '@/api/v1/files';

/** 把 Blob 存成文件下载。 */
export function saveBlob(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  // 给浏览器一点时间开始下载再回收。
  setTimeout(() => URL.revokeObjectURL(url), 10_000);
}

/** 把 Blob 转成可预览的本地地址；调用方负责 revoke。 */
export function blobObjectUrl(blob: Blob): string {
  return URL.createObjectURL(blob);
}

/** 新标签页打开 PDF 等可直接渲染的内容。 */
export function openBlobTab(blob: Blob): void {
  const url = blobObjectUrl(blob);
  window.open(url, '_blank', 'noopener');
  // 新页签加载完成前不能回收；留给浏览器自行管理。
  setTimeout(() => URL.revokeObjectURL(url), 60_000);
}

export interface PreviewTarget {
  fileId: string;
  name: string;
  mediaType: string;
  kind: string;
}

/** 判断文件是否可以在新页签里直接打开（PDF）。 */
export function isPdfPreview(target: PreviewTarget): boolean {
  return target.mediaType === 'application/pdf' || /\.pdf$/i.test(target.name);
}

/** 拉取文件内容并决定打开方式：图片走组件内预览，PDF 开新页，其余下载。 */
export async function fetchFileBlob(fileId: string): Promise<Blob> {
  const file = await downloadFile(fileId);
  return file.blob;
}
