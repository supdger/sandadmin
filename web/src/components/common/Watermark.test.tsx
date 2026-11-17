import type { ReactNode } from 'react';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';

import { clearToken, setToken } from '@/lib/authToken';
import { Watermark } from './Watermark';

function wrap({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}

describe('Watermark', () => {
  beforeEach(() => clearToken());

  it('显式文案直接作为水印内容', () => {
    const { container } = render(<Watermark text="内部资料" />, { wrapper: wrap });
    const layer = container.querySelector('[data-watermark-text]');
    expect(layer).not.toBeNull();
    expect(layer).toHaveAttribute('data-watermark-text', '内部资料');
  });

  it('缺省文案取登录人昵称加时间', async () => {
    setToken('mock-admin-token');
    const { container } = render(<Watermark />, { wrapper: wrap });
    await waitFor(() => {
      const layer = container.querySelector('[data-watermark-text]');
      expect(layer?.getAttribute('data-watermark-text')).toContain('管理员');
    });
  });

  it('遮罩层不拦截指针事件', () => {
    const { container } = render(<Watermark text="x" />, { wrapper: wrap });
    const layer = container.querySelector('[data-watermark-text]') as HTMLElement;
    expect(layer.style.pointerEvents).toBe('none');
  });
});
