import { act, renderHook } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';

import {
  activateTab,
  closeAllTabs,
  closeOtherTabs,
  closeTab,
  getTabsState,
  MAX_TABS,
  openTab,
  resetTabs,
  useTabs,
} from './tabs';

function tab(key: string) {
  return { key, path: key, title: `T${key}` };
}

describe('tabs store', () => {
  afterEach(() => {
    resetTabs();
  });

  it('opens a tab and activates it', () => {
    openTab(tab('/a'));
    openTab(tab('/b'));
    expect(getTabsState().tabs.map((t) => t.key)).toEqual(['/a', '/b']);
    expect(getTabsState().activeKey).toBe('/b');
  });

  it('reopening an existing key only activates it', () => {
    openTab(tab('/a'));
    openTab(tab('/b'));
    openTab(tab('/a'));
    expect(getTabsState().tabs).toHaveLength(2);
    expect(getTabsState().activeKey).toBe('/a');
  });

  it('closing the active tab activates its right neighbour', () => {
    openTab(tab('/a'));
    openTab(tab('/b'));
    openTab(tab('/c'));
    activateTab('/b');
    const next = closeTab('/b');
    expect(next?.key).toBe('/c');
    expect(getTabsState().activeKey).toBe('/c');
  });

  it('closing the rightmost active tab activates the left neighbour', () => {
    openTab(tab('/a'));
    openTab(tab('/b'));
    const next = closeTab('/b');
    expect(next?.key).toBe('/a');
  });

  it('closing an inactive tab keeps the current active', () => {
    openTab(tab('/a'));
    openTab(tab('/b'));
    closeTab('/a');
    expect(getTabsState().activeKey).toBe('/b');
  });

  it('closeOtherTabs keeps only the given tab', () => {
    openTab(tab('/a'));
    openTab(tab('/b'));
    openTab(tab('/c'));
    closeOtherTabs('/b');
    expect(getTabsState().tabs.map((t) => t.key)).toEqual(['/b']);
    expect(getTabsState().activeKey).toBe('/b');
  });

  it('evicts the oldest tab beyond MAX_TABS', () => {
    for (let i = 0; i < MAX_TABS + 2; i += 1) {
      openTab(tab(`/t${i}`));
    }
    const { tabs } = getTabsState();
    expect(tabs).toHaveLength(MAX_TABS);
    expect(tabs[0].key).toBe('/t2');
  });

  it('exposes state through useTabs', () => {
    const { result } = renderHook(() => useTabs());
    act(() => openTab(tab('/a')));
    expect(result.current.tabs).toHaveLength(1);
    expect(result.current.activeKey).toBe('/a');
  });

  it('closeAllTabs empties the list', () => {
    openTab(tab('/a'));
    closeAllTabs();
    expect(getTabsState().tabs).toEqual([]);
    expect(getTabsState().activeKey).toBeNull();
  });
});
