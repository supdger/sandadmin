import '@douyinfe/semi-ui/react19-adapter';
import '@/i18n/i18n';

import type { ReactNode } from 'react';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterAll, beforeAll, describe, expect, it, vi } from 'vitest';

import type { FileObject } from '@/api/v1/files';

const hoisted = vi.hoisted(() => ({
  uploaded: {
    id: 'file-900',
    original_name: '说明.txt',
    kind: 'document',
    media_type: 'text/plain',
    size_bytes: 5,
    usage_state: 'unused',
    usage_label: null,
    visibility: 'private',
    purpose: 'attachment',
    uploader_id: 'u-admin',
    uploader_name: '管理员',
    create_time: '2026-01-01T00:00:00+08:00',
  } as FileObject,
  deleteFile: vi.fn(async () => ({})),
}));

// jsdom File 过不了 fetch 序列化，uploadFile/deleteFile 换桩测组件行为。
vi.mock('@/api/v1/files', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/api/v1/files')>()),
  uploadFile: vi.fn(async () => hoisted.uploaded),
  deleteFile: hoisted.deleteFile,
}));

import { FileUpload } from './FileUpload';

function wrap({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}

describe('FileUpload', () => {
  beforeAll(() => {
    vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:mock');
    vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => {});
  });
  afterAll(() => {
    vi.restoreAllMocks();
  });

  it('选文件上传成功后回调新文件', async () => {
    const onChange = vi.fn();
    const { container } = render(<FileUpload onChange={onChange} />, { wrapper: wrap });
    const input = container.querySelector('input[type="file"]') as HTMLInputElement;
    await userEvent.upload(input, new File(['hello'], '说明.txt', { type: 'text/plain' }));
    await waitFor(() =>
      expect(onChange).toHaveBeenCalledWith([
        expect.objectContaining({ id: 'file-900' }),
      ]),
    );
  });

  it('渲染已传列表项', () => {
    render(<FileUpload value={[hoisted.uploaded]} />, { wrapper: wrap });
    expect(screen.getByText('说明.txt')).toBeInTheDocument();
  });

  it('删除已传文件先弹确认，确认后调 deleteFile 并回写', async () => {
    const onChange = vi.fn();
    const { container } = render(<FileUpload value={[hoisted.uploaded]} onChange={onChange} />, {
      wrapper: wrap,
    });
    const close = container.querySelector('.semi-upload-file-card-close') as HTMLElement;
    expect(close).not.toBeNull();
    fireEvent.click(close);

    // Semi 确认框按钮的可访问名是 aria-label（confirm/cancel），不是按钮文案。
    await userEvent.click(await screen.findByRole('button', { name: 'confirm' }));
    await waitFor(() => expect(hoisted.deleteFile).toHaveBeenCalledWith('file-900'));
    expect(onChange).toHaveBeenCalledWith([]);
  });
});
