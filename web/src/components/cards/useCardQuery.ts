import { useQuery } from '@tanstack/react-query';

export interface CardQueryResult<T> {
  data: T | undefined;
  pending: boolean;
  error: unknown;
  /** 重新拉取（静态 data 形态下是空操作）。 */
  retry: () => void;
}

/**
 * 卡片族共用的取数开关：给了 data 就以它为准不发请求；
 * 没给 data 且给了 fetcher 时走 react-query 管加载/错误/重试，
 * 此时 queryKey 用于区分不同卡片的缓存，必须给。
 */
export function useCardQuery<T>(shape: {
  data: T | undefined;
  fetcher: (() => Promise<T>) | undefined;
  queryKey: readonly unknown[] | undefined;
}): CardQueryResult<T> {
  const { data, fetcher, queryKey } = shape;
  const query = useQuery<T>({
    queryKey: ['fe-card', ...(queryKey ?? [])],
    queryFn: () => {
      if (!fetcher) {
        // enabled 已挡住这条路径；保底拒绝而不是静默吞错。
        return Promise.reject(new Error('useCardQuery: fetcher missing'));
      }
      return fetcher();
    },
    enabled: data === undefined && fetcher !== undefined,
  });
  const fromProps = data !== undefined;
  return {
    data: fromProps ? data : query.data,
    pending: !fromProps && query.isPending,
    error: !fromProps && query.isError ? query.error : undefined,
    retry: () => {
      void query.refetch();
    },
  };
}
