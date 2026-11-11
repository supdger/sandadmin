import type { TFunction } from 'i18next';
import { Modal } from '@douyinfe/semi-ui';

import { API_ERROR_CODES } from '@/api/errorCodes';
import type { LifecycleRequest } from '@/api/v1/lifecycle';
import { isAdminApiError } from '@/lib/adminClient';

/** kind → 界面动词（「正在安装/升级/卸载 X」用）。 */
export function actionOfKind(kind: LifecycleRequest['kind'], t: TFunction): string {
  switch (kind) {
    case 'plugin_install':
      return t('actions.install');
    case 'plugin_uninstall':
      return t('actions.uninstall');
    case 'system_upgrade':
      return t('actions.upgradeSystem');
    default:
      return t('actions.upgrade');
  }
}

/** 申请的目标显示名：插件按编码查清单，系统升级/批量用固定称呼。 */
export function targetNameOf(
  request: LifecycleRequest,
  nameOfCode: (code: string) => string | null,
  t: TFunction,
): string {
  if (request.kind === 'system_upgrade') {
    return t('actions.upgradeSystem');
  }
  if (request.target_code === '*') {
    return t('actions.upgrade');
  }
  return nameOfCode(request.target_code) ?? request.target_code;
}

/** 另一个申请正在做的动作描述，用于忙碌提示「「流程审批」正在升级」。 */
export function doingTextOf(
  request: LifecycleRequest,
  nameOfCode: (code: string) => string | null,
  t: TFunction,
): string {
  if (request.kind === 'system_upgrade') {
    return t('actions.upgradeSystem');
  }
  const name = request.target_code === '*' ? '' : nameOfCode(request.target_code);
  const action = actionOfKind(request.kind, t);
  return name ? `「${name}」正在${action}` : `正在${action}`;
}

/** 「批量升级」逐项进度里做完的个数（succeeded + failed 都算做完）。 */
export function batchDoneCount(request: LifecycleRequest): number {
  return (request.items ?? []).filter(
    (item) => item.state === 'succeeded' || item.state === 'failed' || item.state === 'cancelled',
  ).length;
}

export function batchFailedNames(request: LifecycleRequest): string[] {
  return (request.items ?? [])
    .filter((item) => item.state === 'failed')
    .map((item) => item.code);
}

/** 权限在用着用着被收回：下一次操作被后端拒绝时统一走这个弹窗。 */
export function showPermissionRevoked(t: TFunction<'lifecycle'>): void {
  Modal.confirm({
    title: t('permissionRevoked'),
    okText: t('refresh'),
    cancelText: t('think'),
    onOk: () => window.location.reload(),
  });
}

/** 操作被抢跑/正在跑：PLUGIN_BUSY、UPDATE_IN_PROGRESS 的统一提示文案判断。 */
export function isBusyError(error: unknown): boolean {
  return (
    isAdminApiError(error) &&
    (error.code === API_ERROR_CODES.PLUGIN_BUSY || error.code === API_ERROR_CODES.UPDATE_IN_PROGRESS)
  );
}

/** 权限被收回时返回 true，调用方据此弹刷新提示。 */
export function isPermissionRevoked(error: unknown): boolean {
  return isAdminApiError(error) && error.code === API_ERROR_CODES.PERMISSION_DENIED;
}
