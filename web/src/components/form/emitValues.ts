import type { FormFieldConfig } from './FieldItem';

/**
 * 提交值规整：dateRange + rangeKeys 的字段把 [from,to] 拆成两个键，
 * 其余字段原样保留（FileObject 等非查询值由调用方自取）。
 */
export function emitValues(
  fields: FormFieldConfig[],
  values: Record<string, unknown>,
): Record<string, unknown> {
  const out: Record<string, unknown> = { ...values };
  for (const config of fields) {
    if (config.type !== 'date' || !config.rangeKeys) {
      continue;
    }
    const range = out[config.field];
    delete out[config.field];
    const [from, to] = Array.isArray(range) ? range : [undefined, undefined];
    if (from !== undefined && from !== null && from !== '') {
      out[config.rangeKeys.from] = from;
    }
    if (to !== undefined && to !== null && to !== '') {
      out[config.rangeKeys.to] = to;
    }
  }
  return out;
}
