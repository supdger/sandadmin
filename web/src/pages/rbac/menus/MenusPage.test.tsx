import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { MemoryRouter } from 'react-router';
import { afterEach, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { env } from '@/config/env';
import { clearToken, setToken } from '@/lib/authToken';
import { server } from '@/mocks/server';
import { MenusPage } from './MenusPage';

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

function renderPage(initialEntry = '/system/menu') {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[initialEntry]}>
        <MenusPage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

/** 树表格——侧边预览会重复每个标题，查询要限定范围。 */
async function tableRegion(): Promise<HTMLElement> {
  let table: HTMLElement | null = null;
  await waitFor(() => {
    table = document.querySelector('.semi-table');
    expect(table).not.toBeNull();
    // 行在树查询返回后异步出现。
    expect(
      table!.querySelectorAll('.semi-table-tbody tr').length,
    ).toBeGreaterThan(0);
  });
  return table!;
}

describe('MenusPage', () => {
  afterEach(() => {
    clearToken();
  });

  it('renders the two-level tree with type, source and visibility', async () => {
    setToken('mock-admin-token');
    renderPage();

    const table = await tableRegion();
    expect(within(table).getByText('组织与用户')).toBeInTheDocument();
    expect(within(table).getByText('工作台')).toBeInTheDocument();
    expect(within(table).getByText('菜单管理')).toBeInTheDocument();
    expect(within(table).getByText('帮助文档')).toBeInTheDocument();
    expect(within(table).getAllByText('系统自带').length).toBeGreaterThan(0);
    expect(within(table).getByText('自建')).toBeInTheDocument();
    expect(within(table).getByText('统计插件')).toBeInTheDocument();
    // 插件页默认隐藏——行仍在列表里，只是开关是关的。
    expect(within(table).getByText('数据看板')).toBeInTheDocument();
    expect(screen.getByText('菜单预览')).toBeInTheDocument();
  });

  it('writes keyword and source filters through the URL query', async () => {
    setToken('mock-admin-token');
    let seenKeyword: string | null = null;
    server.use(
      http.get(`${v1}/menus/tree`, ({ request }) => {
        seenKeyword = new URL(request.url).searchParams.get('keyword');
        return HttpResponse.json({
          data: { menu_version: 7, items: [] },
          request_id: 'test',
        });
      }),
    );
    renderPage();

    // 搜索框立即渲染；本用例不需要等行数据。
    await screen.findByPlaceholderText('搜菜单名称');
    await userEvent.type(
      screen.getByPlaceholderText('搜菜单名称'),
      '角色',
    );

    await waitFor(() => {
      expect(seenKeyword).toBe('角色');
    });
  });

  it('creates a group through the side sheet', async () => {
    setToken('mock-admin-token');
    renderPage();
    await tableRegion();

    await userEvent.click(
      await screen.findByRole('button', { name: /添加分组/ }),
    );
    const nameInput = await screen.findByPlaceholderText('用同事一看就懂的说法');
    await userEvent.type(nameInput, '新分组');
    await userEvent.click(screen.getByRole('button', { name: '保存' }));

    // 刷新后的树里能看到新分组（抽屉退出动画在 jsdom 下走不完，
    // 所以断言行而不是断言抽屉消失）。
    const table = await tableRegion();
    await waitFor(() => {
      expect(within(table).getByText('新分组')).toBeInTheDocument();
    });
  });

  it('rejects a duplicate group name before calling the API', async () => {
    setToken('mock-admin-token');
    let postCalls = 0;
    server.use(
      http.post(`${v1}/menus/groups`, () => {
        postCalls += 1;
        return HttpResponse.json({ data: { key: 'x' }, request_id: 't' });
      }),
    );
    renderPage();
    const table = await tableRegion();
    expect(within(table).getByText('组织与用户')).toBeInTheDocument();

    await userEvent.click(
      await screen.findByRole('button', { name: /添加分组/ }),
    );
    const nameInput = await screen.findByPlaceholderText('用同事一看就懂的说法');
    await userEvent.type(nameInput, '组织与用户');
    await userEvent.click(screen.getByRole('button', { name: '保存' }));

    expect(
      await screen.findByText(/已经有叫“组织与用户”的菜单了/),
    ).toBeInTheDocument();
    expect(postCalls).toBe(0);
  });

  it('hides write controls and disables switches for read-only users', async () => {
    setToken('mock-viewer-token');
    renderPage();
    const table = await tableRegion();
    expect(within(table).getByText('组织与用户')).toBeInTheDocument();

    expect(screen.queryByRole('button', { name: /添加分组/ })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /添加外部链接/ })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /恢复默认/ })).not.toBeInTheDocument();
    // 没有 menu:update 的用户，所有可见性开关都禁用。
    const switches = document.querySelectorAll('.semi-switch input[type="checkbox"]');
    expect(switches.length).toBeGreaterThan(0);
    switches.forEach((sw) => expect(sw).toBeDisabled());
  });

  it('moves a menu up through the row actions and offers undo', async () => {
    setToken('mock-admin-token');
    let moveBody: Record<string, unknown> | null = null;
    server.use(
      http.post(`${v1}/menus/:menuKey/move`, async ({ request }) => {
        moveBody = (await request.json()) as Record<string, unknown>;
        return HttpResponse.json({ data: {}, request_id: 'test' });
      }),
    );
    renderPage();
    const table = await tableRegion();
    const linkRow = within(table).getByText('帮助文档').closest('tr')!;

    await userEvent.click(
      within(linkRow).getByRole('button', { name: '更多' }),
    );
    await userEvent.click(await screen.findByText('上移'));

    await waitFor(() => {
      expect(moveBody).not.toBeNull();
    });
    // 「帮助文档」在「系统设置」下 index 1；上移目标是 index 0。
    expect(moveBody!.parent_key).toBe('system-settings');
    expect(moveBody!.index).toBe(0);
    expect(typeof moveBody!.base_version).toBe('number');

    // 移动后出现五秒撤销入口。
    expect(await screen.findByText('撤销')).toBeInTheDocument();
  });

  it('surfaces the backend message when a write is rejected', async () => {
    setToken('mock-admin-token');
    server.use(
      http.put(`${v1}/menus/:menuKey/visible`, () =>
        HttpResponse.json(
          {
            code: 'MENU_LOCKED',
            message: '该菜单不允许隐藏',
            request_id: 'test',
          },
          { status: 409 },
        ),
      ),
    );
    renderPage();
    const table = await tableRegion();
    const linkRow = within(table).getByText('帮助文档').closest('tr')!;
    const sw = linkRow.querySelector('.semi-switch input[type="checkbox"]')!;
    await userEvent.click(sw);

    expect(await screen.findByText('该菜单不允许隐藏')).toBeInTheDocument();
  });
});
