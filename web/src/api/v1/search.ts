import { adminGet } from '@/lib/adminClient';
import type { Id } from './types';

export interface SearchItem {
  id: Id;
  title: string;
  subtitle: string | null;
  link: string;
}

/** GET /search 的分组；type=menu 由本地菜单兜底，type=user 用接口。 */
export interface SearchGroup {
  type: string;
  label: string;
  items: SearchItem[];
}

export function searchAdmin(
  q: string,
  types?: string[],
): Promise<{ groups: SearchGroup[] }> {
  return adminGet<{ groups: SearchGroup[] }>('/search', {
    query: { q, types: types?.join(',') },
  });
}
