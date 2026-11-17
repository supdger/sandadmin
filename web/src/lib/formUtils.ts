import type { RuleItem } from '@douyinfe/semi-ui/lib/es/form/interface';

import i18n from '@/i18n/i18n';
import { isAdminApiError, type AdminQuery, type AdminQueryValue } from './adminClient';

/** v4 信封 fields 的形状：字段名 → 消息数组（snake_case 原样）。 */
export type FieldErrorMap = Record<string, string[]>;

/** Semi FormApi 里回显错误用到的最小接口。 */
export interface FieldErrorSetter {
  setError: (field: string, error: unknown) => void;
  getFieldExist?: (field: string) => boolean;
}

/** 从提交错误取字段错误表；非 AdminApiError 或没有 fields 时返回 null。 */
export function fieldErrorsOf(error: unknown): FieldErrorMap | null {
  if (!isAdminApiError(error) || !error.fields) {
    return null;
  }
  const map: FieldErrorMap = {};
  for (const [field, messages] of Object.entries(error.fields)) {
    const clean = messages.filter((m): m is string => typeof m === 'string' && m !== '');
    if (clean.length > 0) {
      map[field] = clean;
    }
  }
  return Object.keys(map).length > 0 ? map : null;
}

/**
 * 信封字段错误写入 Semi 字段（setError）。表单里没有的字段跳过；
 * 返回是否有字段被写入——调用方据此决定还要不要弹整体提示。
 */
export function applyFieldErrors(api: FieldErrorSetter, error: unknown): boolean {
  const map = fieldErrorsOf(error);
  if (!map) {
    return false;
  }
  let applied = false;
  for (const [field, messages] of Object.entries(map)) {
    if (api.getFieldExist && !api.getFieldExist(field)) {
      continue;
    }
    api.setError(field, messages);
    applied = true;
  }
  return applied;
}

/** 必填规则；message 缺省走 feForm 语言包。 */
export function requiredRule(message?: string): RuleItem {
  return { required: true, message: message ?? i18n.t('feForm:validation.required') };
}

export interface FieldRuleSource {
  required?: boolean;
  rules?: RuleItem[];
}

/** 字段配置 → Semi rules：required 排最前，其余原样跟上。 */
export function buildFieldRules(source: FieldRuleSource, requiredMessage?: string): RuleItem[] {
  const rules: RuleItem[] = [];
  if (source.required) {
    rules.push(requiredRule(requiredMessage));
  }
  if (source.rules) {
    rules.push(...source.rules);
  }
  return rules;
}

/** 查询场景的空值口径：'' / null / undefined / 空数组。0 和 false 是有效值。 */
export function isEmptyValue(value: unknown): boolean {
  return (
    value === undefined ||
    value === null ||
    value === '' ||
    (Array.isArray(value) && value.length === 0)
  );
}

function toQueryValue(value: unknown): AdminQueryValue | AdminQueryValue[] | undefined {
  if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
    return value;
  }
  if (Array.isArray(value)) {
    const items = value.filter(
      (item): item is string | number | boolean =>
        typeof item === 'string' || typeof item === 'number' || typeof item === 'boolean',
    );
    return items.length > 0 ? items : undefined;
  }
  return undefined;
}

/** 表单值 → 接口查询参数：丢空值，可序列化的标量/数组原样保留。 */
export function compactValues(values: Record<string, unknown>): AdminQuery {
  const query: AdminQuery = {};
  for (const [key, value] of Object.entries(values)) {
    if (isEmptyValue(value)) {
      continue;
    }
    const out = toQueryValue(value);
    if (out !== undefined) {
      query[key] = out;
    }
  }
  return query;
}
