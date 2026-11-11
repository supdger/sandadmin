import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router';
import { describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { ResultPage } from './ResultPage';

describe('ResultPage', () => {
  it('renders success status with title, description and default home button', () => {
    render(
      <MemoryRouter>
        <ResultPage
          status="success"
          title="安装完成"
          description="现在可以登录开始使用了"
        />
      </MemoryRouter>,
    );

    expect(screen.getByText('安装完成')).toBeInTheDocument();
    expect(screen.getByText('现在可以登录开始使用了')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '返回首页' })).toBeInTheDocument();
  });

  it('renders fail status and uses caller-provided extra instead of the default button', () => {
    render(
      <MemoryRouter>
        <ResultPage
          status="fail"
          title="安装失败"
          description="环境初始化没有成功"
          extra={<button type="button">重新开始</button>}
        />
      </MemoryRouter>,
    );

    expect(screen.getByText('安装失败')).toBeInTheDocument();
    expect(screen.getByText('重新开始')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: '返回首页' })).not.toBeInTheDocument();
  });
});
