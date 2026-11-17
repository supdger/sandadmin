import '@douyinfe/semi-ui/react19-adapter';
import '@/i18n/i18n';

import type { ReactNode } from 'react';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterAll, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

import type { FileObject } from '@/api/v1/files';
import { setToken } from '@/lib/authToken';

const hoisted = vi.hoisted(() => ({
  uploaded: {
    id: 'file-999',
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
}));

/*
 * jsdom 里 jsdom File 进了 fetch 会炸（序列化不了），uploadFile 换成桩；
 * Semi Upload 还会调 URL.createObjectURL，jsdom 没有实现，一并桩掉。
 * 组件自己的取文件/回调/失败提示行为照测。
 */
vi.mock('@/api/v1/files', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/api/v1/files')>()),
  uploadFile: vi.fn(async () => hoisted.uploaded),
}));

import { FileField } from './FileField';

function wrap({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}

describe('FileField', () => {
  beforeAll(() => {
    vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:mock');
    vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => {});
  });
  afterAll(() => {
    vi.restoreAllMocks();
  });
  beforeEach(() => setToken('mock-admin-token'));

  it('uploads the picked file and emits the created file object', async () => {
    const onChange = vi.fn();
    const { container } = render(<FileField onChange={onChange} />, { wrapper: wrap });
    const input = container.querySelector('input[type="file"]') as HTMLInputElement;
    expect(input).not.toBeNull();
    await userEvent.upload(input, new File(['hello'], '说明.txt', { type: 'text/plain' }));
    await waitFor(() =>
      expect(onChange).toHaveBeenCalledWith(
        expect.objectContaining({ id: 'file-999', original_name: '说明.txt' }),
      ),
    );
  });

  it('renders the current file', async () => {
    render(<FileField value={hoisted.uploaded} onChange={() => {}} />, { wrapper: wrap });
    expect(await screen.findByText('说明.txt')).toBeInTheDocument();
  });
});
