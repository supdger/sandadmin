import '@douyinfe/semi-ui/react19-adapter';

import { render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';

import { Watermark } from './Watermark';

describe('Watermark', () => {
  afterEach(() => {
    document.body.removeAttribute('theme-mode');
  });

  it('按文字平铺 SVG 水印，不挡交互', () => {
    render(<Watermark text="管理员" />);
    const layer = screen.getByTestId('watermark');
    expect(layer.getAttribute('aria-hidden')).toBe('true');
    const style = layer.getAttribute('style') ?? '';
    expect(style).toContain('pointer-events: none');
    expect(decodeURIComponent(style)).toContain('管理员');
  });

  it('空文字不渲染', () => {
    const { container } = render(<Watermark text="" />);
    expect(container.querySelector('[data-testid="watermark"]')).toBeNull();
  });

  it('深色模式用浅色水印，浅色模式用深色水印', () => {
    const light = render(<Watermark text="管理员" />);
    const lightStyle = decodeURIComponent(
      screen.getByTestId('watermark').getAttribute('style') ?? '',
    );
    expect(lightStyle).toContain('rgba(0,0,0,0.07)');
    light.unmount();

    document.body.setAttribute('theme-mode', 'dark');
    render(<Watermark text="管理员" />);
    const darkStyle = decodeURIComponent(
      screen.getByTestId('watermark').getAttribute('style') ?? '',
    );
    expect(darkStyle).toContain('rgba(255,255,255,0.06)');
  });
});
