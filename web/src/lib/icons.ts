import type { ComponentType } from 'react';

import {
  IconApps,
  IconBarChartHStroked,
  IconBell,
  IconBook,
  IconBriefcase,
  IconCalendar,
  IconClock,
  IconEyeOpened,
  IconFile,
  IconFilter,
  IconFolder,
  IconHome,
  IconIdCard,
  IconLayers,
  IconLink,
  IconList,
  IconMail,
  IconMapPin,
  IconMenu,
  IconPhone,
  IconPriceTag,
  IconSafe,
  IconSetting,
  IconStar,
  IconUser,
  IconUserGroup,
} from '@douyinfe/semi-icons';
import type { IconProps } from '@douyinfe/semi-icons/lib/es/components/Icon';

/**
 * 本地图标注册表。CSP `default-src 'self'` 下禁止运行时拉取
 * iconify 远程数据，图标一律编译期注册：名字（字符串，接口
 * 与存储里的取值）→ 组件。渲染统一走 `components/SvgIcon`；
 * Semi icons 是内置兜底集，插件可按需 registerIcon。
 */

export type IconComponent = ComponentType<Omit<IconProps, 'svg' | 'type'>>;

/** 接口/存储里未知名、空名的兜底图标。 */
export const FALLBACK_ICON = 'apps';

const registry = new Map<string, IconComponent>();

export function registerIcon(name: string, icon: IconComponent): void {
  registry.set(name, icon);
}

export function getIcon(name: string | null | undefined): IconComponent | undefined {
  if (!name) {
    return undefined;
  }
  return registry.get(name);
}

export function hasIcon(name: string | null | undefined): boolean {
  return name != null && registry.has(name);
}

/** 图标选择器等场景取全集；顺序即注册顺序。 */
export function listIconNames(): string[] {
  return [...registry.keys()];
}

// 内置集：与 /me/menus、菜单编辑器可选项同名，新增先确认两侧一致。
const BUILTIN_ICONS: Record<string, IconComponent> = {
  apps: IconApps,
  home: IconHome,
  users: IconUserGroup,
  user: IconUser,
  setting: IconSetting,
  menu: IconMenu,
  safe: IconSafe,
  chart: IconBarChartHStroked,
  file: IconFile,
  link: IconLink,
  star: IconStar,
  calendar: IconCalendar,
  clock: IconClock,
  mail: IconMail,
  phone: IconPhone,
  map: IconMapPin,
  tag: IconPriceTag,
  folder: IconFolder,
  list: IconList,
  filter: IconFilter,
  eye: IconEyeOpened,
  bell: IconBell,
  book: IconBook,
  building: IconBriefcase,
  idcard: IconIdCard,
  layers: IconLayers,
};

for (const [name, icon] of Object.entries(BUILTIN_ICONS)) {
  registerIcon(name, icon);
}
