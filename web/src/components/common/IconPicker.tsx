import { useMemo, useState } from 'react';

import { IconClear, IconSearch } from '@douyinfe/semi-icons';
import { Button, Input, Popover, Typography } from '@douyinfe/semi-ui';
import { useTranslation } from 'react-i18next';

import { SvgIcon } from '@/components/SvgIcon';
import { listIconNames } from '@/lib/icons';

export interface IconPickerProps {
  /** 受控值：图标注册表里的名字；null/undefined 表示未选。 */
  value?: string | null;
  onChange?: (name: string | null) => void;
  disabled?: boolean;
  /** 允许清空已选。 */
  allowClear?: boolean;
  /** 面板一行几个图标。 */
  columns?: number;
}

/**
 * 图标选择器：候选来自 lib/icons 注册表（内置 Semi 图标集 +
 * 插件注册项），搜索按名字过滤，受控输出图标名。菜单编辑器配套。
 */
export function IconPicker({
  value,
  onChange,
  disabled = false,
  allowClear = true,
  columns = 6,
}: IconPickerProps) {
  const { t } = useTranslation('feMisc');
  const [visible, setVisible] = useState(false);
  const [keyword, setKeyword] = useState('');

  const names = useMemo(() => listIconNames(), []);
  const filtered = useMemo(() => {
    const needle = keyword.trim().toLowerCase();
    return needle ? names.filter((n) => n.toLowerCase().includes(needle)) : names;
  }, [names, keyword]);

  const pick = (name: string) => {
    onChange?.(name);
    setVisible(false);
    setKeyword('');
  };

  return (
    <Popover
      visible={visible}
      trigger="custom"
      onClickOutSide={() => setVisible(false)}
      position="bottomLeft"
      motion={false}
      content={
        <div style={{ width: 320 }} onClick={(e) => e.stopPropagation()}>
          <Input
            prefix={<IconSearch />}
            placeholder={t('iconPicker.search')}
            value={keyword}
            onChange={setKeyword}
            showClear
          />
          <div
            role="listbox"
            aria-label={t('iconPicker.placeholder')}
            className="mt-2 grid gap-1"
            style={{ gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))`, maxHeight: 240, overflowY: 'auto' }}
          >
            {filtered.map((name) => (
              <button
                key={name}
                type="button"
                role="option"
                aria-selected={name === value}
                title={name}
                onClick={() => pick(name)}
                className="flex flex-col items-center gap-1 rounded p-1.5"
                style={{
                  cursor: 'pointer',
                  border: 'none',
                  background:
                    name === value
                      ? 'var(--semi-color-primary-light-default)'
                      : 'transparent',
                  color: 'var(--semi-color-text-0)',
                }}
              >
                <SvgIcon name={name} size="large" />
                <Typography.Text size="small" ellipsis={{ showTooltip: false }} style={{ width: '100%' }}>
                  {name}
                </Typography.Text>
              </button>
            ))}
            {filtered.length === 0 ? (
              <Typography.Text type="tertiary" size="small" className="col-span-full py-4 text-center">
                {t('iconPicker.empty')}
              </Typography.Text>
            ) : null}
          </div>
        </div>
      }
    >
      <span
        className="inline-flex items-center gap-1"
        onClick={() => {
          if (!disabled) {
            setVisible((v) => !v);
          }
        }}
      >
        <Button disabled={disabled} icon={value ? <SvgIcon name={value} /> : undefined}>
          {value ?? t('iconPicker.placeholder')}
        </Button>
        {allowClear && value ? (
          <Button
            icon={<IconClear />}
            theme="borderless"
            size="small"
            aria-label={t('iconPicker.clear')}
            disabled={disabled}
            onClick={(e) => {
              e.stopPropagation();
              onChange?.(null);
            }}
          />
        ) : null}
      </span>
    </Popover>
  );
}
