import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { HighlightText } from './HighlightText';

describe('HighlightText', () => {
  it('命中片段渲染成 mark，其余原样输出', () => {
    render(<HighlightText text="系统设置" keyword="设置" />);
    const mark = screen.getByText('设置');
    expect(mark.tagName).toBe('MARK');
    expect(screen.getByText('系统').tagName).toBe('SPAN');
  });

  it('大小写不敏感命中', () => {
    render(<HighlightText text="User Table" keyword="user" />);
    const mark = screen.getByText('User');
    expect(mark.tagName).toBe('MARK');
  });

  it('关键词为空时不渲染 mark', () => {
    const { container } = render(<HighlightText text="没有高亮" />);
    expect(container.querySelector('mark')).toBeNull();
  });
});
