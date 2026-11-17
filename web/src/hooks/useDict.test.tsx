import type { ReactNode } from 'react';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { renderHook, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';

import { setToken } from '@/lib/authToken';
import { useDict, useDictByCode } from './useDict';

function wrapper({ children }: { children: ReactNode }) {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}

describe('useDict', () => {
  beforeEach(() => {
    setToken('mock-admin-token');
  });

  it('returns enabled items as options sorted by sort', async () => {
    const { result } = renderHook(() => useDict('dict-type-1'), { wrapper });
    await waitFor(() => expect(result.current.isPending).toBe(false));
    // dict-type-1 has six rows; the disabled one is filtered out.
    expect(result.current.options).toHaveLength(5);
    expect(result.current.options[0].label).toBe('电话咨询');
    expect(result.current.labelOf('v_item-src-3')).toBe('门店路过');
    expect(result.current.optionOf('v_item-src-3')?.color).toBe('#3491FA');
    expect(result.current.labelOf('missing-value')).toBe('');
  });

  it('stays empty without a type id', () => {
    const { result } = renderHook(() => useDict(null), { wrapper });
    expect(result.current.options).toEqual([]);
    expect(result.current.isPending).toBe(false);
  });
});

describe('useDictByCode', () => {
  beforeEach(() => {
    setToken('mock-admin-token');
  });

  it('resolves the type by code and returns its items', async () => {
    const { result } = renderHook(() => useDictByCode('user_state'), { wrapper });
    await waitFor(() => expect(result.current.options.length).toBeGreaterThan(0));
    expect(result.current.labelOf('v_item-st-1')).toBe('正常');
  });

  it('returns empty options for an unknown code', async () => {
    const { result } = renderHook(() => useDictByCode('no_such_code'), { wrapper });
    await waitFor(() => expect(result.current.isPending).toBe(false));
    expect(result.current.options).toEqual([]);
  });
});
