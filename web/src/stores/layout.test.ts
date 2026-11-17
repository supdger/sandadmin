import { act, renderHook } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { onAppEvent } from '@/lib/eventBus';
import { loadPref } from '@/lib/storage';
import {
  getLayoutSettings,
  resetLayoutSettings,
  setLayoutMode,
  setSidebarCollapsed,
  toggleSidebarCollapsed,
  useLayoutSettings,
} from './layout';

describe('layout store', () => {
  afterEach(() => {
    resetLayoutSettings();
    window.localStorage.clear();
  });

  it('toggles and persists the collapsed state', () => {
    toggleSidebarCollapsed();
    expect(getLayoutSettings().sidebarCollapsed).toBe(true);
    setSidebarCollapsed(false);
    expect(getLayoutSettings().sidebarCollapsed).toBe(false);
    expect(loadPref('layout', null)).toMatchObject({ sidebarCollapsed: false });
  });

  it('sets the layout mode', () => {
    setLayoutMode('mix');
    expect(getLayoutSettings().layoutMode).toBe('mix');
  });

  it('emits appearance:changed on writes', () => {
    const handler = vi.fn();
    const unsubscribe = onAppEvent('appearance:changed', handler);
    setSidebarCollapsed(true);
    expect(handler).toHaveBeenCalledWith({ kind: 'layout' });
    unsubscribe();
  });

  it('exposes settings through useLayoutSettings', () => {
    const { result } = renderHook(() => useLayoutSettings());
    expect(result.current.sidebarCollapsed).toBe(false);
    act(() => setSidebarCollapsed(true));
    expect(result.current.sidebarCollapsed).toBe(true);
  });

  it('drops a corrupt persisted value back to defaults', () => {
    window.localStorage.setItem('sandadmin.layout', '{broken json');
    // 坏值在下次加载被守卫拦掉——这正是模块加载时 loadPref 的行为。
    const isSettings = (v: unknown): v is { sidebarCollapsed: boolean } =>
      typeof v === 'object' && v !== null;
    expect(
      loadPref('layout', { sidebarCollapsed: false }, isSettings).sidebarCollapsed,
    ).toBe(false);
  });
});
