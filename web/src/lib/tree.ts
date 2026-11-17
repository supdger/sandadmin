/**
 * 树形数据的纯函数：扁平列表组装成树、树展平回列表。
 * 接口字段各不相同，id/parentId 用取值函数传入，
 * 不在本文件写死字段名。
 */

export type TreeItem<T> = T & { children: TreeItem<T>[] };

export interface TreeOptions<T> {
  id: (item: T) => string;
  /** 根节点的父级取值约定：null / undefined / 不在列表里。 */
  parentId: (item: T) => string | null | undefined;
}

/**
 * 扁平列表 → 树。原数组不被修改；parent 不在列表里的节点
 * 挂在根层（孤儿不当根失败处理，菜单脏数据也要能显示）。
 * 兄弟顺序保持原数组顺序。
 */
export function buildTree<T>(items: T[], options: TreeOptions<T>): TreeItem<T>[] {
  const nodes = new Map<string, TreeItem<T>>();
  for (const item of items) {
    nodes.set(options.id(item), { ...item, children: [] });
  }

  const roots: TreeItem<T>[] = [];
  for (const item of items) {
    const node = nodes.get(options.id(item)) as TreeItem<T>;
    const parentId = options.parentId(item);
    const parent = parentId ? nodes.get(parentId) : undefined;
    if (parent) {
      parent.children.push(node);
    } else {
      roots.push(node);
    }
  }
  return roots;
}

/** 树 → 深度优先扁平列表；children 字段保留原样。 */
export function flattenTree<T>(tree: TreeItem<T>[]): TreeItem<T>[] {
  const out: TreeItem<T>[] = [];
  const walk = (nodes: TreeItem<T>[]): void => {
    for (const node of nodes) {
      out.push(node);
      walk(node.children);
    }
  };
  walk(tree);
  return out;
}
