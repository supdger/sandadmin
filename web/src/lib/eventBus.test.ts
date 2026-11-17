import { describe, expect, it, vi } from 'vitest';

import { AdminApiError } from './adminClient';
import { eventBus, onAppEvent } from './eventBus';

describe('eventBus', () => {
  it('delivers an emitted event to subscribers', () => {
    const handler = vi.fn();
    const unsubscribe = onAppEvent('session:expired', handler);
    eventBus.emit('session:expired', { message: '登录已过期' });
    expect(handler).toHaveBeenCalledWith({ message: '登录已过期' });
    unsubscribe();
  });

  it('stops delivering after unsubscribe', () => {
    const handler = vi.fn();
    const unsubscribe = onAppEvent('request:begin', handler);
    unsubscribe();
    eventBus.emit('request:begin', { url: '/x', method: 'GET' });
    expect(handler).not.toHaveBeenCalled();
  });

  it('carries the AdminApiError payload on sudo:required', () => {
    const error = new AdminApiError({
      code: 'SUDO_REQUIRED',
      message: '需要再次验证',
      httpStatus: 403,
      method: 'POST',
      url: '/x',
    });
    const handler = vi.fn();
    const unsubscribe = onAppEvent('sudo:required', handler);
    eventBus.emit('sudo:required', { error });
    expect(handler).toHaveBeenCalledWith({ error });
    unsubscribe();
  });
});
