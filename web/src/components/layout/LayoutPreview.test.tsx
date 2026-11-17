import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import '@/i18n/i18n';
import { LayoutPreview } from './LayoutPreview';

describe('LayoutPreview', () => {
  it('按 mode 渲染 SVG 预览并回传选择', async () => {
    const onSelect = vi.fn();
    render(<LayoutPreview mode="side" label="侧边菜单" onSelect={onSelect} />);

    const button = screen.getByRole('button', { name: '侧边菜单' });
    expect(button.querySelector('svg')).toBeTruthy();
    await userEvent.click(button);
    expect(onSelect).toHaveBeenCalledTimes(1);
  });

  it('active 时 aria-pressed 为 true', () => {
    render(<LayoutPreview mode="mix" active label="混合菜单" />);
    expect(screen.getByRole('button', { name: '混合菜单' })).toHaveAttribute(
      'aria-pressed',
      'true',
    );
  });
});
