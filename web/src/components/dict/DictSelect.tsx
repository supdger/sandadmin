import type { CSSProperties } from 'react';

import { Select } from '@douyinfe/semi-ui';
import { useTranslation } from 'react-i18next';

import { FIELD_CHOICE_WIDTH } from '@/components/fieldWidth';
import { useDictByCode } from '@/hooks/useDict';

export interface DictSelectProps {
  /** 选项组 code（dict_type.code）。 */
  code: string | null | undefined;
  value?: string | string[] | null;
  onChange?: (value: string | string[] | undefined) => void;
  multiple?: boolean;
  placeholder?: string;
  disabled?: boolean;
  showClear?: boolean;
  style?: CSSProperties;
}

/** 字典驱动的下拉选择：选项走 useDictByCode，含加载/空态/可清空。 */
export function DictSelect({
  code,
  value,
  onChange,
  multiple,
  placeholder,
  disabled,
  showClear = true,
  style,
}: DictSelectProps) {
  const { t } = useTranslation('feForm');
  const dict = useDictByCode(code);

  return (
    <Select
      value={value ?? undefined}
      onChange={(next) => {
        if (typeof next === 'string') {
          onChange?.(next);
        } else if (Array.isArray(next)) {
          onChange?.(next as string[]);
        } else {
          onChange?.(undefined);
        }
      }}
      optionList={dict.options}
      multiple={multiple}
      loading={dict.isPending}
      disabled={disabled}
      showClear={showClear}
      filter
      placeholder={placeholder ?? t('dict.placeholder')}
      emptyContent={t('dict.empty')}
      style={style ?? { width: FIELD_CHOICE_WIDTH }}
    />
  );
}
