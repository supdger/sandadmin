import { useCallback, useState } from 'react';

import { useQuery } from '@tanstack/react-query';

import type { Page } from '@/api/v1/types';
import type { AdminQuery } from '@/lib/adminClient';

/** 排序状态与 Semi Table sortOrder 同取值，可直接互相映射。 */
export interface TableSorter {
  field: string;
  order: 'ascend' | 'descend';
}

/** 表格查询状态；filters 原样并入接口查询参数（snake_case）。 */
export interface TableQueryState {
  page: number;
  pageSize: number;
  sorter: TableSorter | null;
  filters: AdminQuery;
}

export interface UseTableOptions<T> {
  /** react-query 缓存命名空间，如 ['v1', 'users']；查询状态会自动拼在后面。 */
  queryKey: readonly unknown[];
  /** 调用方把查询状态映射成接口参数并返回契约分页。 */
  fetcher: (query: TableQueryState) => Promise<Page<T>>;
  /** 初始每页条数，默认 20。 */
  pageSize?: number;
  initialFilters?: AdminQuery;
}

export interface UseTableResult<T> {
  rows: T[];
  total: number;
  /** 首屏加载中（无旧数据时）；翻页/刷新看 fetching。 */
  loading: boolean;
  fetching: boolean;
  error: unknown;
  query: TableQueryState;
  /** 合并 patch；sorter/filters 变化且 patch 未带 page 时回到第一页，并清空选择。 */
  setQuery: (patch: Partial<TableQueryState>) => void;
  /** 清空筛选回第一页，排序保留。 */
  resetFilters: () => void;
  /** 重新取当前页；勾选清空。 */
  refresh: () => void;
  selectedKeys: (string | number)[];
  setSelectedKeys: (keys: (string | number)[]) => void;
  clearSelection: () => void;
}

/**
 * 列表取数状态机：分页/排序/筛选进 query 状态，react-query 按状态取数；
 * 查询条件任何变化都清空行选择，避免对已不在列表里的行做批量操作。
 */
export function useTable<T>(options: UseTableOptions<T>): UseTableResult<T> {
  const [query, setState] = useState<TableQueryState>(() => ({
    page: 1,
    pageSize: options.pageSize ?? 20,
    sorter: null,
    filters: options.initialFilters ?? {},
  }));
  const [selectedKeys, setSelectedKeys] = useState<(string | number)[]>([]);

  const queryResult = useQuery({
    queryKey: [...options.queryKey, query],
    queryFn: () => options.fetcher(query),
    placeholderData: (previous) => previous,
  });

  const setQuery = useCallback((patch: Partial<TableQueryState>) => {
    setState((prev) => {
      const next = { ...prev, ...patch };
      if (patch.page === undefined && (patch.filters !== undefined || patch.sorter !== undefined)) {
        next.page = 1;
      }
      return next;
    });
    setSelectedKeys([]);
  }, []);

  const resetFilters = useCallback(() => {
    setState((prev) => ({ ...prev, filters: {}, page: 1 }));
    setSelectedKeys([]);
  }, []);

  const refresh = useCallback(() => {
    setSelectedKeys([]);
    void queryResult.refetch();
  }, [queryResult]);

  const clearSelection = useCallback(() => setSelectedKeys([]), []);

  return {
    rows: queryResult.data?.items ?? [],
    total: queryResult.data?.total ?? 0,
    loading: queryResult.isPending,
    fetching: queryResult.isFetching,
    error: queryResult.error,
    query,
    setQuery,
    resetFilters,
    refresh,
    selectedKeys,
    setSelectedKeys,
    clearSelection,
  };
}
