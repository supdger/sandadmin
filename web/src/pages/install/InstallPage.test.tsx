import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createMemoryRouter, RouterProvider, useLocation } from 'react-router';
import { afterEach, describe, expect, it } from 'vitest';

import { startInstall } from '@/api/install';
import { getJob } from '@/api/jobs';
import InstallPage from './InstallPage';

function ResultProbe() {
  const location = useLocation();
  const state = (location.state ?? {}) as { title?: string; next?: string };
  return (
    <div>
      结果页占位:{state.title}:{state.next}
    </div>
  );
}

function renderInstall() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  const router = createMemoryRouter(
    [
      { path: '/install', element: <InstallPage /> },
      { path: '/login', element: <div>登录页占位</div> },
      { path: '/result/success', element: <ResultProbe /> },
    ],
    { initialEntries: ['/install'] },
  );
  render(
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={router} />
    </QueryClientProvider>,
  );
  return router;
}

async function walkToConfirm(adminUsername = 'boss') {
  const user = userEvent.setup();
  // Step 0 — environment check list renders, then go next.
  await screen.findByText('运行环境');
  await user.click(screen.getByRole('button', { name: '下一步' }));
  // Step 1 — admin account.
  await user.type(screen.getByPlaceholderText('比如 admin'), adminUsername);
  await user.type(screen.getByPlaceholderText('比如 管理员'), '站长');
  await user.type(screen.getByPlaceholderText('请设置登录密码'), 'Admin@123456');
  await user.type(screen.getByPlaceholderText('请再输入一次密码'), 'Admin@123456');
  await user.click(screen.getByRole('button', { name: '下一步' }));
  // Step 2 — site info (siteName prefilled).
  await screen.findByPlaceholderText('不填就用默认名称');
  await user.click(screen.getByRole('button', { name: '下一步' }));
  // Step 3 — confirm.
  await screen.findByText('管理员用户名');
}

describe('InstallPage', () => {
  afterEach(() => {
    localStorage.removeItem('mock.installed');
    localStorage.removeItem('mock.installJobId');
  });

  it('shows the already-installed result when status reports installed', async () => {
    localStorage.setItem('mock.installed', '1');
    const router = renderInstall();

    expect(await screen.findByText('系统已安装')).toBeInTheDocument();
    const user = userEvent.setup();
    await user.click(screen.getByRole('button', { name: '去登录' }));
    expect(router.state.location.pathname).toBe('/login');
  });

  it('walks the wizard to a finished install and lands on the result page', async () => {
    const router = renderInstall();
    await walkToConfirm();

    const user = userEvent.setup();
    await user.click(screen.getByRole('button', { name: '开始安装' }));

    await waitFor(
      () => {
        expect(router.state.location.pathname).toBe('/result/success');
      },
      { timeout: 15000 },
    );
    const state = router.state.location.state as { title: string; next: string };
    expect(state.title).toBe('安装完成');
    expect(state.next).toBe('/login');
  }, 20000);

  it('shows the failure reason and a retry button for a failing job', async () => {
    renderInstall();
    await walkToConfirm('failbot');

    const user = userEvent.setup();
    await user.click(screen.getByRole('button', { name: '开始安装' }));

    expect(await screen.findByRole('button', { name: '重新安装' }, { timeout: 10000 }))
      .toBeInTheDocument();
    expect(screen.getByText(/写入数据库时中断/)).toBeInTheDocument();
  }, 15000);

  it('resumes the progress view when a job id is stored', async () => {
    localStorage.setItem('mock.installJobId', 'install-42');
    renderInstall();

    expect(await screen.findByText('正在安装，请稍等…')).toBeInTheDocument();
    expect(await screen.findByText(/当前步骤/)).toBeInTheDocument();
  });
});

describe('jobs mock progression', () => {
  afterEach(() => {
    localStorage.removeItem('mock.installed');
  });

  it('advances a job to success and flags the system as installed', async () => {
    const { jobId } = await startInstall({
      adminUsername: 'boss',
      adminPassword: 'Admin@123456',
      adminNickname: '站长',
      siteName: 'SandAdmin',
    });
    expect(jobId).toMatch(/^install-/);

    let job = await getJob(jobId);
    expect(job.status).toBe('running');
    expect(job.stage).toBe('准备环境');
    expect(job.progress).toBe(20);

    for (let i = 0; i < 4; i += 1) {
      // sequential on purpose — each poll advances the mock job one step
      job = await getJob(jobId);
    }
    expect(job.status).toBe('success');
    expect(job.progress).toBe(100);
    expect(localStorage.getItem('mock.installed')).toBe('1');
  });
});
