import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { ImageCard } from './ImageCard';

describe('ImageCard', () => {
  it('渲染封面图、标题与描述', () => {
    render(
      <ImageCard
        cover="/static/banner.png"
        coverAlt="宣传图"
        title="新版本上线"
        description="1.0 功能一览"
      />,
    );
    const image = screen.getByAltText('宣传图');
    expect(image).toHaveAttribute('src', '/static/banner.png');
    expect(screen.getByText('新版本上线')).toBeInTheDocument();
    expect(screen.getByText('1.0 功能一览')).toBeInTheDocument();
  });

  it('actions 渲染在底部操作位', () => {
    render(
      <ImageCard
        cover="/x.png"
        title="图卡"
        actions={[<button key="go">去看看</button>]}
      />,
    );
    expect(screen.getByRole('button', { name: '去看看' })).toBeInTheDocument();
  });
});
