import { afterEach, describe, expect, it } from 'vitest';

import { login } from '@/api/auth';
import { getMe, getMyMenus } from '@/api/v1/me';
import { getPreferences, putPreferences } from '@/api/preferences';
import { env } from '@/config/env';
import { ApiError } from '@/lib/apiClient';
import { AdminApiError } from '@/lib/adminClient';
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
      permissions: [
        'workbench:view',
        'preferences:view',
        'system:user:index',
        'system:dept:index',
        'system:post:index',
        'system:role:index',
        'system:role:permission:index',
        'system:menu:index',
        'system:dict:index',
        'system:file:index',
        'system:announcement:index',
      ],
    });

    setToken('mock-guest-token');
    await expect(getMe()).resolves.toMatchObject({ permissions: [] });
  });

  it('returns no protected pages for the no-permission account', async () => {
    setToken('mock-guest-token');
    const guestMenus = await getMyMenus();
    // 无权限的 page 全部滤掉；只剩无权限要求的 link（帮助文档）。
    const hasPage = (nodes: { type: string; children?: { type: string }[] }[]): boolean =>
      nodes.some((n) => n.type === 'page' || (n.children ? hasPage(n.children) : false));
    expect(hasPage(guestMenus.items)).toBe(false);

    setToken('mock-viewer-token');
    const menus = await getMyMenus();
    expect(menus.items.length).toBeGreaterThan(0);
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
    // /me、/me/menus 已迁 v1：错误类型是 AdminApiError；preferences 仍走旧 client。
    await expect(getMyMenus()).rejects.toBeInstanceOf(AdminApiError);
    await expect(getMe()).rejects.toBeInstanceOf(AdminApiError);
    await expect(getPreferences()).rejects.toBeInstanceOf(ApiError);
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
