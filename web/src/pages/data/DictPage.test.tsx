import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { clearToken, setToken } from '@/lib/authToken';
import { restorePhoneConsultItem } from '@/mocks/handlers/data';
import { DictPage } from './DictPage';

function renderPage() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        <DictPage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

/** 找到包含指定文本的表格行。 */
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

describe('DictPage', () => {
  beforeEach(() => {
    restorePhoneConsultItem();
  });

  afterEach(() => {
    clearToken();
    restorePhoneConsultItem();
  });

  it('loads option groups and shows items after selecting a group', async () => {
    setToken('mock-admin-token');
    renderPage();

    expect(await screen.findByText('选项管理')).toBeInTheDocument();
    const group = await screen.findByText('客户来源');
    await userEvent.click(group);

    expect(await screen.findByText('电话咨询')).toBeInTheDocument();
    expect(screen.getByText('朋友介绍')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /添加选项组/ })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '添加选项' })).toBeInTheDocument();
    // 系统组带「系统自带」标记。
    expect(screen.getAllByText('系统自带').length).toBeGreaterThan(0);
  });

  it('creates a new option group', async () => {
    setToken('mock-admin-token');
    renderPage();

    await screen.findByText('客户来源');
    await userEvent.click(screen.getByRole('button', { name: /添加选项组/ }));

    // 弹窗打开后出现组名输入框。
    await screen.findByPlaceholderText('例如：客户来源');
    await userEvent.type(
      screen.getByPlaceholderText('例如：客户来源'),
      '回访渠道',
    );
    await clickDialogButton('保存');

    expect(await screen.findByText('已添加「回访渠道」')).toBeInTheDocument();
    // 新建后自动选中该组，右侧先出现空态（说明详情已加载、右侧标题已渲染）。
    expect(await screen.findByText('这个组还没有选项')).toBeInTheDocument();
    // 左栏与右侧标题都会出现组名。
    expect((await screen.findAllByText('回访渠道')).length).toBeGreaterThanOrEqual(2);
  });

  it('warns before deleting an option that may be in use', async () => {
    setToken('mock-admin-token');
    renderPage();

    await userEvent.click(await screen.findByText('客户来源'));
    await screen.findByText('电话咨询');

    // 「电话咨询」used=true：删除入口走「可能已经有数据在用」自定义弹窗。
    await userEvent.click(within(rowOf('电话咨询')).getByLabelText('更多'));
    await userEvent.click(await screen.findByText('删除'));

    expect(await screen.findByText('“电话咨询”可能已经有数据在用')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '改为停用' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '仍然删除' })).toBeInTheDocument();

    // 主按钮「改为停用」直接把选项停用。
    await userEvent.click(screen.getByRole('button', { name: '改为停用' }));
    await waitFor(() =>
      expect(screen.queryByText('“电话咨询”可能已经有数据在用')).not.toBeInTheDocument(),
    );
    // 停用后状态列变为「已停用」。
    await waitFor(() =>
      expect(within(rowOf('电话咨询')).getByText('已停用')).toBeInTheDocument(),
    );
  });

  it('keeps a disabled option disabled when only the name changes', async () => {
    setToken('mock-admin-token');
    renderPage();

    await userEvent.click(await screen.findByText('客户来源'));
    await screen.findByText('电话咨询');
    await userEvent.click(within(rowOf('电话咨询')).getByLabelText('更多'));
    await userEvent.click(await screen.findByText('删除'));
    await userEvent.click(await screen.findByRole('button', { name: '改为停用' }));
    await waitFor(() =>
      expect(within(rowOf('电话咨询')).getByText('已停用')).toBeInTheDocument(),
    );

    await userEvent.click(within(rowOf('电话咨询')).getByRole('button', { name: '编辑' }));
    const name = await screen.findByPlaceholderText('例如：展会');
    expect(screen.getByRole('switch', { name: '启用' })).not.toBeChecked();
    await userEvent.clear(name);
    await userEvent.type(name, '来电咨询');
    await clickDialogButton('保存');

    expect(await screen.findByText('已保存「来电咨询」')).toBeInTheDocument();
    await waitFor(() =>
      expect(within(rowOf('来电咨询')).getByText('已停用')).toBeInTheDocument(),
    );
  });

  it('deletes an unused option after plain confirmation', async () => {
    setToken('mock-admin-token');
    renderPage();

    await userEvent.click(await screen.findByText('客户来源'));
    await screen.findByText('网上搜索');

    // 「网上搜索」used=false：普通确认弹窗。
    await userEvent.click(within(rowOf('网上搜索')).getByLabelText('更多'));
    await userEvent.click(await screen.findByText('删除'));

    expect(await screen.findByText('删除「网上搜索」？')).toBeInTheDocument();
    await clickDialogButton('删除');

    await waitFor(() =>
      expect(screen.queryByText('网上搜索')).not.toBeInTheDocument(),
    );
  });

  it('hides write controls and drag handles for read-only accounts', async () => {
    setToken('mock-viewer-token');
    renderPage();

    await userEvent.click(await screen.findByText('客户来源'));
    await screen.findByText('电话咨询');

    expect(screen.queryByRole('button', { name: /添加选项组/ })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: '添加选项' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: '编辑选项组' })).not.toBeInTheDocument();
    expect(screen.queryAllByLabelText('拖动调整顺序')).toHaveLength(0);
    // 行内不出现「编辑」按钮与「更多」下拉。
    expect(within(rowOf('电话咨询')).queryByText('编辑')).not.toBeInTheDocument();
    expect(within(rowOf('电话咨询')).queryByLabelText('更多')).not.toBeInTheDocument();
  });
});
