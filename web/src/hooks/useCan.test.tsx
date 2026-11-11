import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { renderHook, waitFor } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import type { ReactNode } from 'react';
import { describe, expect, it } from 'vitest';

import { env } from '@/config/env';
import { server } from '@/mocks/server';
import { hasPermission, useCan } from './useCan';

const base = `${env.apiBaseUrl}/admin/v1`;

function mockPermissions(permissions: string[]) {
  server.use(
    http.get(`${base}/me`, () =>
      HttpResponse.json({
        data: {
          id: 'u-1',
          username: 'u',
          nickname: 'U',
          avatar: null,
          avatar_file_id: null,
          phone: '',
          email: null,
          status: 'enabled',
          is_admin: false,
          is_super: false,
          must_change_password: false,
          permissions,
          dept_id: null,
          dept_name: null,
          post_id: null,
          post_name: null,
          role_ids: [],
          role_names: [],
          last_login_time: null,
          create_time: '2026-01-01T00:00:00Z',
        },
        request_id: 't',
      }),
    ),
  );
}

function wrapper({ children }: { children: ReactNode }) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
}

describe('hasPermission', () => {
  it('lets the * wildcard satisfy any permission', () => {
    expect(hasPermission(['*'], 'workbench:view')).toBe(true);
    expect(hasPermission(['*'], ['a:b', 'c:d'])).toBe(true);
  });

  it('requires every permission when an array is given', () => {
    expect(hasPermission(['a:b', 'c:d'], ['a:b', 'c:d'])).toBe(true);
    expect(hasPermission(['a:b'], ['a:b', 'c:d'])).toBe(false);
  });

  it('matches exact strings otherwise', () => {
    expect(hasPermission(['workbench:view'], 'workbench:view')).toBe(true);
    expect(hasPermission(['workbench:view'], 'workbench:write')).toBe(false);
    expect(hasPermission([], 'workbench:view')).toBe(false);
  });
});

describe('useCan', () => {
  it('reflects the permissions returned by /me', async () => {
    mockPermissions(['workbench:view']);
    const { result } = renderHook(() => useCan('workbench:view'), { wrapper });
    await waitFor(() => expect(result.current).toBe(true));
  });

  it('is false for a missing permission', async () => {
    mockPermissions(['workbench:view']);
    const { result } = renderHook(() => useCan('workbench:write'), { wrapper });
    await waitFor(() => expect(result.current).toBe(false));
  });
});
