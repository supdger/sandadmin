import type { Dept } from '@/api/v1/depts';
import type { DeptOption } from '@/api/v1/org';
import type { Id } from '@/api/v1/types';

/** TreeSelect 需要的节点形状（Semi TreeSelect 用 children 递归）。 */
export interface DeptTreeOption {
  value: Id;
  label: string;
  /** 停用部门：表单里置灰不可选，筛选场景仍可用。 */
  disabled?: boolean;
  children?: DeptTreeOption[];
}

interface FlatDeptLike {
  id: Id;
  parent_id: Id | null;
  name: string;
  sort: number;
  status?: string;
}

/** 平铺部门（含停用）→ 按 parent_id/sort 组树，供 TreeSelect 与左侧树共用。 */
export function buildDeptTreeOptions(depts: FlatDeptLike[]): DeptTreeOption[] {
  const byId = new Map<Id, DeptTreeOption>();
  for (const dept of depts) {
    byId.set(dept.id, {
      value: dept.id,
      label: dept.name,
      disabled: dept.status === 'disabled',
      children: [],
    });
  }
  const roots: DeptTreeOption[] = [];
  for (const dept of depts) {
    const node = byId.get(dept.id)!;
    const parent = dept.parent_id ? byId.get(dept.parent_id) : undefined;
    if (parent) {
      parent.children!.push(node);
    } else {
      roots.push(node);
    }
  }
  const sortNodes = (nodes: DeptTreeOption[]) => {
    nodes.sort((a, b) => {
      const da = depts.find((d) => d.id === a.value)!;
      const db = depts.find((d) => d.id === b.value)!;
      return da.sort - db.sort || a.label.localeCompare(b.label, 'zh-CN');
    });
    for (const node of nodes) {
      if (node.children?.length) {
        sortNodes(node.children);
      }
    }
  };
  sortNodes(roots);
  return roots;
}

/** DeptOption[]（平铺、只含启用）→ 树。 */
export function deptOptionsToTree(items: DeptOption[]): DeptTreeOption[] {
  return buildDeptTreeOptions(items);
}

/** Dept[]（接口树）→ 同形状 DeptTreeOption，供用户页部门筛选。 */
export function deptTreeToOptions(depts: Dept[]): DeptTreeOption[] {
  const flat: FlatDeptLike[] = [];
  const walk = (nodes: Dept[]) => {
    for (const node of nodes) {
      flat.push({
        id: node.id,
        parent_id: node.parent_id,
        name: node.name,
        sort: node.sort,
        status: node.status,
      });
      walk(node.children);
    }
  };
  walk(depts);
  return buildDeptTreeOptions(flat);
}

/** 收集某节点的全部后代 id（编辑部门时禁选自己和下级）。 */
export function collectDescendantIds(nodes: Dept[], rootId: Id): Set<Id> {
  const result = new Set<Id>();
  const walk = (list: Dept[]) => {
    for (const node of list) {
      if (node.id === rootId) {
        const collect = (n: Dept) => {
          for (const child of n.children) {
            result.add(child.id);
            collect(child);
          }
        };
        collect(node);
        return;
      }
      walk(node.children);
    }
  };
  walk(nodes);
  return result;
}

/** 前两层的展开键：根 + 每个根的直接子级。 */
export function defaultExpandedKeys(depts: Dept[]): Id[] {
  const keys: Id[] = [];
  for (const root of depts) {
    keys.push(root.id);
    for (const child of root.children) {
      keys.push(child.id);
    }
  }
  return keys;
}

export interface FlatDeptRow {
  dept: Dept;
  depth: number;
}

/** 树 → 深度优先平铺行（表格行顺序）。 */
export function flattenDeptRows(depts: Dept[]): FlatDeptRow[] {
  const rows: FlatDeptRow[] = [];
  const walk = (nodes: Dept[], depth: number) => {
    for (const node of nodes) {
      rows.push({ dept: node, depth });
      walk(node.children, depth + 1);
    }
  };
  walk(depts, 0);
  return rows;
}

/** 按 id 在树里找节点。 */
export function findDeptNode(depts: Dept[], id: Id): Dept | undefined {
  for (const node of depts) {
    if (node.id === id) {
      return node;
    }
    const hit = findDeptNode(node.children, id);
    if (hit) {
      return hit;
    }
  }
  return undefined;
}

/** 拖放目标行成为新上级时，新 sort = 目标现有子级最大 sort + 1（无子级为 1）。 */
export function nextChildSort(target: Dept, movingId: Id): number {
  const siblings = target.children.filter((c) => c.id !== movingId);
  if (siblings.length === 0) {
    return 1;
  }
  return Math.max(...siblings.map((c) => c.sort)) + 1;
}

/** 顶层追加时的新 sort = 顶层现有最大 sort + 1。 */
export function nextRootSort(roots: Dept[], movingId: Id): number {
  const siblings = roots.filter((d) => d.id !== movingId);
  if (siblings.length === 0) {
    return 1;
  }
  return Math.max(...siblings.map((d) => d.sort)) + 1;
}

/** 判断 targetId 是否为 deptId 的后代（含自身不算，调用方单独处理）。 */
export function isDescendantOf(depts: Dept[], deptId: Id, targetId: Id): boolean {
  const root = findDeptNode(depts, deptId);
  if (!root) {
    return false;
  }
  const ids = collectDescendantIds(depts, deptId);
  return ids.has(targetId) || targetId === root.id;
}

function bySort(a: Dept, b: Dept): number {
  return a.sort - b.sort || a.name.localeCompare(b.name, 'zh-CN');
}

/**
 * 拖拽换上级/排序的纯函数：返回移动后的新树（不改原数据）。
 * newParentId 为 null 表示挪到顶层；调用方负责先校验不能落到自己或下级。
 */
export function relocateDept(
  depts: Dept[],
  deptId: Id,
  newParentId: Id | null,
  newSort: number,
): Dept[] {
  const next = structuredClone(depts);
  const node = findDeptNode(next, deptId);
  if (!node) {
    return depts;
  }
  const oldParent = node.parent_id ? findDeptNode(next, node.parent_id) : undefined;
  if (oldParent) {
    oldParent.children = oldParent.children.filter((c) => c.id !== deptId);
  } else {
    const idx = next.findIndex((d) => d.id === deptId);
    if (idx >= 0) {
      next.splice(idx, 1);
    }
  }
  node.parent_id = newParentId;
  node.sort = newSort;
  const newParent = newParentId ? findDeptNode(next, newParentId) : undefined;
  if (newParent) {
    newParent.children.push(node);
    newParent.children.sort(bySort);
  } else {
    next.push(node);
    next.sort(bySort);
  }
  return next;
}
