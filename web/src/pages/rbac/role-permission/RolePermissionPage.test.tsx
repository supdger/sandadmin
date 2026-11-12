import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { MemoryRouter, Route, Routes } from 'react-router';
import { afterEach, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { env } from '@/config/env';
import { clearToken, setToken } from '@/lib/authToken';
import { server } from '@/mocks/server';
import { RolePermissionPage } from './RolePermissionPage';

// jsdom 没有 ResizeObserver；Semi 的观察者组件需要一个空实现桩。
if (typeof globalThis.ResizeObserver === 'undefined') {
  globalThis.ResizeObserver = class {
    observe() {}
    unobserve() {}
    disconnect() {}
  } as unknown as typeof ResizeObserver;
}

if (typeof Range !== 'undefined' && !Range.prototype.getBoundingClientRect) {
  Range.prototype.getBoundingClientRect = () =>
    ({ width: 0, height: 0, top: 0, left: 0, right: 0, bottom: 0, x: 0, y: 0 }) as DOMRect;
}

const v1 = `${env.apiBaseUrl}/admin/v1`;

function renderPage(roleId = 'role-ops', token = 'mock-admin-token') {
  setToken(token);
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[`/system/role/${roleId}/permission`]}>
        <Routes>
          <Route
            path="/system/role/:roleId/permission"
            element={<RolePermissionPage />}
          />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('RolePermissionPage', () => {
  afterEach(() => {
    clearToken();
  });

  it('loads the role, the permission tree and saves the selection', async () => {
    renderPage('role-ops');

    // 两个查询都返回后，角色名成为页头标题。
    expect(await screen.findByText('运营专员')).toBeInTheDocument();
    expect(screen.getByText('系统管理')).toBeInTheDocument();
    expect(screen.getByText('用户管理')).toBeInTheDocument();

    // 勾「用户管理」下的查看 + 新增；查看动作是该行的锚点。
    const userRow = screen.getByText('用户管理').closest('div')!.parentElement!;
    const viewBox = userRow.querySelectorAll('input[type="checkbox"]')[0];
    await userEvent.click(viewBox);

    let putBody: Record<string, unknown> | null = null;
    server.use(
      http.put(`${v1}/roles/role-ops/permissions`, async ({ request }) => {
        putBody = (await request.json()) as Record<string, unknown>;
        return HttpResponse.json({
          data: { affected_users: 4 },
          request_id: 'test',
        });
      }),
    );

    await userEvent.click(screen.getByRole('button', { name: '保存' }));

    await waitFor(() => {
      expect(putBody).not.toBeNull();
    });
    const codes = putBody!.permission_codes as string[];
    expect(codes).toContain('system:user:index');
    // mock 夹具里 role-ops 的 data_scope=dept。
    expect(putBody!.data_scope).toBe('dept');
    expect(typeof putBody!.base_version).toBe('number');
  });

  it('shows the readonly banner and disables checkboxes for system roles', async () => {
    renderPage('role-admin');

    expect(
      await screen.findByText('这个角色是系统自带的，权限不能在这里修改'),
    ).toBeInTheDocument();
    const boxes = document.querySelectorAll(
      '.semi-checkbox input[type="checkbox"]:not(:disabled)',
    );
    expect(boxes.length).toBe(0);
  });

  it('recovers from a stale-version conflict by refetching', async () => {
    renderPage('role-ops');
    expect(await screen.findByText('运营专员')).toBeInTheDocument();

    // 让下一次 PUT 输掉乐观锁竞态。
    server.use(
      http.put(`${v1}/roles/role-ops/permissions`, () =>
        HttpResponse.json(
          {
            code: 'STALE_VERSION',
            message: '数据已被他人修改，请刷新后重试',
            request_id: 'test',
          },
          { status: 409 },
        ),
      ),
    );

    const userRow = screen.getByText('用户管理').closest('div')!.parentElement!;
    await userEvent.click(
      userRow.querySelectorAll('input[type="checkbox"]')[0],
    );
    await userEvent.click(screen.getByRole('button', { name: '保存' }));

    expect(
      await screen.findByText('这条刚被别人改过，刷新后再改'),
    ).toBeInTheDocument();
  });

  it('greys out permissions the signed-in user does not own', async () => {
    // viewer 能打开页面（有 permission:index）但没有任何可分配的码。
    renderPage('role-ops', 'mock-viewer-token');

    expect(await screen.findByText('运营专员')).toBeInTheDocument();
    const userRow = screen.getByText('用户管理').closest('div')!.parentElement!;
    const boxes = userRow.querySelectorAll('input[type="checkbox"]');
    // 每个动作勾选框都禁用：viewer 没有 system:user:* 授权。
    boxes.forEach((box) => expect(box).toBeDisabled());
  });
});
