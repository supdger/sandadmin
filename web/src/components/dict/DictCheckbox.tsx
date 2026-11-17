import { Checkbox, Spin } from '@douyinfe/semi-ui';

import { useDictByCode } from '@/hooks/useDict';

export interface DictCheckboxProps {
  /** 选项组 code（dict_type.code）。 */
  code: string | null | undefined;
  value?: string[] | null;
  onChange?: (value: string[]) => void;
  disabled?: boolean;
}

/** 字典驱动的复选组。 */
export function DictCheckbox({ code, value, onChange, disabled }: DictCheckboxProps) {
  const dict = useDictByCode(code);

  if (dict.isPending) {
    return <Spin size="small" />;
  }
  return (
    <Checkbox.Group
      options={dict.options}
      value={value ?? []}
      disabled={disabled}
      onChange={(next) => onChange?.(next as string[])}
    />
  );
}
