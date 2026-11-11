import { useState } from 'react';

import { useTranslation } from 'react-i18next';

import { Banner, Button, Input, Modal, Space, Toast, Typography } from '@douyinfe/semi-ui';

import { resetUserPassword, type User } from '@/api/v1/users';
import { isAdminApiError, isAdminSystemError, type AdminApiError } from '@/lib/adminClient';
import { adminErrorMessage } from '@/lib/adminError';
import { AdminCopyButton } from '@/components/AdminErrorNotice';

const MIN_PASSWORD_LENGTH = 6;

export interface ResetPasswordModalProps {
  user: User | null;
  onClose: () => void;
}

/** 重置密码弹窗：管理员自己设临时密码（≥6 位），旧密码立即失效。 */
export function ResetPasswordModal({ user, onClose }: ResetPasswordModalProps) {
  const { t } = useTranslation('org');
  const [password, setPassword] = useState('');
  const [fieldError, setFieldError] = useState('');
  const [systemError, setSystemError] = useState<AdminApiError | null>(null);
  const [saving, setSaving] = useState(false);

  // 父组件用 key 保证每次打开都是新实例，状态无需在 effect 里重置。

  const handleSubmit = async () => {
    if (!user || saving) {
      return;
    }
    if (password.length < MIN_PASSWORD_LENGTH) {
      setFieldError(t('users.form.passwordTooShort'));
      return;
    }
    setSaving(true);
    setFieldError('');
    setSystemError(null);
    try {
      await resetUserPassword(user.id, password);
      Toast.success(t('users.reset.done', { name: user.nickname }));
      onClose();
    } catch (error) {
      if (isAdminSystemError(error)) {
        setSystemError(error);
      } else if (isAdminApiError(error) && error.fields?.password?.[0]) {
        setFieldError(error.fields.password[0]);
      } else {
        Toast.error(adminErrorMessage(error, t('common:errors.system.message')));
      }
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal
      visible={Boolean(user)}
      title={t('users.reset.title', { name: user?.nickname ?? '' })}
      onCancel={onClose}
      footer={
        <Space>
          <Button onClick={onClose} disabled={saving}>
            {t('common.cancel')}
          </Button>
          <Button theme="solid" loading={saving} onClick={() => void handleSubmit()}>
            {t('users.reset.ok')}
          </Button>
        </Space>
      }
    >
      {systemError ? (
        <Banner
          type="danger"
          closeIcon={null}
          description={
            <span>
              {t('common:errors.system.message')} <AdminCopyButton error={systemError} />
            </span>
          }
          style={{ marginBottom: 12 }}
        />
      ) : null}
      <Typography.Paragraph type="secondary">{t('users.reset.content')}</Typography.Paragraph>
      <Typography.Text>{t('users.reset.password')}</Typography.Text>
      <Input
        mode="password"
        value={password}
        onChange={(value) => {
          setPassword(value);
          setFieldError('');
        }}
        placeholder={t('users.reset.password')}
        style={{ marginTop: 4 }}
      />
      {fieldError ? (
        <Typography.Text type="danger" size="small">
          {fieldError}
        </Typography.Text>
      ) : (
        <Typography.Text type="secondary" size="small">
          {t('users.reset.passwordHint')}
        </Typography.Text>
      )}
    </Modal>
  );
}
