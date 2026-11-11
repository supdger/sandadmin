/**
 * Light/dark theme plumbing. Semi Design switches via the `theme-mode`
 * attribute on <body>; the user's choice is persisted in localStorage.
 * `auto` follows the OS preference.
 */

export type ThemeMode = 'light' | 'dark' | 'auto';

const STORAGE_KEY = 'sandadmin.theme';
const MEDIA_QUERY = '(prefers-color-scheme: dark)';

function storage(): Storage | null {
  if (typeof window === 'undefined') {
    return null;
  }
  try {
    return window.localStorage;
  } catch {
    return null;
  }
}

export function getStoredTheme(): ThemeMode {
  try {
    const v = storage()?.getItem(STORAGE_KEY);
    if (v === 'light' || v === 'dark' || v === 'auto') {
      return v;
    }
  } catch {
    // storage unavailable — fall through to default
  }
  return 'auto';
}

function prefersDark(): boolean {
  return (
    typeof window !== 'undefined' &&
    typeof window.matchMedia === 'function' &&
    window.matchMedia(MEDIA_QUERY).matches
  );
}

export function applyTheme(mode: ThemeMode): void {
  if (typeof document === 'undefined') {
    return;
  }
  const dark = mode === 'dark' || (mode === 'auto' && prefersDark());
  if (dark) {
    document.body.setAttribute('theme-mode', 'dark');
  } else {
    document.body.removeAttribute('theme-mode');
  }
}

export function setTheme(mode: ThemeMode): void {
  try {
    storage()?.setItem(STORAGE_KEY, mode);
  } catch {
    // storage unavailable — apply for this session only
  }
  applyTheme(mode);
}

/** Apply the stored theme and keep `auto` in sync with the OS preference. */
export function initTheme(): void {
  const mode = getStoredTheme();
  applyTheme(mode);
  if (mode === 'auto' && typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
    window.matchMedia(MEDIA_QUERY).addEventListener('change', () => applyTheme('auto'));
  }
}
