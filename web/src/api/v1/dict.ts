import { adminDelete, adminGet, adminPost, adminPut, type AdminQuery } from '@/lib/adminClient';
import type { Id, UserStatus } from './types';

/** 选项组来源：系统自带 / 插件提供 / 用户自建；plugin 时带插件中文名。 */
export interface DictTypeSource {
  kind: 'system' | 'plugin' | 'custom';
  plugin_label: string | null;
}

export interface DictType {
  id: Id;
  name: string;
  /** 系统自动生成的组标识，接口返回但界面不展示。 */
  code: string;
  remark: string | null;
  is_system: boolean;
  extension_key: string | null;
  item_count: number;
  /** 老接口可能不带该字段，缺省时前端按 is_system 推导。 */
  source?: DictTypeSource;
}

export interface DictTypeDetail extends DictType {
  items: DictItem[];
}

export interface DictItem {
  id: Id;
  type_id: Id;
  label: string;
  /** 系统自动生成的存储值，界面不展示。 */
  value: string;
  color: string | null;
  is_default: boolean;
  is_system: boolean;
  sort: number;
  status: UserStatus;
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
  status?: UserStatus;
}

export interface DictTypesQuery extends AdminQuery {
  keyword?: string;
}

export function listDictTypes(query?: DictTypesQuery): Promise<{ items: DictType[] }> {
  return adminGet<{ items: DictType[] }>('/dicts', { query });
}

export function getDictType(typeId: Id): Promise<DictTypeDetail> {
  return adminGet<DictTypeDetail>(`/dicts/${encodeURIComponent(typeId)}`);
}

export function createDictType(body: DictTypeWrite, idempotencyKey?: string): Promise<DictType> {
  return adminPost<DictType>('/dicts', body, { idempotencyKey });
}

export function updateDictType(typeId: Id, body: DictTypeWrite): Promise<DictType> {
  return adminPut<DictType>(`/dicts/${encodeURIComponent(typeId)}`, body);
}

export function deleteDictType(typeId: Id): Promise<unknown> {
  return adminDelete(`/dicts/${encodeURIComponent(typeId)}`);
}

export function createDictItem(typeId: Id, body: DictItemWrite, idempotencyKey?: string): Promise<DictItem> {
  return adminPost<DictItem>(`/dicts/${encodeURIComponent(typeId)}/items`, body, { idempotencyKey });
}

export function updateDictItem(itemId: Id, body: DictItemWrite): Promise<DictItem> {
  return adminPut<DictItem>(`/dicts/items/${encodeURIComponent(itemId)}`, body);
}

export function deleteDictItem(itemId: Id): Promise<unknown> {
  return adminDelete(`/dicts/items/${encodeURIComponent(itemId)}`);
}

export function updateDictItemStatus(itemId: Id, status: UserStatus): Promise<unknown> {
  return adminPut(`/dicts/items/${encodeURIComponent(itemId)}/status`, { status });
}

export function sortDictItems(typeId: Id, itemIds: Id[]): Promise<unknown> {
  return adminPut('/dicts/items/sort', { type_id: typeId, item_ids: itemIds });
}
