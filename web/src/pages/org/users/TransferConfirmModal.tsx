import { useState } from 'react';

import { useTranslation } from 'react-i18next';

import { Button, List, Modal, Typography } from '@douyinfe/semi-ui';

import type { DisableCheckItem, User } from '@/api/v1/users';
import { UserOptionSelect } from '../shared/UserOptionSelect';

export interface TransferDialogState {
  kind: 'disable' | 'delete';
  user: User;
  items: DisableCheckItem[];
}

export interface TransferConfirmModalProps {
  state: TransferDialogState | null;
  onClose: () => void;
  /** 返回 true 时关闭弹窗；false 保留弹窗（错误已由调用方提示）。 */
  onConfirm: (transferToUserId: string | undefined) => Promise<boolean>;
}

/**
 * 停用/删除前的「待处理事项 + 可选转交人」弹窗。
 * disable-check 返回非空清单时出现；接手人可不选（不选则由后端决定是否放行）。
 */
export function TransferConfirmModal({ state, onClose, onConfirm }: TransferConfirmModalProps) {
  const { t } = useTranslation('org');
  const [transferTo, setTransferTo] = useState<string | undefined>(undefined);
  const [saving, setSaving] = useState(false);

  // 父组件用 key 保证每次打开都是新实例，状态无需在 effect 里重置。

  if (!state) {
    return null;
  }

  const action = state.kind === 'disable' ? t('users.danger.actionDisable') : t('users.danger.actionDelete');

  const handleConfirm = async () => {
    setSaving(true);
    try {
      const ok = await onConfirm(transferTo);
      if (ok) {
        onClose();
      }
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal
      visible
      title={t('users.danger.transferTitle', { action, name: state.user.nickname })}
      onCancel={onClose}
      footer={
        <div className="flex justify-end gap-2">
          <Button onClick={onClose} disabled={saving}>
            {t('common.cancel')}
          </Button>
          <Button theme="solid" loading={saving} onClick={() => void handleConfirm()}>
            {transferTo
              ? t('users.danger.transferOk', { action })
              : action}
          </Button>
        </div>
      }
    >
      <Typography.Paragraph type="secondary">{t('users.danger.transferHint')}</Typography.Paragraph>
      <List size="small" dataSource={state.items.map((item) => item.message).filter(Boolean)} renderItem={(msg) => <List.Item>{msg}</List.Item>} />
      <div className="mt-3">
        <Typography.Text>{t('users.danger.transferPlaceholder')}</Typography.Text>
        <div className="mt-1">
          <UserOptionSelect
            value={transferTo}
            onChange={setTransferTo}
            excludeIds={[state.user.id]}
            placeholder={t('users.danger.transferPlaceholder')}
          />
        </div>
      </div>
    </Modal>
  );
}
