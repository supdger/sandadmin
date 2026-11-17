import type { ReactNode } from 'react';

import { useTranslation } from 'react-i18next';

import { MoreActions, type ActionItem } from './MoreActions';

export interface RowAction<T extends object> {
  key: string;
  label: ReactNode;
  permission?: string | string[];
  disabled?: boolean;
  danger?: boolean;
  confirmTitle?: ReactNode;
  hidden?: boolean;
  onClick: (record: T) => void;
}

export interface RowActionsProps<T extends object> {
  record: T;
  /** 给了就渲染「编辑」按钮。 */
  onEdit?: (record: T) => void;
  editPermission?: string | string[];
  /** 给了就渲染「删除」（危险项，默认确认框）。 */
  onDelete?: (record: T) => void;
  deletePermission?: string | string[];
  deleteConfirmTitle?: ReactNode;
  /** 追加的自定义行操作。 */
  actions?: RowAction<T>[];
  /** 平铺上限，超过收进「更多」；默认 2。 */
  maxInline?: number;
}

/**
 * 表格行内操作组：编辑/删除/自定义项，权限码显隐，
 * 删除等危险项先弹确认框再执行。
 */
export function RowActions<T extends object>({
  record,
  onEdit,
  editPermission,
  onDelete,
  deletePermission,
  deleteConfirmTitle,
  actions,
  maxInline = 2,
}: RowActionsProps<T>) {
  const { t } = useTranslation('feForm');

  const items: ActionItem[] = [];
  if (onEdit) {
    items.push({
      key: 'edit',
      label: t('actions.edit'),
      permission: editPermission,
      onClick: () => onEdit(record),
    });
  }
  if (onDelete) {
    items.push({
      key: 'delete',
      label: t('actions.delete'),
      permission: deletePermission,
      danger: true,
      confirmTitle: deleteConfirmTitle,
      onClick: () => onDelete(record),
    });
  }
  for (const action of actions ?? []) {
    items.push({
      key: action.key,
      label: action.label,
      permission: action.permission,
      disabled: action.disabled,
      danger: action.danger,
      confirmTitle: action.confirmTitle,
      hidden: action.hidden,
      onClick: () => action.onClick(record),
    });
  }

  return <MoreActions actions={items} maxInline={maxInline} />;
}
