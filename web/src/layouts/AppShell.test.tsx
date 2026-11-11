import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { createMemoryRouter, RouterProvider, type RouteObject } from 'react-router';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { env } from '@/config/env';
import { clearToken, getToken, setToken } from '@/lib/authToken';
import { server } from '@/mocks/server';
import { AppShell } from './AppShell';

// jsdom has no ResizeObserver; Semi's observer component needs a no-op stub.
if (typeof globalThis.ResizeObserver === 'undefined') {
  globalThis.ResizeObserver = class {
    observe() {}
    unobserve() {}
    disconnect() {}
  } as unknown as typeof ResizeObserver;
}

// jsdom's Range lacks getBoundingClientRect; Semi's typography ellipsis
// measurement calls it in a floating promise.
if (typeof Range !== 'undefined' && !Range.prototype.getBoundingClientRect) {
  Range.prototype.getBoundingClientRect = () =>
    ({ width: 0, height: 0, top: 0, left: 0, right: 0, bottom: 0, x: 0, y: 0 }) as DOMRect;
}

const base = env.apiBaseUrl;

function mockMe() {
  server.use(
    http.get(`${base}/me`, () =>
      HttpResponse.json({
        code: 200,
        message: 'ok',
        data: {
          id: 'u-1',
          username: 'admin',
          nickname: '管理员',
          mustChangePassword: false,
          permissions: [],
        },
      }),
    ),
  );
}

function renderShell(entry = '/') {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  const children: RouteObject[] = [
    { path: '/', element: <div>首页内容</div> },
    { path: '/preferences', element: <div>偏好页内容</div> },
    { path: '/profile', element: <div>个人设置页</div> },
    { path: '/notifications', element: <div>消息页</div> },
  ];
  const router = createMemoryRouter(
    [
      { path: '/login', element: <div>登录页</div> },
      { element: <AppShell />, children },
    ],
    { initialEntries: [entry] },
  );
  const { container } = render(
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={router} />
    </QueryClientProvider>,
  );
  return { router, nav: () => container.querySelector('.semi-navigation') as HTMLElement };
}

describe('AppShell', () => {
  beforeEach(() => {
    // The important-announcement popup would cover the header in every test;
    // individual tests re-enable it with their own server.use override.
    server.use(
      http.get(`${base}/me/announcements/popup`, () =>
        HttpResponse.json({ data: { items: [] }, request_id: 'test' }),
      ),
    );
  });

  afterEach(() => {
    clearToken();
    localStorage.removeItem('sandadmin.theme');
    document.body.removeAttribute('theme-mode');
  });

  it('renders the menu, current page and copyright footer', async () => {
    setToken('mock-admin-token');
    mockMe();

    const { nav } = renderShell('/');

    await waitFor(() => expect(nav()).toBeTruthy());
    expect(within(nav()).getByText('工作台')).toBeInTheDocument();

    // The nested items live inside the collapsed "系统设置" group — open it.
    await userEvent.click(within(nav()).getByText('系统设置'));
    expect(await within(nav()).findByText('偏好设置')).toBeInTheDocument();
    // Data-domain pages are real routes now, so the mock advertises them too.
    expect(within(nav()).getByText('选项管理')).toBeInTheDocument();
    expect(within(nav()).getByText('文件管理')).toBeInTheDocument();
    expect(within(nav()).getByText('公告管理')).toBeInTheDocument();

    expect(screen.getByText('首页内容')).toBeInTheDocument();
    expect(screen.getByText(env.copyright)).toBeInTheDocument();
    expect(await screen.findByText('管理员')).toBeInTheDocument();
  });

  it('toggles the theme attribute and persists the choice locally only', async () => {
    setToken('mock-admin-token');
    mockMe();
    let putCalls = 0;
    server.use(
      http.put(`${base}/me/preferences`, () => {
        putCalls += 1;
        return HttpResponse.json({ code: 200, message: 'ok', data: null });
      }),
    );

    const { nav } = renderShell('/');
    await waitFor(() => expect(nav()).toBeTruthy());

    const toggle = screen.getByRole('button', { name: '切换到深色模式' });
    await userEvent.click(toggle);

    expect(document.body.getAttribute('theme-mode')).toBe('dark');
    expect(localStorage.getItem('sandadmin.theme')).toBe('dark');
    expect(screen.getByRole('button', { name: '切换到浅色模式' })).toBeInTheDocument();
    // Theme is a per-device setting — no save request may be sent.
    expect(putCalls).toBe(0);
  });

  it('keeps the page usable and offers a retry when the menu request fails', async () => {
    setToken('mock-admin-token');
    mockMe();
    let calls = 0;
    server.use(
      http.get(`${base}/me/menus`, () => {
        calls += 1;
        if (calls === 1) {
          return HttpResponse.json({ code: 5000, message: '服务开了小差', data: null });
        }
        return HttpResponse.json({
          code: 200,
          message: 'ok',
          data: [{ id: 'workbench', parentId: null, title: '工作台', path: '/', order: 1 }],
        });
      }),
    );

    const { nav } = renderShell('/');

    // The content area still renders — no white screen.
    expect(await screen.findByText('首页内容')).toBeInTheDocument();
    expect(await screen.findByText('菜单加载失败了')).toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: '重试' }));
    await waitFor(() => expect(nav()).toBeTruthy());
    expect(within(nav()).getByText('工作台')).toBeInTheDocument();
  });

  it('shows the unified system notice when the menu request hits HTTP 5xx', async () => {
    setToken('mock-admin-token');
    mockMe();
    let calls = 0;
    server.use(
      http.get(`${base}/me/menus`, () => {
        calls += 1;
        if (calls === 1) {
          return HttpResponse.json({ code: 5000, message: 'boom', data: null }, { status: 500 });
        }
        return HttpResponse.json({
          code: 200,
          message: 'ok',
          data: [{ id: 'workbench', parentId: null, title: '工作台', path: '/', order: 1 }],
        });
      }),
    );

    const { nav } = renderShell('/');

    // The content area still renders — no white screen.
    expect(await screen.findByText('首页内容')).toBeInTheDocument();
    expect(await screen.findByText('系统出了点问题，请稍后再试')).toBeInTheDocument();
    expect(screen.queryByText('菜单加载失败了')).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: '复制问题信息' })).toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: '重试' }));
    await waitFor(() => expect(nav()).toBeTruthy());
    expect(within(nav()).getByText('工作台')).toBeInTheDocument();
  });

  it('logs out via the user dropdown and lands on /login', async () => {
    setToken('mock-admin-token');
    mockMe();

    const { router, nav } = renderShell('/');
    await waitFor(() => expect(nav()).toBeTruthy());

    await userEvent.click(await screen.findByText('管理员'));
    await userEvent.click(await screen.findByText('退出登录'));

    await waitFor(() => {
      expect(router.state.location.pathname).toBe('/login');
    });
    expect(getToken()).toBeNull();
  });

  it('shows the backend reason when the logout request is rejected', async () => {
    setToken('mock-admin-token');
    mockMe();
    server.use(
      http.post(`${base}/auth/logout`, () =>
        HttpResponse.json({
          code: 4030,
          message: '当前账号为只读权限，无法退出登录',
          data: null,
        }),
      ),
    );

    const { router, nav } = renderShell('/');
    await waitFor(() => expect(nav()).toBeTruthy());

    await userEvent.click(await screen.findByText('管理员'));
    await userEvent.click(await screen.findByText('退出登录'));

    // The session still ends locally, but the rejection is not silent.
    expect(
      await screen.findByText('当前账号为只读权限，无法退出登录'),
    ).toBeInTheDocument();
    await waitFor(() => {
      expect(router.state.location.pathname).toBe('/login');
    });
    expect(getToken()).toBeNull();
  });

  it('shows the notification bell and personal entries in the avatar menu', async () => {
    setToken('mock-admin-token');
    mockMe();

    const { router, nav } = renderShell('/');
    await waitFor(() => expect(nav()).toBeTruthy());

    // Bell with unread badge (mock returns 20 notifications + 3 announcements).
    expect(await screen.findByRole('button', { name: '消息' })).toBeInTheDocument();
    expect(await screen.findByText('23')).toBeInTheDocument();

    // Open the bell panel: latest items + mark-all + view-all actions.
    await userEvent.click(screen.getByRole('button', { name: '消息' }));
    expect(await screen.findByText('全部标为已读')).toBeInTheDocument();
    await userEvent.click(screen.getByText('查看全部'));
    await waitFor(() => {
      expect(router.state.location.pathname).toBe('/notifications');
    });
  });

  it('links 个人设置 and 修改密码 from the avatar menu', async () => {
    setToken('mock-admin-token');
    mockMe();

    const { router, nav } = renderShell('/');
    await waitFor(() => expect(nav()).toBeTruthy());

    await userEvent.click(await screen.findByText('管理员'));
    await userEvent.click(await screen.findByText('个人设置'));
    await waitFor(() => {
      expect(router.state.location.pathname).toBe('/profile');
    });
  });

  it('pops an important unread announcement and marks it read on 知道了', async () => {
    setToken('mock-admin-token');
    mockMe();
    let readCalls = 0;
    server.use(
      http.get(`${base}/me/announcements/popup`, () =>
        HttpResponse.json({
          data: {
            items: [
              {
                id: 'ann-002',
                title: '新版选项管理上线',
                content: '<p>选项管理模块已全面升级</p>',
                level: 'important',
                published_time: '2026-11-01T10:00:00Z',
                expire_time: null,
              },
            ],
          },
          request_id: 'test',
        }),
      ),
      http.post(`${base}/announcements/ann-002/read`, () => {
        readCalls += 1;
        return HttpResponse.json({ data: { read: true }, request_id: 'test' });
      }),
    );

    const { nav } = renderShell('/');
    await waitFor(() => expect(nav()).toBeTruthy());

    expect(await screen.findByText('新版选项管理上线')).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: '知道了' }));
    await waitFor(() => expect(readCalls).toBe(1));
    await waitFor(() => {
      expect(screen.queryByText('新版选项管理上线')).not.toBeInTheDocument();
    });
  });
});
