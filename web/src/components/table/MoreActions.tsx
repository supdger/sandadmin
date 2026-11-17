import type { ReactNode } from 'react';

import { IconMore } from '@douyinfe/semi-icons';
import { Button, Dropdown, Space } from '@douyinfe/semi-ui';
import type { ButtonProps } from '@douyinfe/semi-ui/lib/es/button';

import { useTranslation } from 'react-i18next';

import { useMe } from '@/hooks/useMe';
import { hasPermission } from '@/hooks/useCan';
import { confirmDanger } from '@/lib/confirmAction';

export interface ActionItem {
  key: string;
  label: ReactNode;
  icon?: ReactNode;
  /** 权限码：无权限不渲染。 */
  permission?: string | string[];
  disabled?: boolean;
  /** 危险项：红色呈现，点击先弹确认框。 */
  danger?: boolean;
  /** 非危险项要确认框时给标题；危险项不给标题用通用确认文案。 */
  confirmTitle?: ReactNode;
  hidden?: boolean;
  onClick: () => void;
}

export interface MoreActionsProps {
  actions: ActionItem[];
  /** 平铺按钮个数上限，超过收进「更多」下拉；默认 2。 */
  maxInline?: number;
  size?: ButtonProps['size'];
}

/**
 * 操作项按钮组：前 maxInline 个平铺，其余收进「更多」Dropdown。
 * 权限码显隐、禁用态、危险项确认框统一在这里处理。
 */
export function MoreActions({ actions, maxInline = 2, size = 'small' }: MoreActionsProps) {
  const { t } = useTranslation('feForm');
  const { data: me } = useMe();
  const granted = me?.permissions ?? [];

  const visible = actions.filter(
    (action) => !action.hidden && (!action.permission || hasPermission(granted, action.permission)),
  );
  const inline = visible.slice(0, Math.max(0, maxInline));
  const overflow = visible.slice(Math.max(0, maxInline));

  const runAction = (action: ActionItem) => {
    if (action.disabled) {
      return;
    }
    if (action.danger || action.confirmTitle !== undefined) {
      confirmDanger({
        title: action.confirmTitle ?? t('table.confirmDanger'),
        onOk: action.onClick,
      });
      return;
    }
    action.onClick();
  };

  if (visible.length === 0) {
    return null;
  }

  const menu = (
    <Dropdown.Menu>
      {overflow.map((action) => (
        <Dropdown.Item
          key={action.key}
          type={action.danger ? 'danger' : 'primary'}
          icon={action.icon}
          disabled={action.disabled}
          onClick={() => runAction(action)}
        >
          {action.label}
        </Dropdown.Item>
      ))}
    </Dropdown.Menu>
  );

  return (
    <Space spacing="tight">
      {inline.map((action) => (
        <Button
          key={action.key}
          size={size}
          theme="borderless"
          type={action.danger ? 'danger' : 'primary'}
          icon={action.icon}
          disabled={action.disabled}
          onClick={() => runAction(action)}
        >
          {action.label}
        </Button>
      ))}
      {overflow.length > 0 ? (
        <Dropdown trigger="click" position="bottomRight" render={menu}>
          <Button size={size} theme="borderless" icon={<IconMore />} aria-label={t('actions.more')} />
        </Dropdown>
      ) : null}
    </Space>
  );
}
