import { IconUpload } from '@douyinfe/semi-icons';
import { Button, Toast, Upload } from '@douyinfe/semi-ui';
import type { FileItem } from '@douyinfe/semi-ui/lib/es/upload/interface';
import { useTranslation } from 'react-i18next';

import { uploadFile, type FileObject } from '@/api/v1/files';

export interface FileFieldProps {
  /** 当前绑定的文件对象；null 表示未选。 */
  value?: FileObject | null;
  onChange?: (value: FileObject | null) => void;
  accept?: string;
  disabled?: boolean;
}

function itemOf(file: FileObject): FileItem {
  return {
    uid: file.id,
    name: file.original_name,
    status: 'success',
    size: String(file.size_bytes),
    preview: false,
  };
}

/** 单文件上传字段：走统一 /files 上传，字段值是创建出来的 FileObject。 */
export function FileField({ value, onChange, accept, disabled }: FileFieldProps) {
  const { t } = useTranslation('feForm');

  return (
    <Upload
      accept={accept}
      disabled={disabled}
      limit={1}
      fileList={value ? [itemOf(value)] : []}
      customRequest={({ fileInstance, onSuccess, onError }) => {
        uploadFile(fileInstance)
          .then((file) => {
            onSuccess(file);
            onChange?.(file);
          })
          .catch(() => {
            onError({ status: 0 });
            Toast.error(t('editor.uploadFailed'));
          });
      }}
      onRemove={() => onChange?.(null)}
    >
      <Button icon={<IconUpload />} theme="light" disabled={disabled}>
        {t('form.uploadFile')}
      </Button>
    </Upload>
  );
}
