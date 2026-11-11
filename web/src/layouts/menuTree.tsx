import type { ReactNode } from 'react';

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
import type { NavItems } from '@douyinfe/semi-ui/lib/es/navigation';

import type { MyMenuNode } from '@/api/v1/me';

/**
 * v1 `/me/menus` returns an already-nested tree of MyMenuNode
 * (group = 容器不可点 / page = 站内 path / link = 外链 url)。
 * 这组帮助函数只做查找与 Nav 结构转换，不再做扁平组装。
 */

/** Path of the currently active menu node and all its ancestors, root first. */
export function findMenuChain(menus: MyMenuNode[], pathname: string): MyMenuNode[] {
  for (const item of menus) {
    if (item.type === 'page' && item.path === pathname) {
      return [item];
    }
    if (item.children) {
      const sub = findMenuChain(item.children, pathname);
      if (sub.length > 0) {
        return [item, ...sub];
      }
    }
  }
  return [];
}

// Menu icons travel as names in the API payload; map them to icon components
// here so new menus only need a name, not a component reference.
const MENU_ICONS: Record<string, ReactNode> = {
  home: <IconHome />,
  setting: <IconSetting />,
  // 组织与用户域菜单用到的图标名（与 /me/menus、MSW 菜单数据保持一致）。
  users: <IconUserGroup />,
  user: <IconUser />,
  building: <IconBriefcase />,
  idcard: <IconIdCard />,
  safe: <IconSafe />,
  menu: <IconMenu />,
  chart: <IconBarChartHStroked />,
  // 菜单管理可自定义图标；键名与 rbac 菜单编辑器的图标集保持一致。
  file: <IconFile />,
  link: <IconLink />,
  star: <IconStar />,
  calendar: <IconCalendar />,
  clock: <IconClock />,
  mail: <IconMail />,
  phone: <IconPhone />,
  map: <IconMapPin />,
  tag: <IconPriceTag />,
  folder: <IconFolder />,
  list: <IconList />,
  filter: <IconFilter />,
  eye: <IconEyeOpened />,
  bell: <IconBell />,
  book: <IconBook />,
};

function menuIcon(item: MyMenuNode, depth: number): ReactNode {
  const icon = item.icon ? MENU_ICONS[item.icon] : undefined;
  // Collapsed top-level entries only show an icon, so always render one there.
  return icon ?? (depth === 0 ? <IconApps /> : undefined);
}

/** MyMenuNode 树 → Semi Nav items；group/link 用 key 做标识，page 用 path。 */
export function toNavItems(menus: MyMenuNode[], depth = 0): NavItems {
  return menus.map((item) => {
    const icon = menuIcon(item, depth);
    if (item.children && item.children.length > 0) {
      return {
        itemKey: item.key,
        text: item.title,
        icon,
        items: toNavItems(item.children, depth + 1),
      };
    }
    const target = item.type === 'link' ? item.url : item.path;
    return { itemKey: target ?? item.key, text: item.title, icon };
  });
}
