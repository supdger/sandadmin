import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { getMe, type CurrentUser } from '@/api/auth';

export const ME_QUERY_KEY = ['me'] as const;

export function useMe(): UseQueryResult<CurrentUser> {
  return useQuery({ queryKey: ME_QUERY_KEY, queryFn: getMe, staleTime: 60_000, retry: 1 });
}
