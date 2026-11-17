import '@douyinfe/semi-ui/react19-adapter';
import '@/i18n/i18n';

import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

const can = vi.hoisted(() => ({ value: true }));
vi.mock('@/hooks/useCan', () => ({ useCan: () => can.value }));

import { IconButton } from './IconButton';

describe('IconButton', () => {
  it('点击触发 onClick', async () => {
    const onClick = vi.fn();
    render(<IconButton icon="setting" label="设置" onClick={onClick} />);
    await userEvent.click(screen.getByRole('button', { name: '设置' }));
    expect(onClick).toHaveBeenCalledTimes(1);
  });

  it('权限码不满足时不渲染', () => {
    can.value = false;
    render(<IconButton icon="setting" label="设置" permission="system:setting:save" />);
    expect(screen.queryByRole('button')).toBeNull();
    can.value = true;
  });

  it('tooltip 字符串同时做可访问名', () => {
    render(<IconButton icon="setting" tooltip="打开设置" />);
    expect(screen.getByRole('button', { name: '打开设置' })).toBeInTheDocument();
  });

  it('disabled 时点击不触发', async () => {
    const onClick = vi.fn();
    render(<IconButton icon="setting" label="设置" disabled onClick={onClick} />);
    await userEvent.click(screen.getByRole('button', { name: '设置' }));
    expect(onClick).not.toHaveBeenCalled();
  });
});
