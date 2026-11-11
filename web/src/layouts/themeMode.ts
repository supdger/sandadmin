/**
 * Shared theme state for the shell. `lib/theme` owns persistence and the
 * `theme-mode` attribute; this module adds a same-tab change notification so
 * the header toggle and the preferences page stay in sync (localStorage's
 * `storage` event only fires across tabs).
 */

import { useCallback, useSyncExternalStore } from 'react';

import { getStoredTheme, setTheme, type ThemeMode } from '@/lib/theme';

const THEME_CHANGED_EVENT = 'sandadmin:theme-changed';
const MEDIA_QUERY = '(prefers-color-scheme: dark)';

/** Apply + persist the theme, then notify every listener in this tab. */
export function changeThemeMode(mode: ThemeMode): void {
  setTheme(mode);
  window.dispatchEvent(new Event(THEME_CHANGED_EVENT));
}

function subscribe(callback: () => void): () => void {
  window.addEventListener(THEME_CHANGED_EVENT, callback);
  const media =
    typeof window.matchMedia === 'function' ? window.matchMedia(MEDIA_QUERY) : null;
  media?.addEventListener('change', callback);
  return () => {
    window.removeEventListener(THEME_CHANGED_EVENT, callback);
    media?.removeEventListener('change', callback);
  };
}

function isDarkNow(): boolean {
  return document.body.getAttribute('theme-mode') === 'dark';
}

export interface ThemeState {
  /** The stored choice: light / dark / auto. */
  mode: ThemeMode;
  /** The effective appearance right now (auto resolved). */
  isDark: boolean;
  setMode: (mode: ThemeMode) => void;
}

export function useThemeMode(): ThemeState {
  const mode = useSyncExternalStore(
    subscribe,
    getStoredTheme,
    () => 'auto' as ThemeMode,
  );
  const isDark = useSyncExternalStore(subscribe, isDarkNow, () => false);
  const setMode = useCallback((m: ThemeMode) => changeThemeMode(m), []);
  return { mode, isDark, setMode };
}
