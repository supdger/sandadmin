import { useSyncExternalStore } from 'react';

import { loadPref, removePref, savePref } from '@/lib/storage';

/**
 * 布局包的全局 UI 态：设置面板开关、锁屏、多标签开关、混合布局
 * 当前一级栏目。锁屏与多标签是设备偏好（持久化到 UI 偏好存储），
 * 面板开关与一级栏目只活在本会话。锁屏只是界面罩层，不是安全
 * 边界——会话本身不受影响，所以能进 UI 偏好存储。
 */

export interface GlobalUiState {
  settingsOpen: boolean;
  screenLocked: boolean;
  tabsEnabled: boolean;
  /** 混合布局下侧栏跟随的一级菜单 key；路由能命中时以路由为准。 */
  activeTopKey: string | null;
}

function isBoolean(value: unknown): value is boolean {
  return typeof value === 'boolean';
}

const LOCK_PREF_KEY = 'screenLock';
const TABS_PREF_KEY = 'tabsEnabled';

const DEFAULT_STATE: GlobalUiState = {
  settingsOpen: false,
  screenLocked: false,
  tabsEnabled: false,
  activeTopKey: null,
};

let state: GlobalUiState = {
  ...DEFAULT_STATE,
  screenLocked: loadPref(LOCK_PREF_KEY, false, isBoolean),
  tabsEnabled: loadPref(TABS_PREF_KEY, false, isBoolean),
};
const listeners = new Set<() => void>();

function update(patch: Partial<GlobalUiState>): void {
  state = { ...state, ...patch };
  for (const listener of listeners) {
    listener();
  }
}

export function openSettings(): void {
  update({ settingsOpen: true });
}

export function closeSettings(): void {
  update({ settingsOpen: false });
}

export function lockScreen(): void {
  savePref(LOCK_PREF_KEY, true);
  update({ screenLocked: true });
}

export function clearScreenLock(): void {
  savePref(LOCK_PREF_KEY, false);
  update({ screenLocked: false });
}

export function setTabsEnabled(enabled: boolean): void {
  savePref(TABS_PREF_KEY, enabled);
  update({ tabsEnabled: enabled });
}

export function setActiveTopKey(key: string | null): void {
  update({ activeTopKey: key });
}

export function getGlobalUi(): GlobalUiState {
  return state;
}

function subscribe(callback: () => void): () => void {
  listeners.add(callback);
  return () => {
    listeners.delete(callback);
  };
}

export function useGlobalUi(): GlobalUiState {
  return useSyncExternalStore(subscribe, getGlobalUi, () => DEFAULT_STATE);
}

/** 测试钩子：回到默认态并清掉持久化值。 */
export function resetGlobalUi(): void {
  state = DEFAULT_STATE;
  removePref(LOCK_PREF_KEY);
  removePref(TABS_PREF_KEY);
  for (const listener of listeners) {
    listener();
  }
}
