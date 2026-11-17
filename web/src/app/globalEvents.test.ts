import { QueryClient } from '@tanstack/react-query';
import { describe, expect, it, vi } from 'vitest';

import { eventBus } from '@/lib/eventBus';
import { ME_QUERY_KEY } from '@/hooks/useMe';
import { setToken, getToken } from '@/lib/authToken';
import { bindGlobalEvents, type NavigateTarget } from './globalEvents';

function fakeRouter(pathname = '/system/user', search = ''): NavigateTarget {
  return {
    state: { location: { pathname, search } },
    navigate: vi.fn(),
  };
}

describe('bindGlobalEvents', () => {
  it('navigates to the login page with the return address on session expiry', () => {
    setToken('mock-admin-token');
    const queryClient = new QueryClient();
    queryClient.setQueryData(ME_QUERY_KEY, { id: 'u1' });
    const router = fakeRouter('/system/user', '?x=1');
    const unbind = bindGlobalEvents(router, queryClient);

    eventBus.emit('session:expired', { message: '登录已过期' });

    expect(getToken()).toBeNull();
    expect(queryClient.getQueryData(ME_QUERY_KEY)).toBeUndefined();
    expect(router.navigate).toHaveBeenCalledWith(
      `/login?redirect=${encodeURIComponent('/system/user?x=1')}`,
      { replace: true },
    );
    unbind();
  });

  it('does not navigate when already on the login page', () => {
    const router = fakeRouter('/login');
    const unbind = bindGlobalEvents(router, new QueryClient());
    eventBus.emit('session:expired', { message: 'x' });
    expect(router.navigate).not.toHaveBeenCalled();
    unbind();
  });

  it('stops reacting after unbind', () => {
    const router = fakeRouter();
    const unbind = bindGlobalEvents(router, new QueryClient());
    unbind();
    eventBus.emit('session:expired', { message: 'x' });
    expect(router.navigate).not.toHaveBeenCalled();
  });
});
