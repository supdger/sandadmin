import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { createMemoryRouter, RouterProvider, type RouteObject } from 'react-router';
import { afterEach, describe, expect, it } from 'vitest';

import { env } from '@/config/env';
import { clearToken, setToken } from '@/lib/authToken';
import { server } from '@/mocks/server';
import { RequireAuth } from './guards';

const base = env.apiBaseUrl;

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

function mockMe(user: { mustChangePassword: boolean }) {
  server.use(
    http.get(`${base}/me`, () =>
      HttpResponse.json({
        code: 200,
        message: 'ok',
        data: {
          id: 'u-1',
          username: 'admin',
          nickname: '管理员',
          mustChangePassword: user.mustChangePassword,
          permissions: [],
        },
      }),
    ),
  );
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
        HttpResponse.json({ code: 4010, message: '登录已过期，请重新登录', data: null }),
      ),
    );

    const router = renderAt('/');

    await waitFor(() => {
      expect(router.state.location.pathname).toBe('/login');
    });
    expect(localStorage.getItem('sandadmin.token')).toBeNull();
  });
});
