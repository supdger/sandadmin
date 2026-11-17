import { useSyncExternalStore } from 'react';

/**
 * 多标签页 UI 态容器。标签栏组件（art-work-tab）与路由联动
 * 消费它：打开页面 openTab、关闭时返回该去的标签。
 * 会话级状态，不持久化。
 */

export interface WorkTab {
  /** 稳定标识，取路由 path；同 key 再次打开只激活不重复加。 */
  key: string;
  path: string;
  title: string;
}

/** 标签数上限；超出时最老的非激活标签被淘汰。 */
export const MAX_TABS = 12;

interface TabsState {
  tabs: WorkTab[];
  activeKey: string | null;
}

let state: TabsState = { tabs: [], activeKey: null };
const listeners = new Set<() => void>();

function setState(next: TabsState): void {
  state = next;
  for (const listener of listeners) {
    listener();
  }
}

export function openTab(tab: WorkTab): void {
  const existing = state.tabs.some((item) => item.key === tab.key);
  let tabs = existing
    ? state.tabs.map((item) => (item.key === tab.key ? tab : item))
    : [...state.tabs, tab];
  if (tabs.length > MAX_TABS) {
    const dropIndex = tabs.findIndex((item) => item.key !== tab.key);
    if (dropIndex >= 0) {
      tabs = tabs.filter((_, index) => index !== dropIndex);
    }
  }
  setState({ tabs, activeKey: tab.key });
}

export function activateTab(key: string): void {
  if (state.activeKey !== key && state.tabs.some((item) => item.key === key)) {
    setState({ ...state, activeKey: key });
  }
}

/** 关闭标签；返回关闭后应激活的标签（全关完为 null）。 */
export function closeTab(key: string): WorkTab | null {
  const index = state.tabs.findIndex((item) => item.key === key);
  if (index < 0) {
    return state.tabs.find((item) => item.key === state.activeKey) ?? null;
  }
  const tabs = state.tabs.filter((item) => item.key !== key);
  let activeKey = state.activeKey;
  if (activeKey === key) {
    // 优先右邻，没有就左邻——与用户视觉期望一致。
    activeKey = tabs[index]?.key ?? tabs[index - 1]?.key ?? null;
  }
  setState({ tabs, activeKey });
  return tabs.find((item) => item.key === activeKey) ?? null;
}

export function closeOtherTabs(key: string): void {
  const keep = state.tabs.filter((item) => item.key === key);
  setState({ tabs: keep, activeKey: keep.length > 0 ? key : null });
}

export function closeAllTabs(): void {
  setState({ tabs: [], activeKey: null });
}

export function getTabsState(): TabsState {
  return state;
}

function subscribe(callback: () => void): () => void {
  listeners.add(callback);
  return () => {
    listeners.delete(callback);
  };
}

const EMPTY_STATE: TabsState = { tabs: [], activeKey: null };

export function useTabs(): TabsState {
  return useSyncExternalStore(subscribe, getTabsState, () => EMPTY_STATE);
}

/** 测试钩子：清空全部标签。 */
export function resetTabs(): void {
  closeAllTabs();
}
