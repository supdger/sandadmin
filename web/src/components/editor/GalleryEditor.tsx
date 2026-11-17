import { useRef, useState } from 'react';

import { IconImage } from '@douyinfe/semi-icons';
import { Button, Modal } from '@douyinfe/semi-ui';
import { useQuery } from '@tanstack/react-query';
import type { IDomEditor } from '@wangeditor/editor';
import { useTranslation } from 'react-i18next';

import { listFiles, type FileObject } from '@/api/v1/files';
import type { Page } from '@/api/v1/types';
import { Empty } from '@/components/Empty';
import { fileDownloadUrl } from './fileUrl';
import { WangEditor, type WangEditorProps } from './WangEditor';

export interface GalleryEditorProps extends Omit<WangEditorProps, 'onCreated'> {
  /** 图库取数；默认 /files?kind=image 只读列表。 */
  fetchImages?: (page: number, pageSize: number) => Promise<Page<FileObject>>;
  /** 文件 → <img> src；默认走下载端点。 */
  imageUrl?: (file: FileObject) => string;
}

function escapeAttr(text: string): string {
  return text.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
}

/**
 * 富文本编辑器（带图库选图）：WangEditor + 「从图库选图」，
 * 图库面板只读 GET /files，选中后把 <img> 插到光标处。
 */
export function GalleryEditor({
  fetchImages,
  imageUrl,
  readOnly,
  ...rest
}: GalleryEditorProps) {
  const { t } = useTranslation('feForm');
  const editorRef = useRef<IDomEditor | null>(null);
  const [galleryOpen, setGalleryOpen] = useState(false);

  const imagesQuery = useQuery({
    queryKey: ['v1', 'files', 'gallery'],
    queryFn: () =>
      fetchImages
        ? fetchImages(1, 60)
        : listFiles({ kind: 'image', page: 1, page_size: 60 }),
    enabled: galleryOpen,
    staleTime: 30_000,
  });

  const urlOf = imageUrl ?? ((file: FileObject) => fileDownloadUrl(file.id));

  const insertImage = (file: FileObject) => {
    const editor = editorRef.current;
    if (editor) {
      editor.restoreSelection();
      editor.dangerouslyInsertHtml(
        `<img src="${escapeAttr(urlOf(file))}" alt="${escapeAttr(file.original_name)}" />`,
      );
    }
    setGalleryOpen(false);
  };

  return (
    <div className="flex flex-col gap-2">
      {readOnly ? null : (
        <div>
          <Button size="small" icon={<IconImage />} onClick={() => setGalleryOpen(true)}>
            {t('editor.pickImage')}
          </Button>
        </div>
      )}
      <WangEditor
        {...rest}
        readOnly={readOnly}
        onCreated={(editor) => {
          editorRef.current = editor;
        }}
      />
      <Modal
        title={t('editor.galleryTitle')}
        visible={galleryOpen}
        onCancel={() => setGalleryOpen(false)}
        footer={null}
        width={640}
      >
        {(imagesQuery.data?.items.length ?? 0) === 0 && !imagesQuery.isPending ? (
          <Empty description={t('editor.galleryEmpty')} />
        ) : (
          <div className="grid grid-cols-4 gap-3">
            {(imagesQuery.data?.items ?? []).map((file) => (
              <button
                key={file.id}
                type="button"
                className="flex flex-col items-center gap-1 rounded-md border border-[var(--semi-color-border)] p-2 hover:border-[var(--semi-color-primary)]"
                onClick={() => insertImage(file)}
              >
                <img
                  src={urlOf(file)}
                  alt={file.original_name}
                  className="h-20 w-full rounded object-cover"
                />
                <span className="w-full truncate text-xs">{file.original_name}</span>
              </button>
            ))}
          </div>
        )}
      </Modal>
    </div>
  );
}
