import '@douyinfe/semi-ui/react19-adapter';
import '@/i18n/i18n';

import type { ReactNode } from 'react';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import type { FileObject } from '@/api/v1/files';
import { setToken } from '@/lib/authToken';

const hoisted = vi.hoisted(() => ({
  uploaded: {
    id: 'file-9',
    original_name: '图.png',
    kind: 'image',
    media_type: 'image/png',
    size_bytes: 3,
    usage_state: 'unused',
    usage_label: null,
    visibility: 'private',
    purpose: 'attachment',
    uploader_id: 'u-admin',
    uploader_name: '管理员',
    create_time: '2026-01-01T00:00:00+08:00',
  } as FileObject,
}));

// jsdom File 过不了 fetch 序列化，uploadFile 换桩；图片插入/Toast 行为照测。
vi.mock('@/api/v1/files', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/api/v1/files')>()),
  uploadFile: vi.fn(async () => hoisted.uploaded),
}));

import { MarkdownEditor } from './MarkdownEditor';

function wrap({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}

describe('MarkdownEditor', () => {
  beforeEach(() => setToken('mock-admin-token'));

  it('edits markdown and renders a sanitized preview', async () => {
    const onChange = vi.fn();
    render(<MarkdownEditor value="# 标题" onChange={onChange} />, { wrapper: wrap });
    // 默认双栏：编辑区 + 预览区同时存在。
    const area = await screen.findByPlaceholderText('支持 Markdown 语法');
    expect(area).toHaveValue('# 标题');
    expect(document.querySelector('h1')?.textContent).toBe('标题');

    await userEvent.type(area, '追加');
    expect(onChange).toHaveBeenCalled();
  });

  it('switches between edit, split and preview modes', async () => {
    render(<MarkdownEditor value="正文" defaultMode="edit" />, { wrapper: wrap });
    expect(await screen.findByPlaceholderText('支持 Markdown 语法')).toBeInTheDocument();

    await userEvent.click(screen.getByText('预览'));
    expect(screen.queryByPlaceholderText('支持 Markdown 语法')).not.toBeInTheDocument();
    expect(screen.getByText('正文')).toBeInTheDocument();

    await userEvent.click(screen.getByText('编辑'));
    expect(screen.getByPlaceholderText('支持 Markdown 语法')).toBeInTheDocument();
  });

  it('uploads a picked image and inserts the markdown link', async () => {
    const onChange = vi.fn();
    const { container } = render(
      <MarkdownEditor value="" onChange={onChange} />,
      { wrapper: wrap },
    );
    const input = container.querySelector('input[type="file"]') as HTMLInputElement;
    await userEvent.upload(input, new File(['png'], '图.png', { type: 'image/png' }));
    await waitFor(() =>
      expect(onChange).toHaveBeenCalledWith(
        expect.stringMatching(/!\[图\.png\]\(.*files\/file-9\/download\)/),
      ),
    );
  });
});
