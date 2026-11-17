import { useMemo } from 'react';

import { useQuery } from '@tanstack/react-query';

import { DICT_QUERY_KEY, getDictType, listDictTypes, type DictItem } from '@/api/v1/dict';
import type { Id } from '@/api/v1/types';

/** 给选择控件用的字典项视图；取数与缓存见 useDict。 */
export interface DictOption {
  label: string;
  value: string;
  /** 字典配置的颜色值（十六进制等原样字符串），无配置为 null。 */
  color: string | null;
}

export interface UseDictResult {
  /** 已启用、按 sort 排好的原始项。 */
  items: DictItem[];
  options: DictOption[];
  /** 按存储值取展示标签；查不到返回空串（页面决定兜底）。 */
  labelOf: (value: string) => string;
  optionOf: (value: string) => DictOption | undefined;
  isPending: boolean;
  isError: boolean;
  refetch: () => void;
}

function toOption(item: DictItem): DictOption {
  return { label: item.label, value: item.value, color: item.color };
}

function pickItems(detail: { items: DictItem[] } | undefined): DictItem[] {
  return (detail?.items ?? [])
    .filter((item) => item.status === 'enabled')
    .slice()
    .sort((a, b) => a.sort - b.sort);
}

/** 按选项组 id 取字典项；typeId 为空不发请求，展示集为空。 */
export function useDict(typeId: Id | null | undefined): UseDictResult {
  const query = useQuery({
    queryKey: [...DICT_QUERY_KEY, 'type', typeId],
    queryFn: () => getDictType(typeId as Id),
    enabled: Boolean(typeId),
    staleTime: 60_000,
  });

  const items = useMemo(() => pickItems(query.data), [query.data]);
  const options = useMemo(() => items.map(toOption), [items]);
  const optionOf = useMemo(
    () => (value: string) => options.find((option) => option.value === value),
    [options],
  );

  return {
    items,
    options,
    optionOf,
    labelOf: (value: string) => optionOf(value)?.label ?? '',
    isPending: Boolean(typeId) && query.isPending,
    isError: query.isError,
    refetch: () => void query.refetch(),
  };
}

/**
 * 按选项组 code 取字典：先查选项组列表定位 type_id，再取明细。
 * 消费方只知道 `dict_type` 编码时用；结果与 useDict 同形。
 */
export function useDictByCode(code: string | null | undefined): UseDictResult {
  const types = useQuery({
    queryKey: [...DICT_QUERY_KEY, 'types'],
    queryFn: () => listDictTypes(),
    enabled: Boolean(code),
    staleTime: 60_000,
  });

  const typeId = code ? types.data?.items.find((type) => type.code === code)?.id : undefined;
  const result = useDict(typeId ?? null);

  return {
    ...result,
    isPending: Boolean(code) && (types.isPending || result.isPending),
    isError: types.isError || result.isError,
  };
}
