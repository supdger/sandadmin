/**
 * 接口把列表字段留空、或根本不给这个字段时，页面仍要画出空状态。
 * 对缺省值调用 filter / length 会把整页打崩。
 */
export function listOrEmpty<T>(value: T[] | null | undefined): T[] {
  return Array.isArray(value) ? value : [];
}
