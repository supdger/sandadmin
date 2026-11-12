import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { MemoryRouter } from 'react-router';
import { afterEach, describe, expect, it, vi } from 'vitest';

import '@/i18n/i18n';
import { clearToken, setToken } from '@/lib/authToken';
import { server } from '@/mocks/server';
import { AnnouncementsPage } from './AnnouncementsPage';

// jsdom 跑不动 WangEditor 的 DOM 测量，用等价的受控输入替身：
// 每敲一个字就回传 <p>内容</p>，与真实编辑器的 HTML 输出形态一致。
vi.mock('./RichTextEditor', () => ({
  RichTextEditor: ({
    onChange,
    placeholder,
  }: {
    onChange: (html: string) => void;
    placeholder?: string;
  }) => (
    <textarea
      data-testid="rich-text-editor"
      placeholder={placeholder}
      onChange={(e) => onChange(`<p>${e.target.value}</p>`)}
    />
  ),
}));

function renderPage() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        <AnnouncementsPage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

function rowOf(text: string): HTMLElement {
  const cell = screen.getByText(text);
  const row = cell.closest('tr');
  expect(row).not.toBeNull();
  return row as HTMLElement;
}

/**
 * Semi 弹窗内置确定/取消按钮的可访问名是 confirm/cancel，
 * 按按钮内的可见文字点击。
 */
async function clickDialogButton(text: string) {
  // 页面可能同时开着抽屉（也是 role=dialog），只认 Modal 的内容节点。
  const dialogs = await screen.findAllByRole('dialog');
  // Modal.confirm 的弹层挂在 React 根之外，上一条用例可能留下未卸载的旧弹层；
  // 最新打开的弹层在 DOM 里最后，取最后一个。
  const modals = dialogs.filter((el) => el.className.includes('semi-modal-content'));
  const modal = modals[modals.length - 1];
  expect(modal, 'modal dialog').toBeTruthy();
  const button = within(modal as HTMLElement)
    .getAllByRole('button')
    .find((el) => el.textContent?.includes(text));
  expect(button, `dialog button "${text}"`).toBeTruthy();
  await userEvent.click(button as HTMLElement);
}

describe('AnnouncementsPage', () => {
  afterEach(() => {
    clearToken();
  });

  it('lists announcements with state, scope and pinned markers', async () => {
    setToken('mock-admin-token');
    renderPage();

    expect(await screen.findByText('公告管理')).toBeInTheDocument();
    expect(await screen.findByText('系统维护通知')).toBeInTheDocument();
    expect(screen.getByText('新版选项管理上线')).toBeInTheDocument();
    expect(screen.getByText('置顶')).toBeInTheDocument();
    expect(screen.getAllByText('已发布').length).toBeGreaterThan(0);
    expect(screen.getAllByText('草稿').length).toBeGreaterThan(0);
    expect(screen.getByText('已收回')).toBeInTheDocument();
    // 「写公告」入口对有创建权限的账号可见。
    expect(screen.getByRole('button', { name: /写公告/ })).toBeInTheDocument();
  });

  it('creates and publishes a new announcement', async () => {
    setToken('mock-admin-token');
    renderPage();

    await screen.findByText('系统维护通知');
    await userEvent.click(screen.getByRole('button', { name: /写公告/ }));

    // 编辑抽屉打开：标题与正文必填。
    await screen.findByPlaceholderText('一句话说清楚要通知大家什么');
    await userEvent.type(
      screen.getByPlaceholderText('一句话说清楚要通知大家什么'),
      '关于年会的通知',
    );
    await userEvent.type(
      screen.getByTestId('rich-text-editor'),
      '年会定于 12 月 28 日晚上七点，请各位同事准时参加。',
    );

    await userEvent.click(screen.getByRole('button', { name: '发布' }));
    // 发布前先保存，确认框带出接口算的应收人数。
    expect(await screen.findByText('发布「关于年会的通知」？')).toBeInTheDocument();
    expect(screen.getByText(/发布后，\d+ 个人马上就能看到/)).toBeInTheDocument();
    await clickDialogButton('发布');

    // 成功提示带后端返回的名字；新公告出现在列表里（标题唯一，可精确断言）。
    expect(await screen.findByText('已发布「关于年会的通知」')).toBeInTheDocument();
    expect(await screen.findByText('关于年会的通知')).toBeInTheDocument();
  });

  it('保存撞上 STALE_VERSION：固定口径提示且表单内容保留', async () => {
    // 后端拒收这次的 PUT：另一个管理员先改了这条。
    server.use(
      http.put('/api/admin/v1/announcements/:announcementId', () =>
        HttpResponse.json(
          { code: 'STALE_VERSION', message: '这条刚被别人改过', request_id: 'x' },
          { status: 409 },
        ),
      ),
    );
    setToken('mock-admin-token');
    renderPage();

    // 草稿行「国庆值班安排」→ 编辑 → 改标题 → 存为草稿。
    await screen.findByText('国庆值班安排');
    await userEvent.click(within(rowOf('国庆值班安排')).getByText('编辑'));
    const titleInput = await screen.findByPlaceholderText('一句话说清楚要通知大家什么');
    await userEvent.clear(titleInput);
    await userEvent.type(titleInput, '国庆值班安排（改）');
    await userEvent.click(screen.getByRole('button', { name: '存为草稿' }));

    // 冲突口径定死；抽屉不关、刚写的内容还在。
    expect(await screen.findByText('这条刚被别人改过，刷新后再改')).toBeInTheDocument();
    expect(
      (screen.getByPlaceholderText('一句话说清楚要通知大家什么') as HTMLInputElement).value,
    ).toBe('国庆值班安排（改）');
  });

  it('revokes a published announcement after confirmation', async () => {
    setToken('mock-admin-token');
    renderPage();

    await screen.findByText('系统维护通知');
    await userEvent.click(
      within(rowOf('系统维护通知')).getByRole('button', { name: '更多' }),
    );
    await userEvent.click(await screen.findByText('收回'));

    expect(await screen.findByText('收回「系统维护通知」？')).toBeInTheDocument();
    await clickDialogButton('收回');

    // 收回后该行状态变为「已收回」。
    await waitFor(() =>
      expect(
        within(rowOf('系统维护通知')).getByText('已收回'),
      ).toBeInTheDocument(),
    );
  });

  it('opens read stats drawer from the read-count cell', async () => {
    setToken('mock-admin-token');
    renderPage();

    await screen.findByText('系统维护通知');
    // 已发布公告的「已读情况」列是「已读 / 应读」按钮；草稿行为「—」。
    const statsButton = await screen.findAllByRole('button', { name: /\/\s*\d+/ });
    await userEvent.click(statsButton[0]);

    // 抽屉里的已读/未读分页签（表头也叫「已读情况」，不拿它做断言）。
    expect(await screen.findByText(/已读（\d+）/)).toBeInTheDocument();
    expect(screen.getByText(/未读（\d+）/)).toBeInTheDocument();
  });

  it('hides write controls for read-only accounts', async () => {
    setToken('mock-viewer-token');
    renderPage();

    await screen.findByText('系统维护通知');

    expect(screen.queryByRole('button', { name: /写公告/ })).not.toBeInTheDocument();
    // 行内「编辑」按钮与「更多」下拉都不可见。
    expect(within(rowOf('系统维护通知')).queryByText('编辑')).not.toBeInTheDocument();
    expect(
      within(rowOf('系统维护通知')).queryByRole('button', { name: '更多' }),
    ).not.toBeInTheDocument();
  });
});
