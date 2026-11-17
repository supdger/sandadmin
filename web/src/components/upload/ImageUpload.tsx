import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';

import { IconUpload } from '@douyinfe/semi-icons';
import { Button, Modal, Toast, Upload } from '@douyinfe/semi-ui';
import type { FileItem } from '@douyinfe/semi-ui/lib/es/upload/interface';
import { useTranslation } from 'react-i18next';

import { deleteFile, uploadFile, type FileObject } from '@/api/v1/files';
import { fileDownloadUrl } from '@/components/editor/fileUrl';
import { confirmDanger } from '@/lib/confirmAction';
import { ImageCropper } from './ImageCropper';

export interface ImageUploadProps {
  /** 已上传图片（受控）。 */
  value?: FileObject[];
  onChange?: (files: FileObject[]) => void;
  /** 缺省 image/*。 */
  accept?: string;
  maxSizeMb?: number;
  limit?: number;
  disabled?: boolean;
  /** 上传前裁剪：true 用 4:3，或给宽高比；不传不裁。 */
  crop?: boolean | { aspectRatio?: number };
  usage?: string;
  visibility?: 'private' | 'public';
  children?: ReactNode;
}

interface PendingImage {
  uid: string;
  name: string;
  size: string;
  /** 本地预览 object URL，仅在浏览器能力可用时生成。 */
  previewUrl: string | null;
}

interface CropJob {
  uid: string;
  file: File;
  objectUrl: string | null;
}

function objectUrlOf(file: File): string | null {
  return typeof URL.createObjectURL === 'function' ? URL.createObjectURL(file) : null;
}

function imageItemOf(file: FileObject): FileItem {
  return {
    uid: file.id,
    name: file.original_name,
    status: 'success',
    size: String(file.size_bytes),
    url: fileDownloadUrl(file.id),
  };
}

/**
 * 图片上传：与 FileUpload 同一机制（POST /files + DELETE 确认删除），
 * 图片专用——缩略图预览（picture 列表），可选上传前接 ImageCropper 裁剪。
 */
export function ImageUpload({
  value = [],
  onChange,
  accept = 'image/*',
  maxSizeMb,
  limit,
  disabled = false,
  crop = false,
  usage,
  visibility,
  children,
}: ImageUploadProps) {
  const { t } = useTranslation('feMisc');
  const [pending, setPending] = useState<PendingImage[]>([]);
  const [jobs, setJobs] = useState<CropJob[]>([]);
  const valueRef = useRef(value);
  useEffect(() => {
    valueRef.current = value;
  }, [value]);
  const droppedRef = useRef(new Set<string>());
  const cropRatio = typeof crop === 'object' ? crop.aspectRatio : crop ? 4 / 3 : undefined;

  const items: FileItem[] = [
    ...value.map(imageItemOf),
    ...pending.map((p) => ({
      uid: p.uid,
      name: p.name,
      size: p.size,
      status: 'uploading' as const,
      url: p.previewUrl ?? undefined,
    })),
  ];

  // 组件卸载时回收本地预览 URL，避免 object URL 泄漏。
  useEffect(
    () => () => {
      for (const entry of pending) {
        if (entry.previewUrl) {
          URL.revokeObjectURL(entry.previewUrl);
        }
      }
      for (const j of jobs) {
        if (j.objectUrl) {
          URL.revokeObjectURL(j.objectUrl);
        }
      }
    },
    // 只在卸载时跑一次；快照取卸载当刻的队列。
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [],
  );

  const dropPending = (uid: string) => {
    setPending((list) => {
      const hit = list.find((p) => p.uid === uid);
      if (hit?.previewUrl) {
        URL.revokeObjectURL(hit.previewUrl);
      }
      return list.filter((p) => p.uid !== uid);
    });
  };

  const doUpload = (uid: string, file: File) => {
    uploadFile(file, { usage, visibility })
      .then((created) => {
        dropPending(uid);
        if (!droppedRef.current.has(uid)) {
          onChange?.([...valueRef.current, created]);
        }
        droppedRef.current.delete(uid);
      })
      .catch(() => {
        dropPending(uid);
        droppedRef.current.delete(uid);
        Toast.error(t('upload.failed'));
      });
  };

  const job = jobs[0] ?? null;

  const finishJob = () => {
    setJobs((list) => {
      const done = list[0];
      if (done?.objectUrl) {
        URL.revokeObjectURL(done.objectUrl);
      }
      return list.slice(1);
    });
  };

  const handleCropped = (blob: Blob) => {
    if (!job) {
      return;
    }
    const cropped = new File([blob], job.file.name, { type: blob.type });
    finishJob();
    doUpload(job.uid, cropped);
  };

  const handleCropCancel = () => {
    if (!job) {
      return;
    }
    droppedRef.current.add(job.uid);
    dropPending(job.uid);
    finishJob();
  };

  const handleRemove = async (item: FileItem): Promise<boolean> => {
    if (pending.some((p) => p.uid === item.uid)) {
      droppedRef.current.add(item.uid);
      dropPending(item.uid);
      return true;
    }
    const target = valueRef.current.find((f) => f.id === item.uid);
    if (!target) {
      return true;
    }
    const ok = await new Promise<boolean>((resolve) => {
      confirmDanger({
        title: t('upload.deleteConfirm'),
        content: t('upload.deleteConfirmContent'),
        okText: t('upload.confirmDelete'),
        okButtonProps: { type: 'danger' },
        onOk: () => resolve(true),
        onCancel: () => resolve(false),
      });
    });
    if (!ok) {
      return false;
    }
    try {
      await deleteFile(target.id);
    } catch {
      Toast.error(t('upload.failed'));
      return false;
    }
    onChange?.(valueRef.current.filter((f) => f.id !== target.id));
    return true;
  };

  return (
    <>
      <Upload
        accept={accept}
        disabled={disabled}
        limit={limit}
        multiple={limit === undefined || limit > 1}
        maxSize={maxSizeMb === undefined ? undefined : maxSizeMb * 1024}
        listType="picture"
        fileList={items}
        customRequest={({ file, fileInstance }) => {
          const uid = file.uid;
          setPending((list) => [
            ...list,
            {
              uid,
              name: file.name ?? fileInstance.name,
              size: String(fileInstance.size),
              previewUrl: objectUrlOf(fileInstance),
            },
          ]);
          if (crop) {
            setJobs((list) => [...list, { uid, file: fileInstance, objectUrl: objectUrlOf(fileInstance) }]);
          } else {
            doUpload(uid, fileInstance);
          }
        }}
        beforeRemove={(item) => handleRemove(item)}
        onSizeError={(file) =>
          Toast.warning(t('upload.sizeError', { name: file.name, limit: maxSizeMb ?? 0 }))
        }
        onExceed={() => Toast.warning(t('upload.exceed', { count: limit ?? 0 }))}
        onAcceptInvalid={() => Toast.warning(t('upload.failed'))}
      >
        {children ?? (
          <Button icon={<IconUpload />} theme="light" disabled={disabled}>
            {t('upload.chooseImage')}
          </Button>
        )}
      </Upload>
      <Modal
        title={t('imageCropper.title')}
        visible={job !== null}
        footer={null}
        closable={false}
        maskClosable={false}
      >
        {job ? (
          <ImageCropper
            key={job.uid}
            src={job.objectUrl ?? ''}
            aspectRatio={cropRatio}
            onConfirm={handleCropped}
            onCancel={handleCropCancel}
          />
        ) : null}
      </Modal>
    </>
  );
}
