import { beforeEach, describe, expect, it } from 'vitest';

import { loadPref, removePref, savePref } from './storage';

describe('storage', () => {
  beforeEach(() => {
    window.localStorage.clear();
  });

  it('round-trips a JSON value under the sandadmin. prefix', () => {
    savePref('sidebar', { collapsed: true });
    expect(window.localStorage.getItem('sandadmin.sidebar')).toBe('{"collapsed":true}');
    expect(loadPref('sidebar', { collapsed: false })).toEqual({ collapsed: true });
  });

  it('returns the fallback for a missing key', () => {
    expect(loadPref('never-set', 'auto')).toBe('auto');
  });

  it('rejects values failing the guard', () => {
    window.localStorage.setItem('sandadmin.theme', '"banana"');
    const isMode = (v: unknown): v is 'light' | 'dark' => v === 'light' || v === 'dark';
    expect(loadPref('theme', 'light', isMode)).toBe('light');
  });

  it('reads a legacy raw string written before JSON encoding', () => {
    window.localStorage.setItem('sandadmin.theme', 'dark');
    const isMode = (v: unknown): v is 'light' | 'dark' => v === 'light' || v === 'dark';
    expect(loadPref('theme', 'light', isMode)).toBe('dark');
  });

  it('removes a stored value', () => {
    savePref('sidebar', { collapsed: true });
    removePref('sidebar');
    expect(loadPref('sidebar', null)).toBeNull();
  });
});
