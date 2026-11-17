import { useSyncExternalStore } from 'react';

import { eventBus } from '@/lib/eventBus';
import { loadPref, removePref, savePref } from '@/lib/storage';

/**
 * 布局 UI 态容器：侧栏折叠与整体布局模式，持久化到 UI 偏好存储。
 * 设置面板、顶栏开关、图表自适应都从这里读；写操作发出
 * appearance:changed 事件供非 React 消费者（如 echarts）跟随。
 */

/** 菜单形态：侧边 / 顶部 / 混合。 */
export type LayoutMode = 'side' | 'top' | 'mix';

export interface LayoutSettings {
  sidebarCollapsed: boolean;
  layoutMode: LayoutMode;
}

const LAYOUT_SETTING_KEY = 'layout';

function isLayoutSettings(value: unknown): value is LayoutSettings {
  if (typeof value !== 'object' || value === null) {
    return false;
  }
  const settings = value as Partial<LayoutSettings>;
  return (
    typeof settings.sidebarCollapsed === 'boolean' &&
    (settings.layoutMode === 'side' || settings.layoutMode === 'top' || settings.layoutMode === 'mix')
  );
}

const DEFAULT_SETTINGS: LayoutSettings = { sidebarCollapsed: false, layoutMode: 'side' };

let settings: LayoutSettings = loadPref(LAYOUT_SETTING_KEY, DEFAULT_SETTINGS, isLayoutSettings);
const listeners = new Set<() => void>();

function updateSettings(patch: Partial<LayoutSettings>): void {
  settings = { ...settings, ...patch };
  savePref(LAYOUT_SETTING_KEY, settings);
  for (const listener of listeners) {
    listener();
  }
  eventBus.emit('appearance:changed', { kind: 'layout' });
}

export function setSidebarCollapsed(collapsed: boolean): void {
  updateSettings({ sidebarCollapsed: collapsed });
}

export function toggleSidebarCollapsed(): void {
  updateSettings({ sidebarCollapsed: !settings.sidebarCollapsed });
}

export function setLayoutMode(mode: LayoutMode): void {
  updateSettings({ layoutMode: mode });
}

export function getLayoutSettings(): LayoutSettings {
  return settings;
}

function subscribe(callback: () => void): () => void {
  listeners.add(callback);
  return () => {
    listeners.delete(callback);
  };
}

export function useLayoutSettings(): LayoutSettings {
  return useSyncExternalStore(subscribe, getLayoutSettings, () => DEFAULT_SETTINGS);
}

/** 测试钩子：回到默认态并清掉持久化值。 */
export function resetLayoutSettings(): void {
  settings = DEFAULT_SETTINGS;
  removePref(LAYOUT_SETTING_KEY);
  for (const listener of listeners) {
    listener();
  }
}
