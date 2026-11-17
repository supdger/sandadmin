import { useRef, useState } from 'react';

import { IconChevronDown, IconChevronUp } from '@douyinfe/semi-icons';
import { Button, Form, Space } from '@douyinfe/semi-ui';
import type { FormApi } from '@douyinfe/semi-ui/lib/es/form/interface';
import { useTranslation } from 'react-i18next';

import { compactValues } from '@/lib/formUtils';
import type { AdminQuery } from '@/lib/adminClient';
import { emitValues } from './emitValues';
import { FieldItem, type FormFieldConfig } from './FieldItem';

/** 超过这个字段数自动进展开/收起，第 4 个起放展开区（老栈硬规矩）。 */
const INLINE_FIELD_COUNT = 3;

export interface SearchBarProps {
  fields: FormFieldConfig[];
  /** 回显当前筛选值。 */
  initValues?: Record<string, unknown>;
  /** 查询：values 已去空值并拆好 rangeKeys，可直接并进接口参数。 */
  onSearch: (values: AdminQuery) => void;
  /** 重置：字段清空后回调（通常重新拉一遍空筛选）。 */
  onReset?: () => void;
  searching?: boolean;
}

/**
 * 配置化搜索栏：与 ConfigForm 同字段配置；超过 3 个字段自动展开/收起，
 * 查询/重置回调向外抛规整后的查询参数。
 */
export function SearchBar({ fields, initValues, onSearch, onReset, searching }: SearchBarProps) {
  const { t } = useTranslation('feForm');
  const apiRef = useRef<FormApi<Record<string, unknown>> | null>(null);
  const collapsible = fields.length > INLINE_FIELD_COUNT;
  const [expanded, setExpanded] = useState(false);

  const visibleFields = collapsible && !expanded ? fields.slice(0, INLINE_FIELD_COUNT) : fields;

  const emitSearch = () => {
    const values = apiRef.current?.getValues() ?? {};
    onSearch(compactValues(emitValues(fields, values)));
  };

  const handleReset = () => {
    apiRef.current?.reset();
    onReset?.();
    onSearch({});
  };

  return (
    <div className="rounded-md bg-[var(--semi-color-fill-0)] px-4 pt-4">
      <Form<Record<string, unknown>>
        layout="horizontal"
        labelPosition="left"
        initValues={initValues}
        getFormApi={(api) => {
          apiRef.current = api;
        }}
        onSubmit={emitSearch}
      >
        <div className="grid grid-cols-12 items-start gap-x-4">
          {visibleFields.map((config) => (
            <FieldItem key={config.field} config={config} />
          ))}
        </div>
        <Space className="mb-4">
          <Button theme="solid" type="primary" htmlType="submit" loading={searching}>
            {t('actions.search')}
          </Button>
          <Button htmlType="button" onClick={handleReset}>
            {t('actions.reset')}
          </Button>
          {collapsible ? (
            <Button
              theme="borderless"
              icon={expanded ? <IconChevronUp /> : <IconChevronDown />}
              iconPosition="right"
              onClick={() => setExpanded((prev) => !prev)}
            >
              {expanded ? t('actions.collapse') : t('actions.expand')}
            </Button>
          ) : null}
        </Space>
      </Form>
    </div>
  );
}
