import { beforeEach, describe, expect, it } from 'vitest';

import {
  applyThemeColor,
  getStoredColor,
  getStoredTheme,
  setTheme,
  setThemeColor,
} from './theme';

describe('theme', () => {
  beforeEach(() => {
    window.localStorage.clear();
    document.body.removeAttribute('theme-mode');
    document.body.removeAttribute('style');
  });

  it('defaults to auto and persists the chosen mode', () => {
    expect(getStoredTheme()).toBe('auto');
    setTheme('dark');
    expect(getStoredTheme()).toBe('dark');
    expect(document.body.getAttribute('theme-mode')).toBe('dark');
  });

  it('removes the attribute for the light theme', () => {
    setTheme('dark');
    setTheme('light');
    expect(document.body.getAttribute('theme-mode')).toBeNull();
  });

  it('rejects an invalid stored mode', () => {
    window.localStorage.setItem('sandadmin.theme', '"rainbow"');
    expect(getStoredTheme()).toBe('auto');
  });

  it('persists and applies the theme color as Semi vars', () => {
    expect(getStoredColor()).toBe('blue');
    setThemeColor('violet');
    expect(getStoredColor()).toBe('violet');
    expect(document.body.style.getPropertyValue('--semi-color-primary')).toBe('#6941c6');
    expect(document.body.style.getPropertyValue('--semi-color-primary-hover')).toBe('#855ed8');
  });

  it('applyThemeColor writes all four tokens', () => {
    applyThemeColor('green');
    const style = document.body.style;
    expect(style.getPropertyValue('--semi-color-primary')).toBe('#099250');
    expect(style.getPropertyValue('--semi-color-primary-active')).toBe('#077341');
    expect(style.getPropertyValue('--semi-color-primary-light-default')).toBe('#e6f7ee');
  });
});
