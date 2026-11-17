import '@douyinfe/semi-ui/react19-adapter';
import '@/i18n/i18n';

import type { JSX } from 'react';

import { render, screen } from '@testing-library/react';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { describe, expect, it } from 'vitest';

import { ErrorPage } from './ErrorPages';

function renderAt(path: string, element: JSX.Element) {
  const router = createMemoryRouter([{ path, element }], { initialEntries: [path] });
  render(<RouterProvider router={router} />);
}

describe('ErrorPage', () => {
  it('403 页渲染无权限文案与返回首页', async () => {
    renderAt('/403', <ErrorPage kind="403" />);
    expect(await screen.findByText('没有权限')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '返回首页' })).toBeInTheDocument();
  });

  it('404 页渲染页面不存在文案', async () => {
    renderAt('/404', <ErrorPage kind="404" />);
    expect(await screen.findByText('页面不存在')).toBeInTheDocument();
  });

  it('500 页渲染服务异常文案', async () => {
    renderAt('/500', <ErrorPage kind="500" />);
    expect(await screen.findByText('出了点问题')).toBeInTheDocument();
  });
});
