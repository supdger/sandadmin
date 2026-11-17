import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';

import { IconUpload } from '@douyinfe/semi-icons';
import { Button, Toast, Upload } from '@douyinfe/semi-ui';
import type { FileItem } from '@douyinfe/semi-ui/lib/es/upload/interface';
import { useTranslation } from 'react-i18next';

import { deleteFile, uploadFile, type FileObject } from '@/api/v1/files';
import { confirmDanger } from '@/lib/confirmAction';

export interface FileUploadProps {
  /** 已上传文件（受控）；新增/删除都经 onChange 回写。 */
  value?: FileObject[];
  onChange?: (files: FileObject[]) => void;
  accept?: string;
  /** 单文件大小上限 MB；Semi 的 maxSize 是 KB，这里换算。 */
  maxSizeMb?: number;
  /** 最多几个文件；缺省不限。 */
  limit?: number;
  disabled?: boolean;
  /** 透传给 POST /files 的用途与可见性。 */
  usage?: string;
  visibility?: 'private' | 'public';
  /** 触发器内容；缺省为「选择文件」按钮。 */
  children?: ReactNode;
}

interface PendingItem {
  uid: string;
  name: string;
  size: string;
}

function itemOf(file: FileObject): FileItem {
  return {
    uid: file.id,
    name: file.original_name,
    status: 'success',
    size: String(file.size_bytes),
  };
}

function confirmRemove(title: string, content: string, okText: string): Promise<boolean> {
  return new Promise((resolve) => {
    confirmDanger({
      title,
      content,
      okText,
      okButtonProps: { type: 'danger' },
      onOk: () => resolve(true),
      onCancel: () => resolve(false),
    });
  });
}

/**
 * 文件上传：Semi Upload 的皮，走 POST /files（adminUpload 统一封装，
 * Cookie 凭据）。已传列表项的删除先确认再调 DELETE /files/{id}。
 * 分片/断点续传后端未开放（T-7），大文件先按整传处理；fetch 没有
 * 上传进度事件，进行中的条目共用 uploading 态，不做假百分比。
 */
export function FileUpload({
  value = [],
  onChange,
  accept,
  maxSizeMb,
  limit,
  disabled = false,
  usage,
  visibility,
  children,
}: FileUploadProps) {
  const { t } = useTranslation('feMisc');
  const [pending, setPending] = useState<PendingItem[]>([]);
  const valueRef = useRef(value);
  useEffect(() => {
    valueRef.current = value;
  }, [value]);
  // 上传中被用户移除的 uid：响应落地时不再回写 value。
  const droppedRef = useRef(new Set<string>());

  const items: FileItem[] = [
    ...value.map(itemOf),
    ...pending.map((p) => ({
      uid: p.uid,
      name: p.name,
      size: p.size,
      status: 'uploading' as const,
    })),
  ];

  const handleRemove = async (item: FileItem): Promise<boolean> => {
    if (pending.some((p) => p.uid === item.uid)) {
      droppedRef.current.add(item.uid);
      setPending((list) => list.filter((p) => p.uid !== item.uid));
      return true;
    }
    const target = valueRef.current.find((f) => f.id === item.uid);
    if (!target) {
      return true;
    }
    const ok = await confirmRemove(
      t('upload.deleteConfirm'),
      t('upload.deleteConfirmContent'),
      t('upload.confirmDelete'),
    );
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
    <Upload
      accept={accept}
      disabled={disabled}
      limit={limit}
      multiple={limit === undefined || limit > 1}
      maxSize={maxSizeMb === undefined ? undefined : maxSizeMb * 1024}
      fileList={items}
      customRequest={({ file, fileInstance, onSuccess, onError }) => {
        const uid = file.uid;
        setPending((list) => [
          ...list,
          { uid, name: file.name ?? fileInstance.name, size: String(fileInstance.size) },
        ]);
        uploadFile(fileInstance, { usage, visibility })
          .then((created) => {
            setPending((list) => list.filter((p) => p.uid !== uid));
            onSuccess(created);
            if (!droppedRef.current.has(uid)) {
              onChange?.([...valueRef.current, created]);
            }
            droppedRef.current.delete(uid);
          })
          .catch(() => {
            setPending((list) => list.filter((p) => p.uid !== uid));
            onError({ status: 0 });
            Toast.error(t('upload.failed'));
          });
      }}
      beforeRemove={(item) => handleRemove(item)}
      onSizeError={(file) =>
        Toast.warning(
          t('upload.sizeError', { name: file.name, limit: maxSizeMb ?? 0 }),
        )
      }
      onExceed={() => Toast.warning(t('upload.exceed', { count: limit ?? 0 }))}
    >
      {children ?? (
        <Button icon={<IconUpload />} theme="light" disabled={disabled}>
          {t('upload.choose')}
        </Button>
      )}
    </Upload>
  );
}
