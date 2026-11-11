import type { MenuNode } from '@/api/v1/menus';

/**
 * 菜单树编辑器的纯函数：为表格拍平行、把 HTML5 拖放目标解析成
 * `/menus/{key}/move` 参数、为「上移/下移」菜单项计算同级位移。
 * 树最多两层——顶层是分组，页面/链接在分组内（顶层页面也允许）。
 */

export interface MenuRowMeta {
  node: MenuNode;
  /** 父分组的 key；顶层行为 null。 */
  parentKey: string | null;
  /** 在同级列表中的位置。 */
  index: number;
  depth: 0 | 1;
}

/** 按渲染表格顺序的深度优先行列表。 */
export function flattenMenuRows(items: MenuNode[]): MenuRowMeta[] {
  const rows: MenuRowMeta[] = [];
  items.forEach((node, index) => {
    rows.push({ node, parentKey: null, index, depth: 0 });
    (node.children ?? []).forEach((child, childIndex) => {
      rows.push({ node: child, parentKey: node.key, index: childIndex, depth: 1 });
    });
  });
  return rows;
}

/** 移动的目标同级列表：分组的子级，或顶层节点。 */
export function menuSiblings(items: MenuNode[], parentKey: string | null): MenuNode[] {
  if (parentKey === null) {
    return items;
  }
  const group = items.find((n) => n.key === parentKey && n.type === 'group');
  return group?.children ?? [];
}

export function findMenuNode(items: MenuNode[], key: string): MenuNode | undefined {
  for (const node of items) {
    if (node.key === key) return node;
    const hit = node.children ? findMenuNode(node.children, key) : undefined;
    if (hit) return hit;
  }
  return undefined;
}

export function findMenuRow(
  items: MenuNode[],
  key: string,
): MenuRowMeta | undefined {
  return flattenMenuRows(items).find((row) => row.node.key === key);
}

export type DropZone = 'before' | 'after' | 'into';

/**
 * 行内的落点区域：上半部插到前面，下半部插到后面；
 * 分组行把底部一条留作「放进这个分组」。
 */
export function dropZoneAt(
  clientY: number,
  rectTop: number,
  rectHeight: number,
  isGroup: boolean,
): DropZone {
  if (isGroup && rectHeight > 0 && clientY > rectTop + rectHeight * 0.75) {
    return 'into';
  }
  const mid = rectTop + rectHeight / 2;
  return clientY < mid ? 'before' : 'after';
}

export type MoveResolution =
  | { ok: true; parent_key: string | null; index: number }
  | { ok: false; reason: 'depth' | 'self' | 'top_locked' | 'noop' };

/**
 * 把一次拖放（或「上移/下移」）换算成 move 参数。`index` 是
 * 移除后的坐标——接口先摘掉节点再插入。
 */
export function resolveDropMove(args: {
  items: MenuNode[];
  dragKey: string;
  targetKey: string;
  zone: DropZone;
}): MoveResolution {
  const { items, dragKey, targetKey, zone } = args;
  const dragRow = findMenuRow(items, dragKey);
  const targetRow = findMenuRow(items, targetKey);
  if (!dragRow || !targetRow) {
    return { ok: false, reason: 'noop' };
  }
  const dragged = dragRow.node;
  if (dragged.locked?.move) {
    return { ok: false, reason: 'noop' };
  }

  // 'into' 只对分组行有效；其他行上退化为 'after'。
  const effectiveZone = zone === 'into' && targetRow.node.type === 'group' ? 'into' : zone === 'into' ? 'after' : zone;
  const targetParentKey = effectiveZone === 'into' ? targetRow.node.key : targetRow.parentKey;

  // 分组只能待在顶层——更深就是第三层。
  if (dragged.type === 'group' && targetParentKey !== null) {
    return { ok: false, reason: 'depth' };
  }
  // 分组不能装下自己（它的子行解析出来的父键就是它自己）。
  if (targetParentKey === dragKey) {
    return { ok: false, reason: 'self' };
  }

  const targetSiblings = menuSiblings(items, targetParentKey);
  let baseIndex: number;
  if (effectiveZone === 'into') {
    baseIndex = targetSiblings.length;
  } else {
    baseIndex = targetRow.index + (effectiveZone === 'after' ? 1 : 0);
    // 'before'/'after' 继承目标行的父级，index 只在目标行
    // 与目标父级处于同一同级列表时才有意义。
    if (targetRow.parentKey !== targetParentKey) {
      baseIndex = targetSiblings.length;
    }
  }

  // 同列表内的摘除会让移除点之后的位置左移一格。
  let index = baseIndex;
  if (dragRow.parentKey === targetParentKey && dragRow.index < baseIndex) {
    index = baseIndex - 1;
  }

  // 「工作台」固定在最前：任何节点都不许插到它上面。
  if (targetParentKey === null && index === 0) {
    const first = items[0];
    if (first?.locked?.move && first.key !== dragKey) {
      return { ok: false, reason: 'top_locked' };
    }
  }

  if (dragRow.parentKey === targetParentKey && index === dragRow.index) {
    return { ok: false, reason: 'noop' };
  }

  return { ok: true, parent_key: targetParentKey, index };
}

/** 在当前同级列表内上移/下移；到边界返回 null。 */
export function siblingMoveIndex(
  items: MenuNode[],
  row: MenuRowMeta,
  direction: -1 | 1,
): { parent_key: string | null; index: number } | null {
  const siblings = menuSiblings(items, row.parentKey);
  const next = row.index + direction;
  if (next < 0 || next >= siblings.length) {
    return null;
  }
  // 顶层固定首行（locked.move，如「工作台」）不允许任何节点占 index 0。
  if (row.parentKey === null && next === 0 && siblings[0]?.locked?.move) {
    return null;
  }
  return { parent_key: row.parentKey, index: next };
}
