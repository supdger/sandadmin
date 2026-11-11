import { useEffect, useMemo, useState } from 'react';

import { Select } from '@douyinfe/semi-ui';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import { getUserOptions, type UserOption } from '@/api/v1/org';
import type { Id } from '@/api/v1/types';

export interface UserOptionItem {
  value: Id;
  label: string;
  disabled?: boolean;
}

export interface UserOptionSelectProps {
  value?: Id | null;
  onChange?: (value: Id | undefined) => void;
  /** 已选中但当前搜索结果里没有的用户（编辑回显用），label 直接给。 */
  currentLabel?: string;
  placeholder?: string;
  disabled?: boolean;
  /** 只允许选这些状态以外的用户；默认全部可选。 */
  allowedStatus?: 'enabled';
  /** 需要排除的用户 id（如转交时排除本人）。 */
  excludeIds?: Id[];
  /** 选项附加说明（部门名等），拼在 label 后。 */
  renderSuffix?: (option: UserOption) => string;
}

/**
 * 远程搜索选人 Select：/org/user-options?keyword=，输入防抖 300ms。
 * 只用于负责人、转交接手人这类「从用户里挑一个」的场景。
 */
export function UserOptionSelect({
  value,
  onChange,
  currentLabel,
  placeholder,
  disabled,
  allowedStatus = 'enabled',
  excludeIds,
  renderSuffix,
}: UserOptionSelectProps) {
  const { t } = useTranslation('org');
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

  const options = useMemo<UserOptionItem[]>(() => {
    const items = optionsQuery.data?.items ?? [];
    const excluded = new Set(excludeIds ?? []);
    const list = items
      .filter((u) => !excluded.has(u.id))
      .filter((u) => (allowedStatus ? u.status === allowedStatus : true))
      .map((u) => ({
        value: u.id,
        label: renderSuffix ? `${u.nickname} · ${renderSuffix(u)}` : u.nickname,
        disabled: false,
      }));
    // 已选值不在结果里时补一条，保证回显不丢。
    if (value && !list.some((o) => o.value === value)) {
      list.unshift({
        value,
        label: currentLabel ?? value,
        disabled: false,
      });
    }
    return list;
  }, [optionsQuery.data, value, currentLabel, allowedStatus, excludeIds, renderSuffix]);

  return (
    <Select
      remote
      filter
      showClear
      value={value ?? undefined}
      placeholder={placeholder ?? t('depts.form.leaderPlaceholder')}
      disabled={disabled}
      optionList={options}
      onSearch={setKeyword}
      onChange={(next) => onChange?.(typeof next === 'string' ? next : undefined)}
      style={{ width: '100%' }}
    />
  );
}
