import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router';
import { afterEach, beforeAll, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { clearToken, setToken } from '@/lib/authToken';
import { FilesPage } from './FilesPage';

// jsdom/vitest 的 URL.createObjectURL 与真实 Blob 不兼容；图片缩略图只需要一个非空地址。
// jsdom 也没有 matchMedia；「用在哪里/上传人」列只在宽屏（≥1280px）渲染，这里按宽屏处理。
beforeAll(() => {
  URL.createObjectURL = () => 'blob:mock-object-url';
  URL.revokeObjectURL = () => undefined;
  window.matchMedia = ((query: string) => ({
    matches: true,
    media: query,
    onchange: null,
    addEventListener: () => undefined,
    removeEventListener: () => undefined,
    addListener: () => undefined,
    removeListener: () => undefined,
    dispatchEvent: () => false,
  })) as typeof window.matchMedia;
});

function renderPage() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        <FilesPage />
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

describe('FilesPage', () => {
  afterEach(() => {
    clearToken();
    localStorage.clear();
  });

  it('lists files with usage labels and used-space summary', async () => {
    setToken('mock-admin-token');
    renderPage();

    expect(await screen.findByText('文件管理')).toBeInTheDocument();
    // 列表按上传时间倒序，第一页能看到近期文件。
    expect(await screen.findByText('合同扫描件.pdf')).toBeInTheDocument();
    expect(screen.getAllByText('暂时看不出用在哪里').length).toBeGreaterThan(0);
    expect(screen.getByText('没有被使用')).toBeInTheDocument();
    expect(screen.getByText(/已用 /)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /上传/ })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '清理没用的旧文件' })).toBeInTheDocument();
  });

  it('filters files by keyword', async () => {
    setToken('mock-admin-token');
    renderPage();

    await screen.findByText('合同扫描件.pdf');
    await userEvent.type(screen.getByPlaceholderText('搜索文件名'), '发票');

    expect(await screen.findByText('发票_2026-10.pdf')).toBeInTheDocument();
    await waitFor(() =>
      expect(screen.queryByText('合同扫描件.pdf')).not.toBeInTheDocument(),
    );
  });

  it('deletes an unused file after confirmation', async () => {
    setToken('mock-admin-token');
    renderPage();

    await screen.findByText('合同扫描件.pdf');
    await userEvent.type(screen.getByPlaceholderText('搜索文件名'), '发票');
    await screen.findByText('发票_2026-10.pdf');

    // 「发票_2026-10.pdf」usage_state=unused：普通删除确认。
    await userEvent.click(within(rowOf('发票_2026-10.pdf')).getByLabelText('更多'));
    await userEvent.click(await screen.findByText('删除'));

    expect(await screen.findByText('删除这 1 个文件？')).toBeInTheDocument();
    await clickDialogButton('删除');

    await waitFor(() =>
      expect(screen.queryByText('发票_2026-10.pdf')).not.toBeInTheDocument(),
    );
    expect(await screen.findByText('没有找到符合条件的文件')).toBeInTheDocument();
  });

  it('rejects files larger than 100 MB before upload', async () => {
    setToken('mock-admin-token');
    renderPage();

    await screen.findByText('合同扫描件.pdf');
    const input = document.querySelector('input[type="file"]') as HTMLInputElement;
    expect(input).not.toBeNull();

    const big = new File(['x'], '超大压缩包.zip', { type: 'application/zip' });
    Object.defineProperty(big, 'size', { value: 101 * 1024 * 1024 });
    await userEvent.upload(input, big);

    expect(
      await screen.findByText('这个文件超过 100 MB，传不上去'),
    ).toBeInTheDocument();
    // 超限文件不进入上传队列。
    expect(screen.queryByText('超大压缩包.zip')).not.toBeInTheDocument();
  });

  it('hides delete and cleanup controls for read-only accounts', async () => {
    setToken('mock-viewer-token');
    renderPage();

    await screen.findByText('合同扫描件.pdf');

    expect(screen.queryByRole('button', { name: '清理没用的旧文件' })).not.toBeInTheDocument();
    // 行内没有「更多」下拉（删除入口），批量条也不会出现。
    expect(screen.queryAllByLabelText('更多')).toHaveLength(0);
    // 但上传仍对登录用户开放。
    expect(screen.getByRole('button', { name: /上传/ })).toBeInTheDocument();
  });
});
