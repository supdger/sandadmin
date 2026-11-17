/**
 * Shared theme state for the shell. `lib/theme` owns persistence and the
 * `theme-mode` attribute; this module adds same-tab change notification
 * via the event bus so the header toggle, the preferences page and
 * non-React consumers (echarts) stay in sync.
 */

import { useCallback, useSyncExternalStore } from 'react';

import { eventBus, onAppEvent } from '@/lib/eventBus';
import {
  getStoredColor,
  getStoredTheme,
  setTheme,
  setThemeColor,
  type ThemeColor,
  type ThemeMode,
} from '@/lib/theme';

const MEDIA_QUERY = '(prefers-color-scheme: dark)';

/** Apply + persist the theme, then notify every listener in this tab. */
export function changeThemeMode(mode: ThemeMode): void {
  setTheme(mode);
  eventBus.emit('appearance:changed', { kind: 'mode' });
}

/** Apply + persist the theme color, then notify listeners. */
export function changeThemeColor(color: ThemeColor): void {
  setThemeColor(color);
  eventBus.emit('appearance:changed', { kind: 'color' });
}

function subscribe(callback: () => void): () => void {
  const unsubscribe = onAppEvent('appearance:changed', callback);
  const media =
    typeof window.matchMedia === 'function' ? window.matchMedia(MEDIA_QUERY) : null;
  media?.addEventListener('change', callback);
  return () => {
    unsubscribe();
    media?.removeEventListener('change', callback);
  };
}

function isDarkNow(): boolean {
  return document.body.getAttribute('theme-mode') === 'dark';
}

export interface ThemeState {
  /** The stored choice: light / dark / auto. */
  mode: ThemeMode;
  /** 主题色板档位。 */
  color: ThemeColor;
  /** The effective appearance right now (auto resolved). */
  isDark: boolean;
  setMode: (mode: ThemeMode) => void;
  setColor: (color: ThemeColor) => void;
}

export function useThemeMode(): ThemeState {
  const mode = useSyncExternalStore(
    subscribe,
    getStoredTheme,
    () => 'auto' as ThemeMode,
  );
  const color = useSyncExternalStore(subscribe, getStoredColor, () => 'blue' as ThemeColor);
  const isDark = useSyncExternalStore(subscribe, isDarkNow, () => false);
  const setMode = useCallback((m: ThemeMode) => changeThemeMode(m), []);
  const setColor = useCallback((c: ThemeColor) => changeThemeColor(c), []);
  return { mode, color, isDark, setMode, setColor };
}
