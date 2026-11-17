import { beforeEach, describe, expect, it } from 'vitest';

import { clearToken, getToken } from '@/lib/authToken';
import { __authMock } from '@/mocks/handlers/auth';
import { devLogin, login } from './auth';

// 用例按真后端契约断言：响应 data 为 snake_case
// （{token_type, access_token, refresh_token, expires_in, must_change_password}），
// MSW 桩的形状与该契约一致。
describe('auth api token contract', () => {
  beforeEach(() => {
    clearToken();
    __authMock.reset();
  });

  it('parses the snake_case login payload and stores access_token', async () => {
    const result = await login({ username: 'admin', password: 'Admin@123456' });

    expect(result.token_type).toBe('Bearer');
    expect(result.access_token).toBe('mock-admin-token');
    expect(result.must_change_password).toBe(false);
    expect(getToken()).toBe('mock-admin-token');
  });

  it('surfaces must_change_password=true for accounts that must reset', async () => {
    const result = await login({ username: 'newbie', password: 'Admin@123456' });

    expect(result.must_change_password).toBe(true);
    expect(getToken()).toBe('mock-newbie-token');
  });

  it('parses the snake_case dev-login payload and stores access_token', async () => {
    const result = await devLogin();

    expect(result.access_token).toBe('mock-admin-token');
    expect(result.must_change_password).toBe(false);
    expect(getToken()).toBe('mock-admin-token');
  });
});
