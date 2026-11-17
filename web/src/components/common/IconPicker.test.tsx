import '@douyinfe/semi-ui/react19-adapter';
import '@/i18n/i18n';

import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { IconPicker } from './IconPicker';

describe('IconPicker', () => {
  it('未选时显示占位，点开后列出图标候选', async () => {
    render(<IconPicker />);
    await userEvent.click(screen.getByRole('button', { name: '选择图标' }));
    const listbox = await screen.findByRole('listbox');
    expect(listbox.querySelectorAll('[role="option"]').length).toBeGreaterThan(10);
  });

  it('搜索过滤后点选回调图标名并收起', async () => {
    const onChange = vi.fn();
    render(<IconPicker onChange={onChange} />);
    await userEvent.click(screen.getByRole('button', { name: '选择图标' }));
    await userEvent.type(await screen.findByPlaceholderText('搜索图标'), 'home');
    const option = await screen.findByRole('option', { name: /^home$/i });
    await userEvent.click(option);
    expect(onChange).toHaveBeenCalledWith('home');
    await waitFor(() => {
      expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
    });
  });

  it('无匹配时显示空态', async () => {
    render(<IconPicker />);
    await userEvent.click(screen.getByRole('button', { name: '选择图标' }));
    await userEvent.type(await screen.findByPlaceholderText('搜索图标'), 'zzz-none');
    expect(await screen.findByText('没有匹配的图标')).toBeInTheDocument();
  });

  it('已选可清除为 null', async () => {
    const onChange = vi.fn();
    render(<IconPicker value="home" onChange={onChange} />);
    await userEvent.click(screen.getByRole('button', { name: '清除' }));
    expect(onChange).toHaveBeenCalledWith(null);
  });
});
