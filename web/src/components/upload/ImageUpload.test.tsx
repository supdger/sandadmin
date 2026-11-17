import '@douyinfe/semi-ui/react19-adapter';
import '@/i18n/i18n';

import type { ReactNode } from 'react';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterAll, beforeAll, describe, expect, it, vi } from 'vitest';

import type { FileObject } from '@/api/v1/files';

const hoisted = vi.hoisted(() => ({
  uploaded: {
    id: 'file-901',
    original_name: '图.png',
    kind: 'image',
    media_type: 'image/png',
    size_bytes: 5,
    usage_state: 'unused',
    usage_label: null,
    visibility: 'private',
    purpose: 'attachment',
    uploader_id: 'u-admin',
    uploader_name: '管理员',
    create_time: '2026-01-01T00:00:00+08:00',
  } as FileObject,
}));

vi.mock('@/api/v1/files', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/api/v1/files')>()),
  uploadFile: vi.fn(async () => hoisted.uploaded),
  deleteFile: vi.fn(async () => ({})),
}));

import { ImageUpload } from './ImageUpload';

function wrap({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}

describe('ImageUpload', () => {
  beforeAll(() => {
    vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:mock');
    vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => {});
  });
  afterAll(() => {
    vi.restoreAllMocks();
  });

  it('选图直传成功后回调新文件', async () => {
    const onChange = vi.fn();
    const { container } = render(<ImageUpload onChange={onChange} />, { wrapper: wrap });
    const input = container.querySelector('input[type="file"]') as HTMLInputElement;
    await userEvent.upload(input, new File(['x'], '图.png', { type: 'image/png' }));
    await waitFor(() =>
      expect(onChange).toHaveBeenCalledWith([
        expect.objectContaining({ id: 'file-901' }),
      ]),
    );
  });

  it('crop 开启时先弹裁剪框而不是直接上传', async () => {
    const onChange = vi.fn();
    const { container } = render(<ImageUpload crop onChange={onChange} />, { wrapper: wrap });
    const input = container.querySelector('input[type="file"]') as HTMLInputElement;
    await userEvent.upload(input, new File(['x'], '图.png', { type: 'image/png' }));
    expect(await screen.findByTestId('image-cropper')).toBeInTheDocument();
    expect(onChange).not.toHaveBeenCalled();
  });

  it('取消裁剪关闭弹层且不上传', async () => {
    const onChange = vi.fn();
    const { container } = render(<ImageUpload crop onChange={onChange} />, { wrapper: wrap });
    const input = container.querySelector('input[type="file"]') as HTMLInputElement;
    await userEvent.upload(input, new File(['x'], '图.png', { type: 'image/png' }));
    await screen.findByTestId('image-cropper');
    await userEvent.click(screen.getByRole('button', { name: '取消' }));
    await waitFor(() => {
      expect(screen.queryByTestId('image-cropper')).not.toBeInTheDocument();
    });
    expect(onChange).not.toHaveBeenCalled();
  });
});
