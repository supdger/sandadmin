/**
 * Light/dark theme plumbing. Semi Design switches via the `theme-mode`
 * attribute on <body>; the user's choice is persisted in localStorage.
 * `auto` follows the OS preference.
 *
 * 主题色走预设色板——Semi 的派生色（hover/active/light）不成
 * 线性关系，现场算色不可靠，每档色值都在色板表里写死。
 */

import { loadPref, savePref } from './storage';

export type ThemeMode = 'light' | 'dark' | 'auto';

export type ThemeColor = 'blue' | 'violet' | 'green' | 'orange' | 'red';

interface ColorTokens {
  primary: string;
  hover: string;
  active: string;
  light: string;
}

/** 各档主题色的 Semi 变量取值；blue 即 Semi 默认色。 */
const COLOR_TOKENS: Record<ThemeColor, ColorTokens> = {
  blue: { primary: '#1664ff', hover: '#4683f9', active: '#074bd9', light: '#eaf1ff' },
  violet: { primary: '#6941c6', hover: '#855ed8', active: '#4f2d9e', light: '#f1eafd' },
  green: { primary: '#099250', hover: '#2cb16f', active: '#077341', light: '#e6f7ee' },
  orange: { primary: '#e8590c', hover: '#f07c36', active: '#c44b05', light: '#fdf0e5' },
  red: { primary: '#d92d20', hover: '#e35347', active: '#b42318', light: '#fdecea' },
};

const THEME_PREF_KEY = 'theme';
const COLOR_PREF_KEY = 'themeColor';
const MEDIA_QUERY = '(prefers-color-scheme: dark)';

function isThemeMode(value: unknown): value is ThemeMode {
  return value === 'light' || value === 'dark' || value === 'auto';
}

function isThemeColor(value: unknown): value is ThemeColor {
  return typeof value === 'string' && value in COLOR_TOKENS;
}

export function getStoredTheme(): ThemeMode {
  return loadPref(THEME_PREF_KEY, 'auto', isThemeMode);
}

export function getStoredColor(): ThemeColor {
  return loadPref(COLOR_PREF_KEY, 'blue', isThemeColor);
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

/** 把色板变量写到 body 内联样式：Semi 组件经继承取值，覆盖 :root 定义。 */
export function applyThemeColor(color: ThemeColor): void {
  if (typeof document === 'undefined') {
    return;
  }
  const tokens = COLOR_TOKENS[color];
  const style = document.body.style;
  style.setProperty('--semi-color-primary', tokens.primary);
  style.setProperty('--semi-color-primary-hover', tokens.hover);
  style.setProperty('--semi-color-primary-active', tokens.active);
  style.setProperty('--semi-color-primary-light-default', tokens.light);
}

export function setTheme(mode: ThemeMode): void {
  savePref(THEME_PREF_KEY, mode);
  applyTheme(mode);
}

export function setThemeColor(color: ThemeColor): void {
  savePref(COLOR_PREF_KEY, color);
  applyThemeColor(color);
}

/** Apply the stored theme and keep `auto` in sync with the OS preference. */
export function initTheme(): void {
  const mode = getStoredTheme();
  applyTheme(mode);
  applyThemeColor(getStoredColor());
  if (mode === 'auto' && typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
    window.matchMedia(MEDIA_QUERY).addEventListener('change', () => applyTheme('auto'));
  }
}
