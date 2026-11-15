import type { MyMenuNode } from '@/api/v1/me';

/** 菜单标题常见汉字 → 拼音首字母。覆盖不到的字符原样参与匹配。 */
const PINYIN_INITIAL: Record<string, string> = {
  用: 'y', 户: 'h', 管: 'g', 理: 'l', 组: 'z', 织: 'z', 与: 'y', 部: 'b',
  门: 'm', 岗: 'g', 位: 'w', 角: 'j', 色: 's', 权: 'q', 限: 'x', 菜: 'c',
  单: 'd', 系: 'x', 统: 't', 设: 's', 置: 'z', 运: 'y', 行: 'x', 状: 'z',
  态: 't', 登: 'd', 录: 'l', 日: 'r', 志: 'z', 操: 'c', 作: 'z', 定: 'd',
  时: 's', 任: 'r', 务: 'w', 插: 'c', 件: 'j', 更: 'g', 新: 'x', 工: 'g',
  台: 't', 偏: 'p', 好: 'h', 文: 'w', 公: 'g', 告: 'g', 选: 'x', 项: 'x',
  数: 's', 据: 'j', 看: 'k', 板: 'b', 帮: 'b', 助: 'z', 中: 'z', 心: 'x',
  审: 's', 批: 'p', 维: 'w', 护: 'h', 搜: 's', 索: 's', 本: 'b', 基: 'j',
  安: 'a', 全: 'q', 水: 's', 印: 'y', 通: 't', 知: 'z', 消: 'x', 息: 'x',
};

export function pinyinInitials(text: string): string {
  return [...text]
    .map((ch) => PINYIN_INITIAL[ch] ?? (/[a-z0-9]/i.test(ch) ? ch.toLowerCase() : ''))
    .join('');
}

export interface MenuSearchHit {
  key: string;
  title: string;
  /** 所在分组名（无分组时为 null）。 */
  groupTitle: string | null;
  path: string;
}

/**
 * 功能分组名：与后端 SearchService::PAGES 的分组口径一致。
 * 本地菜单兜底要给出和 /search 相同的副标题（「组织与用户 › 用户管理」），
 * 导航树的分组（系统）不是功能分组。
 */
const FUNCTIONAL_GROUP: Record<string, string> = {
  'system:user': '组织与用户',
  'system:dept': '组织与用户',
  'system:post': '组织与用户',
  'system:role': '组织与用户',
  'system:dict': '内容',
  'system:announcement': '内容',
};

function functionalGroup(key: string, navGroupTitle: string | null): string | null {
  if (FUNCTIONAL_GROUP[key]) return FUNCTIONAL_GROUP[key];
  if (key.startsWith('plugin:')) return '插件';
  return navGroupTitle;
}

/**
 * 拉平 /me/menus 的可点击页面节点（已按权限与可见性过滤过）。
 * link 类型走外链，不参与站内搜索。
 */
export function flattenMenuPages(nodes: MyMenuNode[]): MenuSearchHit[] {
  const out: MenuSearchHit[] = [];
  const walk = (items: MyMenuNode[], navGroupTitle: string | null) => {
    for (const item of items) {
      if (item.children && item.children.length > 0) {
        walk(item.children, item.title);
        continue;
      }
      if (item.type === 'page' && item.path) {
        out.push({ key: item.key, title: item.title, groupTitle: functionalGroup(item.key, navGroupTitle), path: item.path });
      }
    }
  };
  walk(nodes, null);
  return out;
}

/** 搜功能：标题/分组名包含，或标题拼音首字母前缀匹配（「yhgl」→「用户管理」）。 */
export function matchMenuPages(hits: MenuSearchHit[], keyword: string): MenuSearchHit[] {
  const kw = keyword.trim().toLowerCase();
  if (!kw) return [];
  return hits.filter(
    (h) =>
      h.title.toLowerCase().includes(kw) ||
      (h.groupTitle ?? '').toLowerCase().includes(kw) ||
      pinyinInitials(h.title).startsWith(kw),
  );
}
