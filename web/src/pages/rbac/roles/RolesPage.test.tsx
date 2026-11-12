import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router';
import { afterEach, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { clearToken, setToken } from '@/lib/authToken';
import { RolesPage } from './RolesPage';

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

function renderPage(initialEntry = '/system/role') {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[initialEntry]}>
        <RolesPage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('RolesPage', () => {
  afterEach(() => {
    clearToken();
  });

  it('renders the role list with scope, status and user counts', async () => {
    setToken('mock-admin-token');
    renderPage();

    expect(await screen.findByText('系统管理员')).toBeInTheDocument();
    expect(screen.getByText('技术负责人')).toBeInTheDocument();
    expect(screen.getByText('本部门及下级部门')).toBeInTheDocument();
    expect(screen.getByText('已停用')).toBeInTheDocument();
    expect(screen.getAllByText('系统自带').length).toBeGreaterThan(0);
    // 「设置权限」收在「更多」菜单里；打开技术负责人那一行的菜单能看到。
    const techRow = screen.getByText('技术负责人').closest('tr')!;
    await userEvent.click(
      within(techRow).getByRole('button', { name: '更多' }),
    );
    expect(await screen.findByText('设置权限')).toBeInTheDocument();
  });

  it('honours URL-backed keyword and status filters', async () => {
    setToken('mock-admin-token');
    renderPage('/system/role?keyword=%E8%BF%90%E8%90%A5&status=disabled');

    // keyword=运营 按名称命中「运营专员」；status=disabled 又把它排除，
    // 所以显示筛选空态而不是任何角色行。
    expect(await screen.findByText('没有找到符合条件的角色')).toBeInTheDocument();
    expect(screen.queryByText('运营专员')).not.toBeInTheDocument();
  });

  it('creates a role through the side sheet and lists it afterwards', async () => {
    setToken('mock-admin-token');
    renderPage();

    await screen.findByText('系统管理员');
    await userEvent.click(
      await screen.findByRole('button', { name: /添加角色/ }),
    );

    const nameInput = await screen.findByPlaceholderText('例如“财务”“部门主管”');
    expect(nameInput).toBeInTheDocument();

    await userEvent.type(nameInput, '测试角色');
    await userEvent.click(screen.getByRole('button', { name: '保存并设置权限' }));

    // 抽屉关闭，失效重取的列表带出新角色。
    // （SideSheet 退出动画在 jsdom 下走不完，所以断言刷新后的行，
    // 而不是断言抽屉消失。）
    const table = document.querySelector('.semi-table');
    await waitFor(() => {
      expect(within(table as HTMLElement).getByText('测试角色')).toBeInTheDocument();
    });
  });

  it('shows the unified system notice when the list request fails', async () => {
    setToken('mock-admin-token');
    renderPage('/system/role?keyword=__error__');

    expect(await screen.findByText('系统出了点问题，请稍后再试')).toBeInTheDocument();
    expect(screen.getByText('复制问题信息')).toBeInTheDocument();
  });

  it('hides write actions for a read-only user', async () => {
    setToken('mock-viewer-token');
    renderPage();

    expect(await screen.findByText('系统管理员')).toBeInTheDocument();
    // viewer 有 system:role:index + permission:index，但没有写权限。
    expect(screen.queryByRole('button', { name: /添加角色/ })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: '编辑' })).not.toBeInTheDocument();
    // 「更多」里只剩「设置权限」，没有复制/停用/删除。
    const adminRow = screen.getByText('系统管理员').closest('tr')!;
    await userEvent.click(
      within(adminRow).getByRole('button', { name: '更多' }),
    );
    expect(await screen.findByText('设置权限')).toBeInTheDocument();
    expect(screen.queryByText('复制')).not.toBeInTheDocument();
    expect(screen.queryByText('删除')).not.toBeInTheDocument();
  });
});
