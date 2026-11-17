import type { ReactNode } from 'react';

import {
  IconChevronDown,
  IconGridView,
  IconList,
  IconPlus,
  IconRefresh,
} from '@douyinfe/semi-icons';
import { Button, Checkbox, Dropdown, Space, Typography } from '@douyinfe/semi-ui';

import { useTranslation } from 'react-i18next';

import { useMe } from '@/hooks/useMe';
import { hasPermission } from '@/hooks/useCan';
import { confirmDanger } from '@/lib/confirmAction';

export type TableDensity = 'default' | 'middle' | 'small';

export interface ToolbarColumn {
  key: string;
  title: ReactNode;
}

export interface BatchAction {
  key: string;
  label: ReactNode;
  /** 权限码：无权限不渲染该操作。 */
  permission?: string | string[];
  /** 危险操作（批量删除等）：红色按钮 + 确认框。 */
  danger?: boolean;
  /** 确认框标题；危险操作缺省给通用确认文案。 */
  confirmTitle?: ReactNode;
  disabled?: boolean;
  onClick: (keys: (string | number)[]) => void;
}

export interface TableToolbarProps {
  title?: ReactNode;
  /** 操作区自定义按钮位，排在批量/新建按钮之后。 */
  extra?: ReactNode;
  onRefresh?: () => void;
  refreshing?: boolean;
  /** 列显隐控制：全量列 + 当前可见 key；两项都给才渲染入口。 */
  columns?: ToolbarColumn[];
  visibleKeys?: string[];
  onVisibleKeysChange?: (keys: string[]) => void;
  /** 表格密度档位（Semi Table size）。 */
  density?: TableDensity;
  onDensityChange?: (density: TableDensity) => void;
  /** 新建按钮；给了 onCreate 才渲染。 */
  create?: {
    permission?: string | string[];
    label?: ReactNode;
    onClick: () => void;
  };
  /** 批量操作（含批量删除）；未选行一律禁用。 */
  batchActions?: BatchAction[];
  selectedKeys?: (string | number)[];
}

const DENSITY_KEYS: Record<TableDensity, string> = {
  default: 'densityDefault',
  middle: 'densityMiddle',
  small: 'densitySmall',
};

/**
 * 表格头工具栏：标题、批量/新建操作、列显隐、密度、刷新。
 * 批量与新建按权限码显隐；危险批量操作确认框 + 未选行禁用。
 */
export function TableToolbar({
  title,
  extra,
  onRefresh,
  refreshing,
  columns,
  visibleKeys,
  onVisibleKeysChange,
  density,
  onDensityChange,
  create,
  batchActions,
  selectedKeys,
}: TableToolbarProps) {
  const { t } = useTranslation('feForm');
  const { data: me } = useMe();
  const granted = me?.permissions ?? [];

  const selected = selectedKeys ?? [];
  const visibleActions = (batchActions ?? []).filter(
    (action) => !action.permission || hasPermission(granted, action.permission),
  );
  const canCreate = create && (!create.permission || hasPermission(granted, create.permission));
  const showColumns = Boolean(columns && visibleKeys && onVisibleKeysChange);

  const runAction = (action: BatchAction) => {
    const keys = [...selected];
    if (action.danger || action.confirmTitle !== undefined) {
      confirmDanger({
        title: action.confirmTitle ?? t('table.confirmDanger'),
        onOk: () => action.onClick(keys),
      });
      return;
    }
    action.onClick(keys);
  };

  const columnPanel = (
    <div className="max-h-72 overflow-auto p-3">
      <Checkbox.Group
        direction="vertical"
        value={visibleKeys}
        onChange={(keys) => onVisibleKeysChange?.(keys as string[])}
        options={(columns ?? []).map((column) => ({
          value: column.key,
          label: column.title,
        }))}
      />
    </div>
  );

  const densityMenu = (
    <Dropdown.Menu>
      {(Object.keys(DENSITY_KEYS) as TableDensity[]).map((level) => (
        <Dropdown.Item
          key={level}
          active={density === level}
          onClick={() => onDensityChange?.(level)}
        >
          {t(`table.${DENSITY_KEYS[level]}`)}
        </Dropdown.Item>
      ))}
    </Dropdown.Menu>
  );

  return (
    <div className="flex items-center justify-between gap-3">
      <div className="flex items-center gap-3">
        {title ? <Typography.Title heading={6} className="!mb-0">{title}</Typography.Title> : null}
        {selected.length > 0 ? (
          <Typography.Text type="secondary" size="small">
            {t('table.selected', { count: selected.length })}
          </Typography.Text>
        ) : null}
      </div>
      <Space spacing="tight">
        {visibleActions.map((action) => (
          <Button
            key={action.key}
            size="small"
            theme={action.danger ? 'solid' : 'light'}
            type={action.danger ? 'danger' : 'primary'}
            disabled={action.disabled || selected.length === 0}
            onClick={() => runAction(action)}
          >
            {action.label}
          </Button>
        ))}
        {canCreate ? (
          <Button
            size="small"
            theme="solid"
            type="primary"
            icon={<IconPlus />}
            onClick={create.onClick}
          >
            {create.label ?? t('actions.create')}
          </Button>
        ) : null}
        {extra}
        {showColumns ? (
          <Dropdown trigger="click" position="bottomRight" render={columnPanel}>
            <Button size="small" icon={<IconGridView />} aria-label={t('table.columns')} />
          </Dropdown>
        ) : null}
        {onDensityChange ? (
          <Dropdown trigger="click" position="bottomRight" render={densityMenu}>
            <Button size="small" icon={<IconList />}>
              <span className="inline-flex items-center gap-1">
                {density ? t(`table.${DENSITY_KEYS[density]}`) : t('table.density')}
                <IconChevronDown size="small" />
              </span>
            </Button>
          </Dropdown>
        ) : null}
        {onRefresh ? (
          <Button
            size="small"
            icon={<IconRefresh spin={refreshing} />}
            aria-label={t('actions.refresh')}
            onClick={onRefresh}
          />
        ) : null}
      </Space>
    </div>
  );
}
