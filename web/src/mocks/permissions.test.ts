import { afterEach, describe, expect, it } from 'vitest';

import { login, getMe } from '@/api/auth';
import { getMyMenus } from '@/api/menus';
import { getPreferences, putPreferences } from '@/api/preferences';
import { env } from '@/config/env';
import { ApiError } from '@/lib/apiClient';
import { clearToken, setToken } from '@/lib/authToken';

/**
 * Permission semantics of the MSW demo accounts. viewer/guest tokens are
 * pre-seeded so most cases need no env config; the login cases only run when
 * the matching VITE_MOCK_*_PASSWORD is set (see .env.example).
 */
describe('mock demo account permissions', () => {
  afterEach(() => {
    clearToken();
  });

  it('reports per-account permissions from /me', async () => {
    setToken('mock-admin-token');
    await expect(getMe()).resolves.toMatchObject({ permissions: ['*'] });

    setToken('mock-viewer-token');
    await expect(getMe()).resolves.toMatchObject({
      permissions: ['workbench:view', 'preferences:view'],
    });

    setToken('mock-guest-token');
    await expect(getMe()).resolves.toMatchObject({ permissions: [] });
  });

  it('returns an empty menu for the no-permission account', async () => {
    setToken('mock-guest-token');
    await expect(getMyMenus()).resolves.toEqual([]);

    setToken('mock-viewer-token');
    const menus = await getMyMenus();
    expect(menus.length).toBeGreaterThan(0);
  });

  it('rejects writes for the read-only account but allows reads', async () => {
    setToken('mock-viewer-token');
    await expect(getPreferences()).resolves.toBeTruthy();
    await expect(putPreferences({ theme: 'dark' })).rejects.toMatchObject({ code: 4030 });
  });

  it('rejects even reads for the no-permission account', async () => {
    setToken('mock-guest-token');
    await expect(getPreferences()).rejects.toMatchObject({ code: 4030 });
    await expect(putPreferences({ theme: 'dark' })).rejects.toMatchObject({ code: 4030 });
  });

  it('still lets admin write', async () => {
    setToken('mock-admin-token');
    await expect(putPreferences({ theme: 'light' })).resolves.toBeNull();
  });

  it('rejects an unknown token on permission-scoped endpoints', async () => {
    setToken('t-unknown');
    await expect(getMyMenus()).rejects.toBeInstanceOf(ApiError);
    await expect(getMe()).rejects.toBeInstanceOf(ApiError);
  });

  it.skipIf(!env.mockViewerPassword)('viewer can log in when the env password is set', async () => {
    const res = await login({ username: 'viewer', password: env.mockViewerPassword as string });
    expect(res.token).toBe('mock-viewer-token');
    await expect(getMe()).resolves.toMatchObject({ username: 'viewer' });
  });

  it.skipIf(!env.mockGuestPassword)('guest can log in when the env password is set', async () => {
    const res = await login({ username: 'guest', password: env.mockGuestPassword as string });
    expect(res.token).toBe('mock-guest-token');
    await expect(getMe()).resolves.toMatchObject({ username: 'guest', permissions: [] });
  });
});
