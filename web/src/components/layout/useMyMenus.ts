import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { getMyMenus, MY_MENUS_QUERY_KEY, type MyMenuTree } from '@/api/v1/me';

/** 布局包共用的 /me/menus 查询；与 AppShell 同一 queryKey，缓存共享。 */
export function useMyMenus(): UseQueryResult<MyMenuTree> {
  return useQuery({ queryKey: MY_MENUS_QUERY_KEY, queryFn: getMyMenus, staleTime: 60_000 });
}
