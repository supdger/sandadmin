import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { createMemoryRouter, RouterProvider, type RouteObject } from 'react-router';
import { afterEach, describe, expect, it } from 'vitest';

import { env } from '@/config/env';
import { clearToken, setToken } from '@/lib/authToken';
import { server } from '@/mocks/server';
import { RequireAuth, RequirePermission } from './guards';

const base = `${env.apiBaseUrl}/admin/v1`;

function renderAt(entry: string, extraRoutes: RouteObject[] = []) {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  const router = createMemoryRouter(
    [
      { path: '/login', element: <div>登录页</div> },
      { path: '/force-change-password', element: <div>强制改密页</div> },
      {
        element: <RequireAuth />,
        children: [
          { path: '/', element: <div>工作台内容</div> },
          ...extraRoutes,
        ],
      },
    ],
    { initialEntries: [entry] },
  );
  render(
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={router} />
    </QueryClientProvider>,
  );
  return router;
}

function mockMe(user: { mustChangePassword: boolean; permissions?: string[] }) {
  server.use(
    http.get(`${base}/me`, () =>
      HttpResponse.json({
        data: {
          id: 'u-1',
          username: 'admin',
          nickname: '管理员',
          avatar: null,
          avatar_file_id: null,
          phone: '',
          email: null,
          status: 'enabled',
          is_admin: false,
          is_super: false,
          must_change_password: user.mustChangePassword,
          permissions: user.permissions ?? [],
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

/** Router mirroring the app: RequireAuth > RequirePermission > gated page. */
function renderGuarded(entry: string, permission?: string | string[]) {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  const router = createMemoryRouter(
    [
      { path: '/login', element: <div>登录页</div> },
      { path: '/403', element: <div>无权限页</div> },
      {
        element: <RequireAuth />,
        children: [
          {
            element: <RequirePermission />,
            children: [
              {
                path: '/',
                element: <div>受保护内容</div>,
                handle: permission === undefined ? {} : { permission },
              },
            ],
          },
        ],
      },
    ],
    { initialEntries: [entry] },
  );
  render(
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={router} />
    </QueryClientProvider>,
  );
  return router;
}

describe('RequireAuth', () => {
  afterEach(() => {
    clearToken();
  });

  it('redirects to /login with a redirect param when there is no token', async () => {
    const router = renderAt('/?tab=a');

    await waitFor(() => {
      expect(router.state.location.pathname).toBe('/login');
    });
    expect(router.state.location.search).toContain('redirect=%2F%3Ftab%3Da');
  });

  it('redirects to /force-change-password when the user must change password', async () => {
    setToken('t-1');
    mockMe({ mustChangePassword: true });

    const router = renderAt('/');

    await waitFor(() => {
      expect(router.state.location.pathname).toBe('/force-change-password');
    });
    expect(screen.getByText('强制改密页')).toBeInTheDocument();
  });

  it('renders the protected outlet for a normal signed-in user', async () => {
    setToken('t-1');
    mockMe({ mustChangePassword: false });

    renderAt('/');

    await screen.findByText('工作台内容');
  });

  it('drops the token and redirects to /login when the profile request fails', async () => {
    setToken('t-dead');
    server.use(
      http.get(`${base}/me`, () =>
        HttpResponse.json(
          { code: 'UNAUTHENTICATED', message: '登录已过期，请重新登录', request_id: 't' },
          { status: 401 },
        ),
      ),
    );

    const router = renderAt('/');

    await waitFor(() => {
      expect(router.state.location.pathname).toBe('/login');
    });
    expect(localStorage.getItem('sandadmin.token')).toBeNull();
  });
});

describe('RequirePermission', () => {
  afterEach(() => {
    clearToken();
  });

  it('redirects to /403 when the user lacks the route permission', async () => {
    setToken('t-1');
    mockMe({ mustChangePassword: false, permissions: ['workbench:view'] });

    const router = renderGuarded('/', 'preferences:view');

    await waitFor(() => {
      expect(router.state.location.pathname).toBe('/403');
    });
    expect(await screen.findByText('无权限页')).toBeInTheDocument();
  });

  it('renders the page when the permission is granted', async () => {
    setToken('t-1');
    mockMe({ mustChangePassword: false, permissions: ['preferences:view'] });

    renderGuarded('/', 'preferences:view');

    expect(await screen.findByText('受保护内容')).toBeInTheDocument();
  });

  it('treats * as satisfying every permission', async () => {
    setToken('t-1');
    mockMe({ mustChangePassword: false, permissions: ['*'] });

    renderGuarded('/', 'preferences:view');

    expect(await screen.findByText('受保护内容')).toBeInTheDocument();
  });

  it('lets users with no permissions through on routes without metadata', async () => {
    setToken('t-1');
    mockMe({ mustChangePassword: false, permissions: [] });

    renderGuarded('/');

    expect(await screen.findByText('受保护内容')).toBeInTheDocument();
  });
});
