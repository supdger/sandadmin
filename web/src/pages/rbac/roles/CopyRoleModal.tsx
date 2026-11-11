import { useState } from 'react';

import { Input, Modal, Typography } from '@douyinfe/semi-ui';
import { useTranslation } from 'react-i18next';

import { copyRole, type Role } from '@/api/v1/roles';
import { useFormIdempotencyKey } from '@/hooks/useFormIdempotencyKey';
import { isAdminApiError } from '@/lib/adminClient';
import { adminErrorMessage } from '@/lib/adminError';

const NAME_MAX = 20;

export interface CopyRoleModalProps {
  /** 仅在打开期间挂载；`role` 是复制来源。 */
  role: Role;
  onClose: () => void;
  /** 复制成功后以新建的角色回调。 */
  onCopied: (role: Role) => void;
}

/**
 * 复制角色弹窗。新角色沿用来源的权限和数据范围；
 * 只需输入名称。
 */
export function CopyRoleModal({ role, onClose, onCopied }: CopyRoleModalProps) {
  const { t } = useTranslation('rbac');
  const [name, setName] = useState(`${role.name} 副本`);
  const [error, setError] = useState('');
  const [pending, setPending] = useState(false);
  const submitKey = useFormIdempotencyKey(true);

  const submit = async () => {
    const trimmed = name.trim();
    if (!trimmed) {
      setError(t('roles.copyModal.nameRequired'));
      return;
    }
    if (trimmed.length > NAME_MAX) {
      setError(t('roles.form.nameTooLong'));
      return;
    }
    setPending(true);
    setError('');
    try {
      const copied = await copyRole(role.id, { name: trimmed }, submitKey);
      onCopied(copied);
      onClose();
    } catch (err) {
      if (isAdminApiError(err)) {
        const fieldMessage = err.fields?.name?.[0];
        setError(fieldMessage ?? adminErrorMessage(err, ''));
      } else {
        setError(adminErrorMessage(err, t('roles.loadFailed')));
      }
    } finally {
      setPending(false);
    }
  };

  return (
    <Modal
      visible
      title={t('roles.copyModal.title')}
      okText={t('roles.copyModal.ok')}
      cancelText={t('permission.discardCancel')}
      confirmLoading={pending}
      onOk={() => void submit()}
      onCancel={onClose}
    >
      <div className="flex flex-col gap-2">
        <Typography.Text strong>{t('roles.copyModal.name')}</Typography.Text>
        <Input
          value={name}
          maxLength={NAME_MAX}
          placeholder={role.name}
          onChange={(v) => {
            setName(v);
            if (error) setError('');
          }}
          onEnterPress={() => void submit()}
        />
        {error ? (
          <Typography.Text type="danger" size="small">
            {error}
          </Typography.Text>
        ) : null}
      </div>
    </Modal>
  );
}
