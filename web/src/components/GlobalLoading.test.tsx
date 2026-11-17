import { act, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { eventBus } from '@/lib/eventBus';
import { resetLoading } from '@/lib/loading';
import { GlobalLoading } from './GlobalLoading';

describe('GlobalLoading', () => {
  afterEach(() => {
    act(() => resetLoading());
  });

  it('renders nothing while no request is active', () => {
    const { container } = render(<GlobalLoading />);
    expect(container.firstChild).toBeNull();
  });

  it('shows a progress bar while a request is in flight', () => {
    render(<GlobalLoading />);
    act(() => {
      eventBus.emit('request:begin', { url: '/x', method: 'GET' });
    });
    expect(screen.getByRole('progressbar')).toBeInTheDocument();
    act(() => {
      eventBus.emit('request:end', { url: '/x', method: 'GET' });
    });
    expect(screen.queryByRole('progressbar')).toBeNull();
  });
});
