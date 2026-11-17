import { createElement } from 'react';

import { describe, expect, it } from 'vitest';

import { FALLBACK_ICON, getIcon, hasIcon, listIconNames, registerIcon } from './icons';

describe('icons registry', () => {
  it('resolves a built-in icon by name', () => {
    expect(getIcon('home')).toBeDefined();
    expect(hasIcon('home')).toBe(true);
  });

  it('returns undefined for unknown or empty names', () => {
    expect(getIcon('not-an-icon')).toBeUndefined();
    expect(getIcon(null)).toBeUndefined();
    expect(getIcon('')).toBeUndefined();
    expect(hasIcon('not-an-icon')).toBe(false);
  });

  it('registers a custom icon for later lookup', () => {
    const Custom = () => createElement('span', null, 'x');
    registerIcon('custom-test-icon', Custom);
    expect(getIcon('custom-test-icon')).toBe(Custom);
    expect(listIconNames()).toContain('custom-test-icon');
  });

  it('keeps the fallback icon registered', () => {
    expect(getIcon(FALLBACK_ICON)).toBeDefined();
  });
});
