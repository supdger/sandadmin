import { afterEach, describe, expect, it, vi } from 'vitest';

import { env } from '@/config/env';
import {
  applySiteSettings,
  getSiteSettings,
  resetSiteSettings,
  settingsItemsToPatch,
  subscribeSiteSettings,
} from './siteSettings';

describe('siteSettings', () => {
  afterEach(() => {
    resetSiteSettings();
  });

  it('初始值来自 env，apply 后订阅者立刻收到新值', () => {
    expect(getSiteSettings().appName).toBe(env.appName);
    expect(getSiteSettings().watermarkEnabled).toBeNull();

    const listener = vi.fn();
    const unsubscribe = subscribeSiteSettings(listener);
    applySiteSettings({ appName: '新后台', watermarkEnabled: false });

    expect(listener).toHaveBeenCalledTimes(1);
    expect(getSiteSettings().appName).toBe('新后台');
    expect(getSiteSettings().watermarkEnabled).toBe(false);

    unsubscribe();
    applySiteSettings({ appName: '再改' });
    expect(listener).toHaveBeenCalledTimes(1);
  });

  it('settingsItemsToPatch 只认已知键', () => {
    const patch = settingsItemsToPatch([
      { key: 'site_name', label: 'x', type: 'text', value: '改名后', has_value: true },
      { key: 'site_logo', label: 'x', type: 'image', value: '/logo.png', has_value: true },
      { key: 'timezone', label: 'x', type: 'select', value: 'Asia/Tokyo', has_value: true },
      { key: 'watermark_enabled', label: 'x', type: 'boolean', value: false, has_value: true },
      { key: 'unrelated', label: 'x', type: 'text', value: 'ignore', has_value: true },
    ]);
    expect(patch).toEqual({
      appName: '改名后',
      appLogo: '/logo.png',
      timezone: 'Asia/Tokyo',
      watermarkEnabled: false,
    });
  });

});
