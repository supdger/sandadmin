import { act, renderHook } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';

import {
  clearScreenLock,
  closeSettings,
  getGlobalUi,
  lockScreen,
  openSettings,
  resetGlobalUi,
  setActiveTopKey,
  setTabsEnabled,
  useGlobalUi,
} from './globalUi';

describe('globalUi store', () => {
  afterEach(() => {
    resetGlobalUi();
  });

  it('设置面板开关切换 settingsOpen', () => {
    openSettings();
    expect(getGlobalUi().settingsOpen).toBe(true);
    closeSettings();
    expect(getGlobalUi().settingsOpen).toBe(false);
  });

  it('锁屏态持久化到 UI 偏好存储', () => {
    lockScreen();
    expect(getGlobalUi().screenLocked).toBe(true);
    expect(localStorage.getItem('sandadmin.screenLock')).toBe('true');
    clearScreenLock();
    expect(getGlobalUi().screenLocked).toBe(false);
    expect(localStorage.getItem('sandadmin.screenLock')).toBe('false');
  });

  it('多标签开关持久化到 UI 偏好存储', () => {
    setTabsEnabled(true);
    expect(getGlobalUi().tabsEnabled).toBe(true);
    expect(localStorage.getItem('sandadmin.tabsEnabled')).toBe('true');
  });

  it('一级栏目键随 setActiveTopKey 变化', () => {
    setActiveTopKey('data');
    expect(getGlobalUi().activeTopKey).toBe('data');
    setActiveTopKey(null);
    expect(getGlobalUi().activeTopKey).toBeNull();
  });

  it('useGlobalUi 暴露同一份状态', () => {
    const { result } = renderHook(() => useGlobalUi());
    act(() => openSettings());
    expect(result.current.settingsOpen).toBe(true);
  });
});
