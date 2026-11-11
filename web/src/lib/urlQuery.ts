/**
 * `useUrlQuery` 背后的纯函数：从查询串解析已声明参数，
 * 把补丁合并回 URLSearchParams。键保持 snake_case——
 * 它们就是契约里的查询参数名。
 */

export type UrlQueryKind = 'string' | 'number' | 'boolean';

export interface UrlQueryField {
  /** 值类型；默认 'string'。 */
  kind?: UrlQueryKind;
  /** 值等于它时从 URL 省略（默认值不进地址栏）。 */
  defaultValue?: string | number | boolean;
}

export type UrlQueryFields = Record<string, UrlQueryField>;
export type UrlQueryValues = Record<string, string | number | boolean | undefined>;

function parseOne(raw: string | null, field: UrlQueryField): string | number | boolean | undefined {
  const kind = field.kind ?? 'string';
  if (raw === null) {
    return field.defaultValue;
  }
  if (kind === 'number') {
    const num = Number(raw);
    return Number.isFinite(num) ? num : field.defaultValue;
  }
  if (kind === 'boolean') {
    return raw === 'true' || raw === '1';
  }
  return raw === '' ? field.defaultValue : raw;
}

export function parseUrlQuery(search: string, fields: UrlQueryFields): UrlQueryValues {
  const params = new URLSearchParams(search);
  const values: UrlQueryValues = {};
  for (const [key, field] of Object.entries(fields)) {
    values[key] = parseOne(params.get(key), field);
  }
  return values;
}

function isEmptyValue(value: unknown): boolean {
  return value === undefined || value === null || value === '';
}

/**
 * 在 `current` 基础上，把 `fields` 声明的参数叠加 `values` 与 `patch`。
 * 空值和等于字段默认值的项被移除，URL 只携带非默认状态；
 * 未声明的参数原样透传。
 */
export function buildUrlQuery(
  current: URLSearchParams,
  fields: UrlQueryFields,
  values: UrlQueryValues,
  patch: UrlQueryValues,
): URLSearchParams {
  const next = new URLSearchParams(current);
  for (const key of Object.keys(fields)) {
    const value = key in patch ? patch[key] : values[key];
    const field = fields[key];
    if (isEmptyValue(value) || value === field.defaultValue) {
      next.delete(key);
      continue;
    }
    next.set(key, String(value));
  }
  return next;
}
