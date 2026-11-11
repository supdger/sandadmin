import type { DownloadedFile } from '@/lib/adminClient';

/**
 * 把下载结果存成文件。jsdom 没有 URL.createObjectURL，测试环境下静默跳过。
 */
export function saveDownloadedFile(file: DownloadedFile, fallbackName: string): void {
  const name = file.fileName ?? fallbackName;
  if (typeof URL === 'undefined' || typeof URL.createObjectURL !== 'function') {
    return;
  }
  const url = URL.createObjectURL(file.blob);
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = name;
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  setTimeout(() => URL.revokeObjectURL(url), 10_000);
}
