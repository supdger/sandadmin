import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router';
import { afterEach, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { clearToken, setToken } from '@/lib/authToken';
import { PostsPage } from './PostsPage';

function renderPage(initialEntry = '/system/post') {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[initialEntry]}>
        <PostsPage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('PostsPage', () => {
  afterEach(() => {
    clearToken();
  });

  it('renders posts with remark fallback and status tags', async () => {
    setToken('mock-admin-token');
    renderPage();

    expect(await screen.findByText('高级后端工程师')).toBeInTheDocument();
    expect(screen.getByText('前端工程师')).toBeInTheDocument();
    // remark 为空的岗位显示「未填写」。
    expect(screen.getAllByText('未填写').length).toBeGreaterThan(0);
    expect(screen.getAllByText('已停用').length).toBeGreaterThan(0);
    // 用户数可点击跳用户页。
    expect(screen.getAllByRole('button').length).toBeGreaterThan(0);
  });

  it('keeps the modal open and shows the backend reason on duplicate name', async () => {
    setToken('mock-admin-token');
    renderPage();

    await screen.findByText('高级后端工程师');
    await userEvent.click(screen.getByRole('button', { name: /添加岗位/ }));

    const nameInput = await screen.findByPlaceholderText('岗位名称');
    await userEvent.type(nameInput, '高级后端工程师');
    await userEvent.click(screen.getByRole('button', { name: '创建' }));

    await waitFor(() => {
      expect(screen.getByText('岗位名称已存在，请换一个')).toBeInTheDocument();
    });
    // 弹窗不关闭。
    expect(nameInput).toBeInTheDocument();
  });

  it('honours URL-backed keyword filter', async () => {
    setToken('mock-admin-token');
    renderPage('/system/post?keyword=%E5%AE%9E%E4%B9%A0%E7%94%9F');

    expect(await screen.findByText('实习生')).toBeInTheDocument();
    expect(screen.queryByText('高级后端工程师')).not.toBeInTheDocument();
  });
});
