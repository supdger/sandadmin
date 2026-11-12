import { createElement } from 'react';

import { IconAlertTriangle } from '@douyinfe/semi-icons';
import { Modal, Toast } from '@douyinfe/semi-ui';

type ConfirmProps = Parameters<typeof Modal.confirm>[0];

const DANGER_ICON = createElement(IconAlertTriangle, {
  style: { color: 'var(--semi-color-danger)' },
});

/** 打开确认框前先收掉还挂着的提示，避免提示条压住标题。 */
export function confirmAction(props: ConfirmProps): ReturnType<typeof Modal.confirm> {
  Toast.destroyAll();
  return Modal.confirm(props);
}

/** 删除/停用/恢复默认等破坏性确认：统一红色警告图标。 */
export function confirmDanger(props: ConfirmProps): ReturnType<typeof Modal.confirm> {
  return confirmAction({ icon: DANGER_ICON, ...props });
}
