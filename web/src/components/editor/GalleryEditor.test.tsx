import type { ReactNode } from 'react';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import type { FileObject } from '@/api/v1/files';
import type { Page } from '@/api/v1/types';

/*
 * 与 WangEditor.test 同理：jsdom 跑不动 slate，用桩把编辑器实例换成
 * 可断言的 fake editor，只验证图库面板的取数/选图/插入行为。
 */
const hoisted = vi.hoisted(() => ({
  editor: {
    restoreSelection: vi.fn(),
    dangerouslyInsertHtml: vi.fn(),
    destroy: vi.fn(),
    disable: vi.fn(),
    enable: vi.fn(),
  },
}));

vi.mock('./WangEditor', () => ({
  WangEditor: (props: { onCreated?: (editor: unknown) => void }) => {
    props.onCreated?.(hoisted.editor);
    return null;
  },
}));

import { GalleryEditor } from './GalleryEditor';

function wrap({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}

function imageFile(id: string, name: string): FileObject {
  return {
    id,
    original_name: name,
    kind: 'image',
    media_type: 'image/png',
    size_bytes: 100,
    usage_state: 'used',
    usage_label: null,
    visibility: 'private',
    purpose: 'attachment',
    uploader_id: 'u-admin',
    uploader_name: '管理员',
    create_time: '2026-01-01T00:00:00+08:00',
  };
}

const PAGE: Page<FileObject> = {
  items: [imageFile('file-a', '海报.png'), imageFile('file-b', '门店.jpg')],
  total: 2,
  page: 1,
  page_size: 60,
};

describe('GalleryEditor', () => {
  it('opens the gallery, lists images and inserts the picked one', async () => {
    const fetchImages = vi.fn(async () => PAGE);
    render(<GalleryEditor fetchImages={fetchImages} />, { wrapper: wrap });
    await userEvent.click(await screen.findByText('从图库选图'));

    await userEvent.click(await screen.findByText('海报.png'));
    await waitFor(() =>
      expect(hoisted.editor.dangerouslyInsertHtml).toHaveBeenCalledWith(
        expect.stringContaining('files/file-a/download'),
      ),
    );
    expect(fetchImages).toHaveBeenCalledWith(1, 60);
    expect(hoisted.editor.restoreSelection).toHaveBeenCalled();
    // jsdom 里 Semi 弹层的离场动画不跑，DOM 不会立刻卸载，不做关闭断言。
  });

  it('shows the empty state when the gallery has no images', async () => {
    const fetchImages = vi.fn(async () => ({ ...PAGE, items: [], total: 0 }));
    render(<GalleryEditor fetchImages={fetchImages} />, { wrapper: wrap });
    await userEvent.click(await screen.findByText('从图库选图'));
    expect(await screen.findByText('图库还没有图片')).toBeInTheDocument();
  });

  it('hides the gallery entry in readOnly mode', () => {
    render(<GalleryEditor readOnly fetchImages={async () => PAGE} />, { wrapper: wrap });
    expect(screen.queryByText('从图库选图')).not.toBeInTheDocument();
  });
});
