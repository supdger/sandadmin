import type { ReactNode } from 'react';

import type { NavItems } from '@douyinfe/semi-ui/lib/es/navigation';

import type { MyMenuNode } from '@/api/v1/me';
import { SvgIcon } from '@/components/SvgIcon';
import i18n from '@/i18n/i18n';

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

// 图标名来自接口载荷，渲染走 SvgIcon 查本地注册表——未知名
// 由注册表兜底，菜单不会因为脏数据丢图标。
function menuIcon(item: MyMenuNode, depth: number): ReactNode {
  // 子项一律不带图标。内置页只有少数几项有图标时，侧栏会把它们看成分组。
  if (depth > 0) {
    return undefined;
  }
  return <SvgIcon name={item.icon} />;
}

/**
 * 菜单显示标题：节点带 plugin + title_key 时按 `plugin-<code>` 命名空间
 * 翻译（词条由插件前端注册），否则用后端下发的 title 兜底。
 */
export function resolveMenuTitle(item: MyMenuNode): string {
  if (item.plugin && item.title_key) {
    return i18n.t(item.title_key, {
      ns: `plugin-${item.plugin}`,
      defaultValue: item.title,
    });
  }
  return item.title;
}

/** MyMenuNode 树 → Semi Nav items；group/link 用 key 做标识，page 用 path。 */
export function toNavItems(menus: MyMenuNode[], depth = 0): NavItems {
  return menus.map((item) => {
    const icon = menuIcon(item, depth);
    if (item.children && item.children.length > 0) {
      return {
        itemKey: item.key,
        text: resolveMenuTitle(item),
        icon,
        items: toNavItems(item.children, depth + 1),
      };
    }
    const target = item.type === 'link' ? item.url : item.path;
    return { itemKey: target ?? item.key, text: resolveMenuTitle(item), icon };
  });
}
