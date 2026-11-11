import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router';
import { afterEach, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { clearToken, setToken } from '@/lib/authToken';
import { TodosPage } from './TodosPage';

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

function renderPage() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        <TodosPage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('TodosPage', () => {
  afterEach(() => {
    clearToken();
  });

  it('lists todos with source, relative time and overdue markers', async () => {
    setToken('mock-admin-token');
    renderPage();

    expect(await screen.findByText('我的待办')).toBeInTheDocument();
    expect(await screen.findByText('「请假申请」等待你审批')).toBeInTheDocument();
    expect(screen.getByText('有 2 个账号待激活')).toBeInTheDocument();
    expect(screen.getByText('已超时')).toBeInTheDocument();
    expect(screen.getAllByRole('button', { name: '去处理' }).length).toBeGreaterThan(0);
  });
});
