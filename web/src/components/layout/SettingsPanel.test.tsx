import '@douyinfe/semi-ui/react19-adapter';

import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { afterEach, describe, expect, it, vi } from 'vitest';

import '@/i18n/i18n';
import { getLayoutSettings, resetLayoutSettings } from '@/stores/layout';
import { getGlobalUi, resetGlobalUi } from './globalUi';
import { SettingsPanel } from './SettingsPanel';

function renderPanel(onClose = () => {}) {
  const router = createMemoryRouter(
    [{ path: '/', element: <SettingsPanel open onClose={onClose} /> }],
    { initialEntries: ['/'] },
  );
  render(<RouterProvider router={router} />);
  return router;
}

describe('SettingsPanel', () => {
  afterEach(() => {
    resetGlobalUi();
    resetLayoutSettings();
    document.body.removeAttribute('theme-mode');
    localStorage.removeItem('sandadmin.theme');
    localStorage.removeItem('sandadmin.themeColor');
  });

  it('切换主题模式写入本机偏好', async () => {
    renderPanel();
    await userEvent.click(await screen.findByText('深色'));
    expect(document.body.getAttribute('theme-mode')).toBe('dark');
    expect(localStorage.getItem('sandadmin.theme')).toBe('dark');
  });

  it('切换主题色写到 body 变量', async () => {
    renderPanel();
    await userEvent.click(await screen.findByRole('button', { name: '紫色' }));
    expect(
      document.body.style.getPropertyValue('--semi-color-primary'),
    ).toBe('#6941c6');
    expect(localStorage.getItem('sandadmin.themeColor')).toBe('violet');
  });

  it('切换布局模式更新布局设置', async () => {
    renderPanel();
    await userEvent.click(await screen.findByRole('button', { name: '顶部菜单' }));
    expect(getLayoutSettings().layoutMode).toBe('top');
  });

  it('多标签开关更新全局 UI 态', async () => {
    renderPanel();
    await userEvent.click(await screen.findByRole('switch', { name: '多标签页' }));
    expect(getGlobalUi().tabsEnabled).toBe(true);
  });

  it('立即锁屏置位锁屏态并关闭面板', async () => {
    const onClose = vi.fn();
    renderPanel(onClose);
    // 按钮内 IconLock 的 aria-label 参与可访问名，不能整串精确匹配。
    await userEvent.click(await screen.findByRole('button', { name: /立即锁屏/ }));
    expect(getGlobalUi().screenLocked).toBe(true);
    expect(onClose).toHaveBeenCalled();
    await waitFor(() => expect(document.body).toBeInTheDocument());
  });
});
