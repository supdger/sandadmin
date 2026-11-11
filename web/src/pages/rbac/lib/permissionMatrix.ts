import type { PermissionAction } from '@/api/v1/permissions';

/**
 * 角色权限矩阵的纯函数：查看动作联动与可授权权限判断。
 * `permission_codes` 始终是扁平的码列表；树只决定哪些勾选框亮起。
 */

/** 该行的「查看」锚点：index 动作，找不到时回退到 label 为「查看」的动作。 */
export function viewActionOf(actions: PermissionAction[]): PermissionAction | undefined {
  return (
    actions.find((a) => a.action === 'index') ??
    actions.find((a) => a.label === '查看')
  );
}

/**
 * 对权限码集合应用一次勾选切换，遵守联动规则：
 * 勾选任一非查看动作会连带勾上查看动作；取消查看动作会清掉
 * 同一菜单行的其余所有动作。
 */
export function togglePermissionCode(
  selected: ReadonlySet<string>,
  action: PermissionAction,
  siblings: PermissionAction[],
): Set<string> {
  const next = new Set(selected);
  const view = viewActionOf(siblings);
  const isView = view !== undefined && action.code === view.code;

  if (next.has(action.code)) {
    next.delete(action.code);
    if (isView) {
      for (const sibling of siblings) {
        if (sibling.code !== action.code) {
          next.delete(sibling.code);
        }
      }
    }
    return next;
  }

  next.add(action.code);
  if (!isView && view) {
    next.add(view.code);
  }
  return next;
}

/** 当前用户可授权的菜单行全部权限码。 */
export function ownableCodes(
  actions: PermissionAction[],
  granted: ReadonlySet<string> | readonly string[] | '*',
): string[] {
  if (granted === '*') {
    return actions.map((a) => a.code);
  }
  const set = granted instanceof Set ? granted : new Set(granted);
  return actions.filter((a) => set.has(a.code)).map((a) => a.code);
}

/** 分组「全选这一组」勾选框在可授权码上的三态。 */
export function groupCheckState(
  ownable: string[],
  selected: ReadonlySet<string>,
): 'checked' | 'unchecked' | 'indeterminate' {
  if (ownable.length === 0) {
    return 'unchecked';
  }
  const hit = ownable.filter((code) => selected.has(code)).length;
  if (hit === 0) {
    return 'unchecked';
  }
  return hit === ownable.length ? 'checked' : 'indeterminate';
}

/** 整组加入或移除全部可授权码（联动已由勾选动作满足）。 */
export function toggleGroupCodes(
  selected: ReadonlySet<string>,
  ownable: string[],
  check: boolean,
): Set<string> {
  const next = new Set(selected);
  for (const code of ownable) {
    if (check) {
      next.add(code);
    } else {
      next.delete(code);
    }
  }
  return next;
}
