import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router';
import { afterEach, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { clearToken, setToken } from '@/lib/authToken';
import { server } from '@/mocks/server';
import { TasksPage } from './TasksPage';

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
// jsdom 的 URL.createObjectURL 对 Blob 抛错；补上桩让下载路径走完成功分支。
if (typeof URL !== 'undefined') {
  URL.createObjectURL = () => 'blob:mock';
  URL.revokeObjectURL = () => {};
}

function renderPage(entry = '/system/tasks') {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[entry]}>
        <TasksPage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('TasksPage', () => {
  afterEach(() => {
    clearToken();
    server.resetHandlers();
  });

  it('按列表展示任务类型、状态与进度', async () => {
    setToken('mock-admin-token');
    renderPage();

    expect(await screen.findByText('任务中心')).toBeInTheDocument();
    expect(await screen.findAllByText('user.export')).not.toHaveLength(0);
    expect(screen.getByText('已完成')).toBeInTheDocument();
    expect(screen.getByText('失败')).toBeInTheDocument();
  });

  it('按状态筛选后只剩对应任务', async () => {
    setToken('mock-admin-token');
    renderPage('/system/tasks?state=failed');

    await waitFor(() => expect(screen.getAllByText('失败')).not.toHaveLength(0));
    expect(screen.queryByText('已完成')).not.toBeInTheDocument();
  });

  it('打开详情抽屉展示任务字段', async () => {
    setToken('mock-admin-token');
    const user = userEvent.setup();
    renderPage();

    const detailButtons = await screen.findAllByRole('button', { name: /详情/ });
    await user.click(detailButtons[0]);

    expect(await screen.findByText('任务详情')).toBeInTheDocument();
    expect(await screen.findByText('task-001')).toBeInTheDocument();
    expect(screen.getByText('file-users-001')).toBeInTheDocument();
  });

  it('成功任务点下载结果触发文件下载', async () => {
    setToken('mock-admin-token');
    const user = userEvent.setup();
    renderPage('/system/tasks?state=succeeded');

    const download = await screen.findByRole('button', { name: /下载结果/ });
    await user.click(download);

    expect(await screen.findByText('已开始下载')).toBeInTheDocument();
  });
});
