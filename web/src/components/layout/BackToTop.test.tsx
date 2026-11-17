import { useRef } from 'react';

import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { BackToTop } from './BackToTop';

function Host() {
  const ref = useRef<HTMLDivElement>(null);
  return (
    <div>
      <div
        ref={ref}
        data-testid="scrollbox"
        style={{ height: 100, overflowY: 'auto' }}
      >
        <div style={{ height: 2000 }} />
      </div>
      <BackToTop target={ref} threshold={50} />
    </div>
  );
}

describe('BackToTop', () => {
  it('滚动超过阈值显示按钮，点击后滚回顶部', async () => {
    render(<Host />);
    const box = screen.getByTestId('scrollbox');

    expect(screen.queryByRole('button', { name: '回到顶部' })).not.toBeInTheDocument();

    box.scrollTop = 300;
    fireEvent.scroll(box);

    const button = await screen.findByRole('button', { name: '回到顶部' });
    fireEvent.click(button);

    // Semi BackTop 内部走 rAF 缓动，等它把 scrollTop 归零。
    await waitFor(() => expect(box.scrollTop).toBe(0), { timeout: 3000 });
  });
});
