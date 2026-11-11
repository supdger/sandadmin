import { useCallback, useMemo } from 'react';
import { useSearchParams } from 'react-router';

import {
  buildUrlQuery,
  parseUrlQuery,
  type UrlQueryFields,
  type UrlQueryValues,
} from '@/lib/urlQuery';

export type { UrlQueryFields, UrlQueryValues } from '@/lib/urlQuery';

export interface UrlQueryResult {
  values: UrlQueryValues;
  /**
   * 把部分更新合并进 URL。默认 `replace`，避免筛选改动刷爆历史栈；
   * 需要用户可回退的翻页等场景传 `{ replace: false }`。
   */
  setValues: (patch: UrlQueryValues, options?: { replace?: boolean }) => void;
}

/**
 * 把列表页的筛选/排序/分页状态放进地址栏。参数只声明一次
 * （snake_case 键与接口查询参数同名）；缺省参数读作 `undefined`，
 * `defaultValue` 兜底且不进 URL。
 */
export function useUrlQuery(fields: UrlQueryFields): UrlQueryResult {
  const [searchParams, setSearchParams] = useSearchParams();

  const values = useMemo(
    () => parseUrlQuery(searchParams.toString(), fields),
    [searchParams, fields],
  );

  const setValues = useCallback(
    (patch: UrlQueryValues, options?: { replace?: boolean }) => {
      const next = buildUrlQuery(searchParams, fields, values, patch);
      setSearchParams(next, { replace: options?.replace ?? true });
    },
    [searchParams, setSearchParams, fields, values],
  );

  return { values, setValues };
}
