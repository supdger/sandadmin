import { useMemo, useRef, useState } from 'react';

import { IconImage } from '@douyinfe/semi-icons';
import { Button, Space, Toast } from '@douyinfe/semi-ui';
import { useTranslation } from 'react-i18next';

import { uploadFile } from '@/api/v1/files';
import { renderMarkdown } from '@/lib/markdown';
import { fileDownloadUrl } from './fileUrl';

export type MarkdownMode = 'edit' | 'preview' | 'split';

export interface MarkdownEditorProps {
  value?: string;
  onChange?: (markdown: string) => void;
  placeholder?: string;
  /** 初始展示模式；运行期切换留在组件内。 */
  defaultMode?: MarkdownMode;
  /** 允许图片上传（走统一 /files 接口）。 */
  uploadImage?: boolean;
  minHeight?: number;
}

/**
 * Markdown 编辑器：编辑/预览/双栏，预览走 marked + dompurify 消毒；
 * 图片上传后把 ![name](下载地址) 插到光标处。
 */
export function MarkdownEditor({
  value = '',
  onChange,
  placeholder,
  defaultMode = 'split',
  uploadImage = true,
  minHeight = 280,
}: MarkdownEditorProps) {
  const { t } = useTranslation('feForm');
  const [mode, setMode] = useState<MarkdownMode>(defaultMode);
  const textareaRef = useRef<HTMLTextAreaElement | null>(null);
  const fileRef = useRef<HTMLInputElement | null>(null);

  const html = useMemo(() => renderMarkdown(value), [value]);
  const showEdit = mode === 'edit' || mode === 'split';
  const showPreview = mode === 'preview' || mode === 'split';

  const insertAtCursor = (text: string) => {
    const area = textareaRef.current;
    if (!area) {
      onChange?.(`${value}${text}`);
      return;
    }
    const start = area.selectionStart ?? value.length;
    const end = area.selectionEnd ?? start;
    onChange?.(`${value.slice(0, start)}${text}${value.slice(end)}`);
    requestAnimationFrame(() => {
      area.focus();
      area.selectionStart = start + text.length;
      area.selectionEnd = start + text.length;
    });
  };

  const handleFile = (file: File) => {
    uploadFile(file)
      .then((created) => {
        insertAtCursor(`![${created.original_name}](${fileDownloadUrl(created.id)})`);
      })
      .catch(() => Toast.error(t('editor.uploadFailed')));
  };

  const modes: { key: MarkdownMode; label: string }[] = [
    { key: 'edit', label: t('editor.write') },
    { key: 'split', label: t('editor.split') },
    { key: 'preview', label: t('editor.preview') },
  ];

  return (
    <div className="flex flex-col gap-2">
      <Space>
        {uploadImage ? (
          <Button size="small" icon={<IconImage />} onClick={() => fileRef.current?.click()}>
            {t('editor.uploadImage')}
          </Button>
        ) : null}
        <Space>
          {modes.map((item) => (
            <Button
              key={item.key}
              size="small"
              theme={mode === item.key ? 'solid' : 'light'}
              onClick={() => setMode(item.key)}
            >
              {item.label}
            </Button>
          ))}
        </Space>
      </Space>
      <input
        ref={fileRef}
        type="file"
        accept="image/*"
        className="hidden"
        onChange={(event) => {
          const file = event.target.files?.[0];
          if (file) {
            handleFile(file);
          }
          event.target.value = '';
        }}
      />
      <div className={showEdit && showPreview ? 'grid grid-cols-2 gap-3' : ''}>
        {showEdit ? (
          <textarea
            ref={textareaRef}
            value={value}
            placeholder={placeholder ?? t('editor.markdownPlaceholder')}
            onChange={(event) => onChange?.(event.target.value)}
            style={{ minHeight }}
            className="w-full rounded-md border border-[var(--semi-color-border)] bg-[var(--semi-color-bg-0)] p-3 text-sm leading-6 outline-none focus:border-[var(--semi-color-primary)]"
          />
        ) : null}
        {showPreview ? (
          <div
            style={{ minHeight }}
            className="w-full overflow-auto rounded-md border border-[var(--semi-color-border)] p-3 text-sm leading-6 [&_img]:max-w-full"
            // renderMarkdown 已过 dompurify，这里才能用 innerHTML。
            dangerouslySetInnerHTML={{ __html: html }}
          />
        ) : null}
      </div>
    </div>
  );
}
