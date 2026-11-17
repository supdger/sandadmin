import type { ReactNode } from 'react';

import { Form } from '@douyinfe/semi-ui';
import type { FormState, RuleItem } from '@douyinfe/semi-ui/lib/es/form/interface';
import { useFormApi, useFormState, withField } from '@douyinfe/semi-ui/lib/es/form';

import { DictCheckbox } from '@/components/dict/DictCheckbox';
import { DictRadio } from '@/components/dict/DictRadio';
import { DictSelect } from '@/components/dict/DictSelect';
import { DictSwitch } from '@/components/dict/DictSwitch';
import { UserSelect } from '@/components/form/UserSelect';
import { FileField } from '@/components/form/FileField';
import { buildFieldRules } from '@/lib/formUtils';

export interface FieldBase {
  /** 接口字段名，snake_case 原样。 */
  field: string;
  label?: ReactNode;
  required?: boolean;
  rules?: RuleItem[];
  /** 栅格占位 1-12，默认 12（整行）。 */
  span?: number;
  helpText?: ReactNode;
  extraText?: ReactNode;
  disabled?: boolean;
  hidden?: boolean;
  noLabel?: boolean;
  placeholder?: string;
  /** 控件宽度 px；不给用控件默认宽。 */
  width?: number;
}

export interface TextField extends FieldBase {
  type: 'input' | 'textarea' | 'password';
  maxLength?: number;
}

export interface NumberField extends FieldBase {
  type: 'number';
  min?: number;
  max?: number;
  step?: number;
  precision?: number;
}

export interface SelectOption {
  label: string;
  value: string | number;
}

export interface SelectField extends FieldBase {
  type: 'select';
  options: SelectOption[];
  multiple?: boolean;
  filter?: boolean;
}

export interface DictField extends FieldBase {
  type: 'dictSelect' | 'dictRadio' | 'dictCheckbox';
  /** 选项组 code（dict_type.code）。 */
  code: string;
  multiple?: boolean;
}

export interface DictSwitchField extends FieldBase {
  type: 'dictSwitch';
  code?: string;
  onValue?: string | number | boolean;
  offValue?: string | number | boolean;
}

export interface UserField extends FieldBase {
  type: 'userSelect';
  multiple?: boolean;
}

export interface DateField extends FieldBase {
  type: 'date';
  kind?: 'date' | 'dateTime' | 'dateRange';
  /** 范围选择时把 [from,to] 拆成两个查询键（如 create_time_from/to）。 */
  rangeKeys?: { from: string; to: string };
}

export interface SwitchField extends FieldBase {
  type: 'switch';
}

export interface UploadField extends FieldBase {
  type: 'upload';
  accept?: string;
}

export interface SlotContext {
  value: unknown;
  values: Record<string, unknown>;
  errors: FormState['errors'];
  setValue: (value: unknown) => void;
}

export interface SlotField extends FieldBase {
  type: 'slot';
  render: (ctx: SlotContext) => ReactNode;
}

export type FormFieldConfig =
  | TextField
  | NumberField
  | SelectField
  | DictField
  | DictSwitchField
  | UserField
  | DateField
  | SwitchField
  | UploadField
  | SlotField;

const DictSelectBound = withField(DictSelect);
const DictRadioBound = withField(DictRadio);
const DictCheckboxBound = withField(DictCheckbox);
const DictSwitchBound = withField(DictSwitch);
const UserSelectBound = withField(UserSelect);
const FileFieldBound = withField(FileField);

const DATE_FORMAT: Record<'date' | 'dateTime' | 'dateRange', string> = {
  date: 'yyyy-MM-dd',
  dateTime: 'yyyy-MM-dd HH:mm:ss',
  dateRange: 'yyyy-MM-dd',
};

function spanOf(config: FieldBase): number {
  return Math.min(12, Math.max(1, config.span ?? 12));
}

export interface FieldItemProps {
  config: FormFieldConfig;
}

/**
 * 配置化字段渲染：按 config.type 落到对应 Semi Form 控件。
 * 必须放在 <Form> 里用（slot/upload 走 formApi 绑定值）。
 */
export function FieldItem({ config }: FieldItemProps) {
  const formApi = useFormApi<Record<string, unknown>>();
  const formState = useFormState<Record<string, unknown>>();

  if (config.hidden) {
    return null;
  }

  // disabled 不在 Semi CommonFieldProps 里（由内层控件声明），这里用普通对象一并下发。
  const common = {
    field: config.field,
    label: config.label,
    rules: buildFieldRules(config),
    disabled: config.disabled,
    helpText: config.helpText,
    extraText: config.extraText,
    noLabel: config.noLabel ?? config.label === undefined,
  };
  const width = config.width ? { width: config.width } : undefined;

  let control: ReactNode;
  switch (config.type) {
    case 'input':
      control = (
        <Form.Input {...common} placeholder={config.placeholder} maxLength={config.maxLength} showClear style={width} />
      );
      break;
    case 'password':
      control = (
        <Form.Input {...common} mode="password" placeholder={config.placeholder} maxLength={config.maxLength} style={width} />
      );
      break;
    case 'textarea':
      control = (
        <Form.TextArea {...common} placeholder={config.placeholder} maxLength={config.maxLength} style={width} />
      );
      break;
    case 'number':
      control = (
        <Form.InputNumber
          {...common}
          min={config.min}
          max={config.max}
          step={config.step}
          precision={config.precision}
          style={width}
        />
      );
      break;
    case 'select':
      control = (
        <Form.Select
          {...common}
          optionList={config.options}
          multiple={config.multiple}
          filter={config.filter ?? true}
          showClear
          placeholder={config.placeholder}
          style={width}
        />
      );
      break;
    case 'dictSelect':
      control = (
        <DictSelectBound
          {...common}
          code={config.code}
          multiple={config.multiple}
          placeholder={config.placeholder}
          style={width}
        />
      );
      break;
    case 'dictRadio':
      control = <DictRadioBound {...common} code={config.code} />;
      break;
    case 'dictCheckbox':
      control = <DictCheckboxBound {...common} code={config.code} />;
      break;
    case 'dictSwitch':
      control = (
        <DictSwitchBound
          {...common}
          code={config.code}
          onValue={config.onValue}
          offValue={config.offValue}
        />
      );
      break;
    case 'userSelect':
      control = (
        <UserSelectBound
          {...common}
          multiple={config.multiple}
          placeholder={config.placeholder}
          style={width}
        />
      );
      break;
    case 'date': {
      const kind = config.kind ?? 'date';
      control = (
        <Form.DatePicker
          {...common}
          type={kind}
          format={DATE_FORMAT[kind]}
          placeholder={config.placeholder}
          style={width}
        />
      );
      break;
    }
    case 'switch':
      control = <Form.Switch {...common} />;
      break;
    case 'upload':
      control = <FileFieldBound {...common} accept={config.accept} />;
      break;
    case 'slot': {
      const ctx: SlotContext = {
        value: formState.values?.[config.field],
        values: formState.values ?? {},
        errors: formState.errors,
        setValue: (next) => formApi.setValue(config.field, next),
      };
      control = (
        <Form.Slot label={config.label} error={{ error: formState.errors?.[config.field] }}>
          {config.render(ctx)}
        </Form.Slot>
      );
      break;
    }
  }

  return <div style={{ gridColumn: `span ${spanOf(config)} / span ${spanOf(config)}` }}>{control}</div>;
}
