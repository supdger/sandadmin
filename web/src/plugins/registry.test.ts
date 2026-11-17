import { describe, expect, it } from 'vitest';

import i18n from '@/i18n/i18n';

import { getPluginModule, listPluginModules } from './registry';

describe('plugin registry', () => {
  it('collects the sand-hello plugin module', () => {
    const plugin = getPluginModule('sand-hello');
    expect(plugin?.code).toBe('sand-hello');
    expect(plugin?.permission).toBe('sand_hello:page:read');
    expect(plugin?.routes.length).toBeGreaterThan(0);
  });

  it('lists every collected module', () => {
    const codes = listPluginModules().map((plugin) => plugin.code);
    expect(codes).toContain('sand-hello');
  });

  it('returns undefined for an unregistered code', () => {
    expect(getPluginModule('no-such-plugin')).toBeUndefined();
  });

  it('registers plugin locales into the plugin-<code> namespace', () => {
    expect(i18n.t('page.title', { ns: 'plugin-sand-hello' })).toBe('你好，插件');
    expect(i18n.t('menu.home', { ns: 'plugin-sand-hello' })).toBe('你好插件');
  });
});
