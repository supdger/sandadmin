import { useEffect, useMemo, useState, type CSSProperties } from 'react';

import { Select } from '@douyinfe/semi-ui';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import { getUserOptions } from '@/api/v1/org';
import type { Id } from '@/api/v1/types';
import { FIELD_CHOICE_WIDTH } from '@/components/fieldWidth';

export interface UserSelectProps {
  value?: Id | Id[] | null;
  onChange?: (value: Id | Id[] | undefined) => void;
  multiple?: boolean;
  placeholder?: string;
  disabled?: boolean;
  /** 已选值不在当前搜索结果时的 label 表（编辑回显）。 */
  currentLabels?: Record<Id, string>;
  /** 只留启用用户；默认全部可选。 */
  enabledOnly?: boolean;
  excludeIds?: Id[];
  style?: CSSProperties;
}

interface Option {
  value: Id;
  label: string;
}

/** 远程搜索选人 Select：GET /org/user-options?keyword=，输入防抖 300ms。 */
export function UserSelect({
  value,
  onChange,
  multiple,
  placeholder,
  disabled,
  currentLabels,
  enabledOnly,
  excludeIds,
  style,
}: UserSelectProps) {
  const { t } = useTranslation('feForm');
  const [keyword, setKeyword] = useState('');
  const [debounced, setDebounced] = useState('');

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(keyword.trim()), 300);
    return () => clearTimeout(timer);
  }, [keyword]);

  const optionsQuery = useQuery({
    queryKey: ['v1', 'org', 'user-options', debounced],
    queryFn: () => getUserOptions(debounced ? { keyword: debounced } : undefined),
    staleTime: 30_000,
    retry: 1,
  });

  const options = useMemo<Option[]>(() => {
    const excluded = new Set(excludeIds ?? []);
    const list: Option[] = (optionsQuery.data?.items ?? [])
      .filter((user) => !excluded.has(user.id))
      .filter((user) => (enabledOnly ? user.status === 'enabled' : true))
      .map((user) => ({
        value: user.id,
        label: user.dept_name ? `${user.nickname} · ${user.dept_name}` : user.nickname,
      }));
    // 已选值不在搜索结果里时补回，保证回显不丢。
    const selected = value === null || value === undefined ? [] : Array.isArray(value) ? value : [value];
    for (const id of selected) {
      if (id && !list.some((option) => option.value === id)) {
        list.unshift({ value: id, label: currentLabels?.[id] ?? id });
      }
    }
    return list;
  }, [optionsQuery.data, value, currentLabels, enabledOnly, excludeIds]);

  return (
    <Select
      remote
      filter
      showClear
      value={value ?? undefined}
      multiple={multiple}
      placeholder={placeholder ?? t('user.placeholder')}
      disabled={disabled}
      optionList={options}
      loading={optionsQuery.isPending}
      onSearch={setKeyword}
      onChange={(next) => {
        if (multiple) {
          onChange?.(Array.isArray(next) ? (next as Id[]) : undefined);
        } else {
          onChange?.(typeof next === 'string' ? next : undefined);
        }
      }}
      style={style ?? { width: FIELD_CHOICE_WIDTH }}
    />
  );
}
