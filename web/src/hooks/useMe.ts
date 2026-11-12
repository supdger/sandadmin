import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { getMe } from '@/api/v1/me';
import type { User } from '@/api/v1/users';

export const ME_QUERY_KEY = ['me'] as const;

export function useMe(): UseQueryResult<User> {
  return useQuery({ queryKey: ME_QUERY_KEY, queryFn: getMe, staleTime: 60_000, retry: 1 });
}
