import { afterEach, describe, expect, it, vi } from 'vitest';

import { env } from '@/config/env';
import {
  applySiteSettings,
  getSiteSettings,
  resetSiteSettings,
  settingsItemsToPatch,
  subscribeSiteSettings,
  zonedNow,
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

  it('zonedNow 按指定时区换算，非法/空时区回退本地', () => {
    const base = new Date('2026-11-12T23:30:00Z');
    // UTC+14：23:30 UTC 已是次日 13:30。
    const kiritimati = zonedNow('Pacific/Kiritimati', base);
    expect(kiritimati.getDate()).toBe(13);
    expect(kiritimati.getHours()).toBe(13);

    const utc = zonedNow('UTC', base);
    expect(utc.getDate()).toBe(12);
    expect(utc.getHours()).toBe(23);

    expect(zonedNow(null, base)).toBe(base);
    expect(zonedNow('Not/AZone', base)).toBe(base);
  });
});
