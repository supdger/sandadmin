import { useEffect, useMemo } from 'react';

import { useQuery } from '@tanstack/react-query';

import { downloadFile } from '@/api/v1/files';

/**
 * 把「图片引用」解析成可给 <img src> 的地址：
 * - ref 本身就是地址（/ 开头、http(s)、data:、blob:）→ 直接回传；
 * - 否则按契约 FileObject.id 处理，走 /files/{id}/download 授权下载
 *   后建 blob URL，卸载/换图时 revoke。
 * 下载还没完成或失败时返回 null，由调用方决定占位。
 */
export function useFileImageUrl(ref: string | null | undefined): string | null {
  const isFileRef = Boolean(ref) && !/^(\/|https?:|data:|blob:)/i.test(ref!);

  const blobQuery = useQuery({
    queryKey: ['file-image', ref],
    queryFn: () => downloadFile(ref!),
    enabled: isFileRef,
    staleTime: 5 * 60_000,
    retry: false,
  });

  const url = useMemo(() => {
    if (!isFileRef) return ref ?? null;
    if (!blobQuery.data) return null;
    try {
      return URL.createObjectURL(blobQuery.data.blob);
    } catch {
      // jsdom 等环境没有 createObjectURL 时回退空，不挡渲染。
      return null;
    }
  }, [isFileRef, ref, blobQuery.data]);

  useEffect(() => {
    if (!isFileRef || !url || !url.startsWith('blob:')) return;
    return () => {
      URL.revokeObjectURL(url);
    };
  }, [isFileRef, url]);

  return url;
}
