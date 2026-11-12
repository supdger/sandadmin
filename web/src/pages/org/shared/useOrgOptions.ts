import { useQuery } from '@tanstack/react-query';

import { getMe } from '@/api/v1/me';
import { getDeptOptions } from '@/api/v1/org';
import { listPosts } from '@/api/v1/posts';
import { listRoles } from '@/api/v1/roles';
import { getDeptTree } from '@/api/v1/depts';
import type { Dept } from '@/api/v1/depts';
import type { Id, UserStatus } from '@/api/v1/types';

import { deptOptionsToTree, deptTreeToOptions, type DeptTreeOption } from './deptTree';

/** v1 /me：拿当前登录人 id（隐藏「停用/删除自己」）和姓名。 */
export function useOrgMe() {
  return useQuery({
    queryKey: ['v1', 'me'],
    queryFn: getMe,
    staleTime: 60_000,
    retry: 1,
  });
}

/** 角色清单：用户页角色列、筛选和表单多选共用。失败时调用方降级。 */
export function useRoleOptions(status?: UserStatus) {
  return useQuery({
    queryKey: ['v1', 'roles', 'options', status ?? 'all'],
    queryFn: () => listRoles({ status, page_size: 100 }),
    staleTime: 60_000,
    retry: 1,
  });
}

/** 已启用岗位清单（用户表单/筛选只列可用岗位）。 */
export function useEnabledPostOptions() {
  return useQuery({
    queryKey: ['v1', 'posts', 'options'],
    queryFn: () => listPosts({ status: 'enabled', page_size: 100 }),
    staleTime: 60_000,
    retry: 1,
  });
}

export interface OrgDeptTree {
  /** 部门管理页用的原始树（含停用）。 */
  tree: Dept[];
  /** 转成 TreeSelect/Tree 可用形状的选项。 */
  options: DeptTreeOption[];
}

/**
 * 用户页左侧部门树：优先 /depts/tree（含停用部门），失败时回退
 * /org/dept-options（任何登录用户可用，但只有启用部门）。
 */
export function useUserDeptTree() {
  const treeQuery = useQuery({
    queryKey: ['v1', 'depts', 'tree', 'all'],
    queryFn: () => getDeptTree(),
    staleTime: 60_000,
    retry: 1,
  });
  const fallbackQuery = useQuery({
    queryKey: ['v1', 'org', 'dept-options'],
    queryFn: getDeptOptions,
    enabled: treeQuery.isError,
    staleTime: 60_000,
    retry: 1,
  });

  if (treeQuery.data) {
    const tree = treeQuery.data.items;
    return {
      data: { tree, options: deptTreeToOptions(tree) } satisfies OrgDeptTree,
      isPending: treeQuery.isPending,
      isError: treeQuery.isError && fallbackQuery.isError,
      refetch: treeQuery.refetch,
    };
  }
  if (fallbackQuery.data) {
    const options = deptOptionsToTree(fallbackQuery.data.items);
    return {
      data: { tree: [], options } satisfies OrgDeptTree,
      isPending: false,
      isError: false,
      refetch: fallbackQuery.refetch,
    };
  }
  return {
    data: undefined as OrgDeptTree | undefined,
    isPending: treeQuery.isPending || fallbackQuery.isPending,
    isError: treeQuery.isError && fallbackQuery.isError,
    refetch: treeQuery.refetch,
  };
}

export type { Id };
