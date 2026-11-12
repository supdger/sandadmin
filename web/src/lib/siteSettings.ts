import { env } from '@/config/env';
import type { SettingItem } from '@/api/v1/settings';

/**
 * 全局站点设置的本会话快照：系统名字、标志、时区、水印开关。
 * 初值来自 env；系统设置页保存后 applySiteSettings 立即生效，
 * 刷新后由 AppShell 的种子查询（/settings/basic、/settings/watermark）恢复。
 */
export interface SiteSettings {
  appName: string;
  appLogo: string | null;
  /** 为空时按浏览器本地时区。 */
  timezone: string | null;
  /** null 表示还没读到配置——规格默认开启。 */
  watermarkEnabled: boolean | null;
}

export interface SiteSettingsPatch {
  appName?: string;
  appLogo?: string | null;
  timezone?: string | null;
  watermarkEnabled?: boolean | null;
}

let state: SiteSettings = {
  appName: env.appName,
  appLogo: env.appLogo,
  timezone: null,
  watermarkEnabled: null,
};

const listeners = new Set<() => void>();

export function subscribeSiteSettings(listener: () => void): () => void {
  listeners.add(listener);
  return () => listeners.delete(listener);
}

export function getSiteSettings(): SiteSettings {
  return state;
}

export function applySiteSettings(patch: SiteSettingsPatch): void {
  state = { ...state, ...patch };
  for (const listener of listeners) listener();
}

/** 测试专用：把快照复位到 env 初值。 */
export function resetSiteSettings(): void {
  state = {
    appName: env.appName,
    appLogo: env.appLogo,
    timezone: null,
    watermarkEnabled: null,
  };
  for (const listener of listeners) listener();
}

/** 把 /settings/{group} 的条目变成补丁；只认已知键。 */
export function settingsItemsToPatch(items: SettingItem[]): SiteSettingsPatch {
  const patch: SiteSettingsPatch = {};
  for (const item of items) {
    if (item.key === 'site_name' && typeof item.value === 'string' && item.value) {
      patch.appName = item.value;
    }
    if (item.key === 'site_logo') {
      patch.appLogo = typeof item.value === 'string' && item.value ? item.value : null;
    }
    if (item.key === 'timezone') {
      patch.timezone = typeof item.value === 'string' && item.value ? item.value : null;
    }
    if (item.key === 'watermark_enabled' && typeof item.value === 'boolean') {
      patch.watermarkEnabled = item.value;
    }
  }
  return patch;
}

/**
 * 指定时区下的「现在」（字段按该时区解释）；时区非法或为空回退本地。
 * 用 en-US 中间格式做时区换算，只用于取年月日/星期/小时。
 */
export function zonedNow(timezone: string | null | undefined, base: Date = new Date()): Date {
  if (!timezone) return base;
  try {
    const shifted = new Date(base.toLocaleString('en-US', { timeZone: timezone }));
    if (Number.isNaN(shifted.getTime())) return base;
    return shifted;
  } catch {
    return base;
  }
}
