import { useEffect, useMemo } from 'react';

import { IconFile, IconImage } from '@douyinfe/semi-icons';
import { useQuery } from '@tanstack/react-query';

import { downloadFile, type FileObject } from '@/api/files';
import { blobObjectUrl } from './fileActions';

const KIND_ICON: Record<FileObject['kind'], typeof IconFile> = {
  image: IconImage,
  document: IconFile,
  sheet: IconFile,
  archive: IconFile,
  other: IconFile,
};

interface FileThumbProps {
  file: FileObject;
  size?: number;
}

/**
 * 文件缩略图：图片懒加载真实内容，其余类型显示类型图标。
 * 图片 blob 地址随组件卸载回收。
 */
export function FileThumb({ file, size = 32 }: FileThumbProps) {
  const isImage = file.kind === 'image';

  const imageBlob = useQuery({
    queryKey: ['file-blob', file.id],
    queryFn: () => downloadFile(file.id),
    enabled: isImage,
    staleTime: 5 * 60_000,
    retry: false,
  });

  // blob 地址只随数据变化重建；卸载或换文件时统一回收。
  // 个别环境（如测试 DOM）没有 createObjectURL，退回类型图标而不是让整页崩掉。
  const objectUrl = useMemo(() => {
    if (!isImage || !imageBlob.data) return null;
    try {
      return blobObjectUrl(imageBlob.data);
    } catch {
      return null;
    }
  }, [isImage, imageBlob.data]);
  useEffect(() => {
    if (!objectUrl) return;
    return () => URL.revokeObjectURL(objectUrl);
  }, [objectUrl]);

  if (objectUrl) {
    return (
      <img
        src={objectUrl}
        alt={file.original_name}
        className="rounded border border-[var(--semi-color-border)] object-cover"
        style={{ width: size, height: size }}
      />
    );
  }

  const Icon = KIND_ICON[file.kind] ?? IconFile;
  return (
    <span
      className="inline-flex items-center justify-center rounded border border-[var(--semi-color-border)] bg-[var(--semi-color-fill-0)] text-[var(--semi-color-text-2)]"
      style={{ width: size, height: size }}
    >
      <Icon size="small" />
    </span>
  );
}
