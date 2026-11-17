import type { ReactNode } from 'react';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';

import { setToken } from '@/lib/authToken';
import { DictLabel } from './DictLabel';

function wrap({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}

describe('DictLabel', () => {
  beforeEach(() => setToken('mock-admin-token'));

  it('maps the value to its dict label with the configured color', async () => {
    render(<DictLabel code="user_state" value="v_item-st-2" />, { wrapper: wrap });
    const tag = await screen.findByText('冻结');
    // 颜色打在 Tag 容器上，不在内层文字节点。
    expect(tag.closest('.semi-tag')).toHaveStyle({ backgroundColor: '#D83931' });
  });

  it('falls back to the placeholder for unknown values', async () => {
    render(<DictLabel code="user_state" value="v_gone" />, { wrapper: wrap });
    // 字典加载完仍查不到 → 兜底占位符。
    expect(await screen.findByText('—')).toBeInTheDocument();
  });

  it('uses the caller fallback when provided', async () => {
    render(<DictLabel code="user_state" value={null} fallback="未设置" />, { wrapper: wrap });
    expect(await screen.findByText('未设置')).toBeInTheDocument();
  });
});
