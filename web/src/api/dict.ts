import { apiDelete, apiGet, apiPost, apiPut } from '@/lib/apiClient';

export interface DictType {
  id: string;
  name: string;
  /** 系统自动生成的组标识，接口返回但界面不展示。 */
  code: string;
  remark: string | null;
  is_system: boolean;
  item_count: number;
}

export interface DictTypeDetail extends DictType {
  items: DictItem[];
}

export interface DictItem {
  id: string;
  type_id: string;
  label: string;
  /** 系统自动生成的存储值，界面不展示。 */
  value: string;
  color: string | null;
  is_default: boolean;
  is_system: boolean;
  sort: number;
  status: 'enabled' | 'disabled';
  /** 是否已有业务数据引用；null 表示查不了，按已使用处理。 */
  used: boolean | null;
  create_time: string;
}

export interface DictTypeWrite {
  name: string;
  remark?: string | null;
}

export interface DictItemWrite {
  label: string;
  color?: string | null;
  is_default?: boolean;
  status?: 'enabled' | 'disabled';
}

export function listDictTypes(keyword?: string): Promise<{ items: DictType[] }> {
  const query = keyword?.trim() ? `?keyword=${encodeURIComponent(keyword.trim())}` : '';
  return apiGet<{ items: DictType[] }>(`/dicts${query}`);
}

export function getDictType(typeId: string): Promise<DictTypeDetail> {
  return apiGet<DictTypeDetail>(`/dicts/${encodeURIComponent(typeId)}`);
}

export function createDictType(body: DictTypeWrite): Promise<DictType> {
  return apiPost<DictType>('/dicts', body);
}

export function updateDictType(typeId: string, body: DictTypeWrite): Promise<DictType> {
  return apiPut<DictType>(`/dicts/${encodeURIComponent(typeId)}`, body);
}

export function deleteDictType(typeId: string): Promise<unknown> {
  return apiDelete(`/dicts/${encodeURIComponent(typeId)}`);
}

export function createDictItem(typeId: string, body: DictItemWrite): Promise<DictItem> {
  return apiPost<DictItem>(`/dicts/${encodeURIComponent(typeId)}/items`, body);
}

export function updateDictItem(itemId: string, body: DictItemWrite): Promise<DictItem> {
  return apiPut<DictItem>(`/dicts/items/${encodeURIComponent(itemId)}`, body);
}

export function deleteDictItem(itemId: string): Promise<unknown> {
  return apiDelete(`/dicts/items/${encodeURIComponent(itemId)}`);
}

export function updateDictItemStatus(
  itemId: string,
  status: 'enabled' | 'disabled',
): Promise<unknown> {
  return apiPut(`/dicts/items/${encodeURIComponent(itemId)}/status`, { status });
}

export function sortDictItems(typeId: string, itemIds: string[]): Promise<unknown> {
  return apiPut('/dicts/items/sort', { type_id: typeId, item_ids: itemIds });
}
