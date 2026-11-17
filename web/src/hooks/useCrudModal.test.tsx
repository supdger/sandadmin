import type { ReactNode } from 'react';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, renderHook, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { adminPost } from '@/lib/adminClient';
import { setToken } from '@/lib/authToken';
import { useCrudModal } from './useCrudModal';

interface Row {
  id: string;
  name: string;
}

function wrapper({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}

function createRow(name: string) {
  return adminPost('/test-rows', { name });
}

describe('useCrudModal', () => {
  beforeEach(() => {
    setToken('mock-admin-token');
  });

  it('opens create/edit and closes back to idle', () => {
    const { result } = renderHook(
      () => useCrudModal<Row, { name: string }>({ submit: (v) => createRow(v.name) }),
      { wrapper },
    );
    expect(result.current.visible).toBe(false);

    act(() => result.current.openCreate());
    expect(result.current.mode).toBe('create');
    expect(result.current.record).toBeNull();

    const row = { id: 'row-1', name: '旧名' };
    act(() => result.current.openEdit(row));
    expect(result.current.mode).toBe('edit');
    expect(result.current.record).toBe(row);

    act(() => result.current.close());
    expect(result.current.visible).toBe(false);
    expect(result.current.mode).toBeNull();
  });

  it('passes record and idempotency key to submit, closes on success', async () => {
    const seen: { record: Row | null; key: string }[] = [];
    const onSuccess = vi.fn();
    const { result } = renderHook(
      () =>
        useCrudModal<Row, { name: string }>({
          submit: async (values, record, key) => {
            seen.push({ record, key });
            await createRow(values.name);
          },
          onSuccess,
        }),
      { wrapper },
    );

    act(() => result.current.openCreate());
    const firstKey = result.current.idempotencyKey;
    act(() => result.current.submit({ name: '新记录' }));
    await waitFor(() => expect(result.current.visible).toBe(false));

    expect(seen).toEqual([{ record: null, key: firstKey }]);
    expect(onSuccess).toHaveBeenCalledWith('create', null);
  });

  it('keeps open and exposes submitError when the call fails', async () => {
    const { result } = renderHook(
      () =>
        useCrudModal<Row, { name: string }>({
          submit: (v) => createRow(v.name),
          onError: () => {},
        }),
      { wrapper },
    );

    act(() => result.current.openCreate());
    act(() => result.current.submit({ name: '' }));
    await waitFor(() => expect(result.current.submitError).not.toBeNull());
    expect(result.current.visible).toBe(true);
  });
});
