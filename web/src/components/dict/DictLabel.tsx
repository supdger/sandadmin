import type { ReactNode } from 'react';

import { Spin, Tag } from '@douyinfe/semi-ui';
import { useTranslation } from 'react-i18next';

import { useDictByCode } from '@/hooks/useDict';

export interface DictLabelProps {
  /** 选项组 code（dict_type.code）。 */
  code: string | null | undefined;
  /** 存储值；命中字典项时渲染带色 Tag。 */
  value?: string | null;
  /** 查不到选项时的兜底展示。 */
  fallback?: ReactNode;
}

/** 按字典把存储值渲染成标签：文案取 label，颜色取字典配置的 hex。 */
export function DictLabel({ code, value, fallback }: DictLabelProps) {
  const { t } = useTranslation('feForm');
  const dict = useDictByCode(code);

  if (dict.isPending) {
    return <Spin size="small" />;
  }
  const option = value ? dict.optionOf(value) : undefined;
  if (!option) {
    return <>{fallback ?? t('dict.emptyValue')}</>;
  }
  return (
    <Tag
      type="solid"
      style={
        option.color
          ? { backgroundColor: option.color, color: 'var(--semi-color-white)', border: 'none' }
          : undefined
      }
    >
      {option.label}
    </Tag>
  );
}
