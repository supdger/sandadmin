import { useEffect, useSyncExternalStore } from 'react';

import { useQuery } from '@tanstack/react-query';

import { getSettings } from '@/api/v1/settings';
import {
  applySiteSettings,
  getSiteSettings,
  settingsItemsToPatch,
  subscribeSiteSettings,
  type SiteSettings,
} from '@/lib/siteSettings';

/** 订阅全局站点设置（系统名/标志/时区/水印开关）。 */
export function useSiteSettings(): SiteSettings {
  return useSyncExternalStore(subscribeSiteSettings, getSiteSettings, getSiteSettings);
}

/**
 * 登录后把服务端设置灌进本地快照（刷新后恢复）。只有
 * `system:config:index` 的账号才发请求，普通账号不拉、用默认值。
 */
export function useSeedSiteSettings(canRead: boolean): void {
  const basic = useQuery({
    queryKey: ['settings', 'items', 'basic'],
    queryFn: () => getSettings('basic'),
    enabled: canRead,
    staleTime: 60_000,
  });
  const watermark = useQuery({
    queryKey: ['settings', 'items', 'watermark'],
    queryFn: () => getSettings('watermark'),
    enabled: canRead,
    staleTime: 60_000,
  });

  useEffect(() => {
    if (basic.data?.items) applySiteSettings(settingsItemsToPatch(basic.data.items));
  }, [basic.data]);
  useEffect(() => {
    if (watermark.data?.items) applySiteSettings(settingsItemsToPatch(watermark.data.items));
  }, [watermark.data]);
}
