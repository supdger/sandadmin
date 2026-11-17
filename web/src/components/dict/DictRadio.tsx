import { Radio, Spin } from '@douyinfe/semi-ui';

import { useDictByCode } from '@/hooks/useDict';

export interface DictRadioProps {
  /** 选项组 code（dict_type.code）。 */
  code: string | null | undefined;
  value?: string | null;
  onChange?: (value: string) => void;
  disabled?: boolean;
}

/** 字典驱动的单选组。 */
export function DictRadio({ code, value, onChange, disabled }: DictRadioProps) {
  const dict = useDictByCode(code);

  if (dict.isPending) {
    return <Spin size="small" />;
  }
  return (
    <Radio.Group
      options={dict.options}
      value={value ?? undefined}
      disabled={disabled}
      onChange={(event) => onChange?.(String(event.target.value))}
    />
  );
}
