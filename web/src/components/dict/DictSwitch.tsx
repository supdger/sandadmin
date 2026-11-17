import { Switch } from '@douyinfe/semi-ui';

import { useDictByCode } from '@/hooks/useDict';

export type DictSwitchValue = string | number | boolean;

export interface DictSwitchProps {
  /** 存储值（不是 boolean 状态本身）。 */
  value?: DictSwitchValue | null;
  onChange?: (value: DictSwitchValue) => void;
  /** 开对应的存储值；配合 code 时缺省取字典第 1 项。 */
  onValue?: DictSwitchValue;
  /** 关对应的存储值；配合 code 时缺省取字典第 2 项。 */
  offValue?: DictSwitchValue;
  /** 选项组 code；不给则按布尔受控（true/false）。 */
  code?: string;
  disabled?: boolean;
}

/**
 * 二值绑定开关：开/关映射到任意存储值（如 status 的 enabled/disabled）。
 * 给 code 时用字典前两项做映射，onValue/offValue 可覆盖。
 */
export function DictSwitch({ value, onChange, onValue, offValue, code, disabled }: DictSwitchProps) {
  const dict = useDictByCode(code ?? null);

  const resolvedOn: DictSwitchValue = onValue ?? dict.options[0]?.value ?? true;
  const resolvedOff: DictSwitchValue = offValue ?? dict.options[1]?.value ?? false;
  const checked = value !== undefined && value !== null && value === resolvedOn;

  return (
    <Switch
      checked={checked}
      disabled={disabled || dict.isPending}
      onChange={(next) => onChange?.(next ? resolvedOn : resolvedOff)}
    />
  );
}
