import { useEffect, useRef, type ReactNode } from 'react';

import { Button, Form, Space } from '@douyinfe/semi-ui';
import type { FormApi } from '@douyinfe/semi-ui/lib/es/form/interface';
import { useTranslation } from 'react-i18next';

import { applyFieldErrors } from '@/lib/formUtils';
import { emitValues } from './emitValues';
import { FieldItem, type FormFieldConfig } from './FieldItem';

export type FormValues = Record<string, unknown>;

export interface ConfigFormProps {
  fields: FormFieldConfig[];
  /** 初始值；Semi initValues 只在挂载时生效，编辑回显请用 key 重挂载。 */
  initValues?: FormValues;
  /** 校验通过后回调；values 已过 emitValues（rangeKeys 拆键）。 */
  onSubmit?: (values: FormValues) => void;
  onValueChange?: (values: FormValues, changed: Partial<FormValues>) => void;
  /** v4 信封错误：fields 回显到对应字段。 */
  error?: unknown;
  submitting?: boolean;
  /** 尾部操作区；false 不渲染（弹层场景由外部按钮提交）。 */
  actions?: ReactNode | false;
  submitText?: ReactNode;
  resetText?: ReactNode;
  onReset?: () => void;
  layout?: 'horizontal' | 'vertical';
  labelPosition?: 'top' | 'left';
  getFormApi?: (api: FormApi<FormValues>) => void;
  className?: string;
}

/**
 * 配置化表单：字段数组驱动渲染，提交/重置、服务端 fields 错误回显。
 * 需要外部触发的场景经 getFormApi 拿 formApi（submitForm/reset/setValues）。
 */
export function ConfigForm({
  fields,
  initValues,
  onSubmit,
  onValueChange,
  error,
  submitting,
  actions,
  submitText,
  resetText,
  onReset,
  layout,
  labelPosition,
  getFormApi,
  className,
}: ConfigFormProps) {
  const { t } = useTranslation('feForm');
  const apiRef = useRef<FormApi<FormValues> | null>(null);

  useEffect(() => {
    if (error && apiRef.current) {
      applyFieldErrors(apiRef.current, error);
    }
  }, [error]);

  const showActions = actions !== false;

  return (
    <Form<FormValues>
      layout={layout}
      labelPosition={labelPosition}
      initValues={initValues}
      onSubmit={(values) => onSubmit?.(emitValues(fields, values))}
      onValueChange={(values, changed) => onValueChange?.(values, changed)}
      getFormApi={(api) => {
        apiRef.current = api;
        getFormApi?.(api);
      }}
      className={className}
    >
      <div className="grid grid-cols-12 gap-x-4">
        {fields.map((config) => (
          <FieldItem key={config.field} config={config} />
        ))}
      </div>
      {showActions ? (
        <Space className="mt-2">
          {actions ?? (
            <>
              <Button theme="solid" type="primary" htmlType="submit" loading={submitting}>
                {submitText ?? t('actions.submit')}
              </Button>
              <Button
                htmlType="button"
                onClick={() => {
                  apiRef.current?.reset();
                  onReset?.();
                }}
              >
                {resetText ?? t('actions.reset')}
              </Button>
            </>
          )}
        </Space>
      ) : null}
    </Form>
  );
}
