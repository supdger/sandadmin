import '@douyinfe/semi-ui/react19-adapter';
import '@/i18n/i18n';

import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { ImageCropper } from './ImageCropper';

describe('ImageCropper', () => {
  it('渲染舞台、缩放与两个操作按钮', () => {
    render(<ImageCropper src="blob:x" onConfirm={vi.fn()} onCancel={vi.fn()} />);
    expect(screen.getByTestId('image-cropper')).toBeInTheDocument();
    expect(screen.getByText('缩放')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '确定' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '取消' })).toBeInTheDocument();
  });

  it('取消回调 onCancel', () => {
    const onCancel = vi.fn();
    render(<ImageCropper src="blob:x" onConfirm={vi.fn()} onCancel={onCancel} />);
    fireEvent.click(screen.getByRole('button', { name: '取消' }));
    expect(onCancel).toHaveBeenCalledTimes(1);
  });

  it('图片未加载时确定键禁用', () => {
    render(<ImageCropper src="blob:x" onConfirm={vi.fn()} onCancel={vi.fn()} />);
    expect(screen.getByRole('button', { name: '确定' })).toBeDisabled();
  });
});
