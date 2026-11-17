import '@douyinfe/semi-ui/react19-adapter';
import '@/i18n/i18n';

import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it } from 'vitest';

import { AuthTopBar } from './AuthTopBar';

describe('AuthTopBar', () => {
  afterEach(() => {
    document.body.removeAttribute('theme-mode');
    localStorage.clear();
  });

  it('渲染产品名与主题切换按钮', () => {
    render(<AuthTopBar />);
    expect(screen.getByText('SandAdmin')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '切换深色/浅色' })).toBeInTheDocument();
  });

  it('点主题键切到深色并持久化', async () => {
    render(<AuthTopBar />);
    await userEvent.click(screen.getByRole('button', { name: '切换深色/浅色' }));
    expect(document.body.getAttribute('theme-mode')).toBe('dark');
    expect(localStorage.getItem('sandadmin.theme')).toBe('dark');
  });
});
