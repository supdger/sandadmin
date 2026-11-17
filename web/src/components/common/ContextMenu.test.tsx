import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { ContextMenu, type ContextMenuEntry } from './ContextMenu';

const ENTRIES: ContextMenuEntry[] = [
  { key: 'refresh', label: '刷新' },
  { type: 'divider' },
  { key: 'rename', label: '重命名', disabled: true },
  {
    key: 'more',
    label: '更多',
    children: [
      { key: 'copy', label: '复制路径' },
      { key: 'delete', label: '删除', danger: true },
    ],
  },
];

function renderMenu(overrides: Partial<Parameters<typeof ContextMenu>[0]> = {}) {
  const props = {
    x: 100,
    y: 100,
    entries: ENTRIES,
    onClose: vi.fn(),
    onSelect: vi.fn(),
    ...overrides,
  };
  render(<ContextMenu {...props} />);
  return props;
}

describe('ContextMenu', () => {
  it('按坐标渲染菜单项与分隔线', () => {
    renderMenu();
    const menu = screen.getByRole('menu');
    expect(menu).toHaveStyle({ left: '100px', top: '100px' });
    expect(screen.getByText('刷新')).toBeInTheDocument();
    expect(screen.getAllByRole('separator')).not.toHaveLength(0);
  });

  it('点普通项回调 onSelect 并关闭', () => {
    const props = renderMenu();
    fireEvent.click(screen.getByText('刷新'));
    expect(props.onSelect).toHaveBeenCalledWith('refresh');
    expect(props.onClose).toHaveBeenCalled();
  });

  it('禁用项不触发 onSelect', () => {
    const props = renderMenu();
    fireEvent.click(screen.getByText('重命名'));
    expect(props.onSelect).not.toHaveBeenCalled();
  });

  it('Esc 与菜单外点击都会关闭', () => {
    const props = renderMenu();
    fireEvent.keyDown(document, { key: 'Escape' });
    expect(props.onClose).toHaveBeenCalledTimes(1);
    fireEvent.mouseDown(document.body);
    expect(props.onClose).toHaveBeenCalledTimes(2);
  });

  it('悬停父项展开子菜单，子项点选同样回调', () => {
    const props = renderMenu();
    fireEvent.mouseOver(screen.getByText('更多'));
    fireEvent.click(screen.getByText('复制路径'));
    expect(props.onSelect).toHaveBeenCalledWith('copy');
    expect(screen.getByText('删除')).toBeInTheDocument();
  });
});
