import { afterEach, describe, expect, it } from 'vitest';

import { clearToken, getToken, setToken } from './authToken';

describe('authToken', () => {
  afterEach(() => {
    clearToken();
  });

  it('returns null when no token is stored', () => {
    expect(getToken()).toBeNull();
  });

  it('stores and reads back a token', () => {
    setToken('abc-token');
    expect(getToken()).toBe('abc-token');
  });

  it('clears a stored token', () => {
    setToken('abc-token');
    clearToken();
    expect(getToken()).toBeNull();
  });

  it('clears instead of writing "undefined" when the token is missing', () => {
    setToken('abc-token');
    setToken(undefined);
    expect(getToken()).toBeNull();
    expect(window.localStorage.getItem('sandadmin.token')).toBeNull();
  });

  it('treats a legacy "undefined" string in storage as no token', () => {
    window.localStorage.setItem('sandadmin.token', 'undefined');
    expect(getToken()).toBeNull();
  });
});
