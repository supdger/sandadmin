import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { clearToken, setToken } from '@/lib/authToken';
import { resetV1Mock } from '@/mocks/handlers/_v1data';
import { SettingsPage } from './SettingsPage';

function renderPage() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        <SettingsPage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('SettingsPage', () => {
  beforeEach(() => {
        resetV1Mock();
    setToken('mock-admin-token');
  });
  afterEach(() => clearToken());

  it('左侧分组 + 右侧表单项；密钥类不显示输入框', async () => {
    renderPage();
    expect(await screen.findByText('基本信息')).toBeInTheDocument();
    expect(screen.getByText('登录与安全')).toBeInTheDocument();
    expect(screen.getByText('一般不用改')).toBeInTheDocument();
    expect(await screen.findByText('系统名字')).toBeInTheDocument();
    // 配置 key / 技术名不出现。
    expect(screen.queryByText('site_name')).not.toBeInTheDocument();
  });

  it('文本改动后出现保存按钮，保存成功提示分组名', async () => {
    renderPage();
    const input = await screen.findByDisplayValue('SandAdmin');
    await userEvent.clear(input);
    await userEvent.type(input, '新后台');
    const save = await screen.findByRole('button', { name: '保存' });
    // 实心主按钮（solid primary，不是 light/borderless）。
    expect(save.className).toContain('semi-button-primary');
    expect(save.className).not.toContain('semi-button-light');
    expect(save.className).not.toContain('semi-button-borderless');
    await userEvent.click(save);
    expect(await screen.findByText('已保存「基本信息」')).toBeInTheDocument();
  });

  it('登录与安全分组默认不展开提示「一般不用改」，密钥项只读', async () => {
    renderPage();
    await userEvent.click(await screen.findByText('登录与安全'));
    expect(await screen.findByText('登录后保持多久')).toBeInTheDocument();
    expect(screen.getByText('已设置，需要改请联系负责部署的人')).toBeInTheDocument();
  });
});
