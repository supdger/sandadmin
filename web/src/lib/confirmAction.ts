import { Modal, Toast } from '@douyinfe/semi-ui';

type ConfirmProps = Parameters<typeof Modal.confirm>[0];

/** 打开确认框前先收掉还挂着的提示，避免提示条压住标题。 */
export function confirmAction(props: ConfirmProps): ReturnType<typeof Modal.confirm> {
  Toast.destroyAll();
  return Modal.confirm(props);
}
