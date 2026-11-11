import { apiGet } from '@/lib/apiClient';

export interface DeptOption {
  id: string;
  parent_id: string | null;
  name: string;
  sort: number;
}

export interface DeptOptions {
  items: DeptOption[];
}

/** 精简部门清单（已登录可用）；返回平铺列表，调用方按 parent_id 组树。 */
export function getDeptOptions(): Promise<DeptOptions> {
  return apiGet<DeptOptions>('/org/dept-options');
}
