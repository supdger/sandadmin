import { useState } from 'react';

import { Button, Input, SideSheet, Switch, TextArea, Toast, Typography } from '@douyinfe/semi-ui';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import { createRole, updateRole, type Role, type RoleWrite } from '@/api/v1/roles';
import { FIELD_NAME_WIDTH } from '@/components/fieldWidth';
import { useFormIdempotencyKey } from '@/hooks/useFormIdempotencyKey';
import { isAdminApiError } from '@/lib/adminClient';
import { adminErrorMessage } from '@/lib/adminError';

export interface RoleFormSheetProps {
  /** null 表示新建；传角色表示编辑。仅在打开期间挂载。 */
  role: Role | null;
  onClose: () => void;
  /** 保存成功后回调；`goPermission` 表示页面要接着打开权限编辑器。 */
  onSaved: (role: Role, options: { goPermission: boolean }) => void;
}

const NAME_MAX = 20;
const REMARK_MAX = 100;

/**
 * 添加/编辑角色抽屉。每次打开都是新挂载，useState 初始值
 * 就是全部种子。底部提供「仅保存」和「保存并设置权限」两种出口。
 */
export function RoleFormSheet({ role, onClose, onSaved }: RoleFormSheetProps) {
  const { t } = useTranslation('rbac');
  const queryClient = useQueryClient();

  const [name, setName] = useState(role?.name ?? '');
  const [remark, setRemark] = useState(role?.remark ?? '');
  const [enabled, setEnabled] = useState(role ? role.status === 'enabled' : true);
  const [nameError, setNameError] = useState('');
  const submitKey = useFormIdempotencyKey(role === null);

  const save = useMutation({
    mutationFn: (body: RoleWrite) =>
      role ? updateRole(role.id, body) : createRole(body, submitKey),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['rbac', 'roles'] });
    },
    onError: (error) => {
      // 字段级原因（重名、校验失败）留在输入框下；
      // 其余一律原样 toast。
      if (isAdminApiError(error)) {
        const fieldMessage = error.fields?.name?.[0];
        if (fieldMessage || error.code === 'ROLE_NAME_TAKEN') {
          setNameError(fieldMessage ?? error.message);
          return;
        }
      }
      Toast.error(adminErrorMessage(error, t('roles.loadFailed')));
    },
  });

  const trimmedName = name.trim();

  const submit = (goPermission: boolean) => {
    if (!trimmedName) {
      setNameError(t('roles.form.nameRequired'));
      return;
    }
    if (trimmedName.length > NAME_MAX) {
      setNameError(t('roles.form.nameTooLong'));
      return;
    }
    setNameError('');
    const body: RoleWrite = {
      name: trimmedName,
      remark: remark.trim() ? remark.trim() : null,
      status: enabled ? 'enabled' : 'disabled',
    };
    save.mutate(body, {
      onSuccess: (saved) => onSaved(saved, { goPermission }),
    });
  };

  return (
    <SideSheet
      visible
      onCancel={onClose}
      size="small"
      title={role ? t('roles.form.editTitle') : t('roles.form.addTitle')}
      footer={
        <div className="flex justify-end gap-2">
          <Button theme="light" disabled={save.isPending} onClick={onClose}>
            {t('common:actions.cancel')}
          </Button>
          <Button
            theme="light"
            disabled={save.isPending}
            onClick={() => submit(false)}
          >
            {save.isPending ? t('roles.form.saving') : t('roles.form.saveOnly')}
          </Button>
          <Button
            theme="solid"
            loading={save.isPending}
            onClick={() => submit(true)}
          >
            {t('roles.form.saveAndSet')}
          </Button>
        </div>
      }
    >
      <div className="flex flex-col gap-4">
        <div>
          <div className="mb-1">
            <Typography.Text strong>
              {t('roles.form.name')}
              <Typography.Text type="danger"> *</Typography.Text>
            </Typography.Text>
          </div>
          <Input
            style={{ width: FIELD_NAME_WIDTH }}
            value={name}
            maxLength={NAME_MAX}
            placeholder={t('roles.form.namePlaceholder')}
            onChange={(v) => {
              setName(v);
              if (nameError) setNameError('');
            }}
          />
          {nameError ? (
            <div className="mt-1 w-full">
              <Typography.Text
                type="danger"
                size="small"
                style={{ display: 'block', whiteSpace: 'nowrap' }}
              >
                {nameError}
              </Typography.Text>
            </div>
          ) : null}
        </div>
        <div>
          <div className="mb-1">
            <Typography.Text strong>{t('roles.form.remark')}</Typography.Text>
          </div>
          <TextArea
            value={remark}
            maxLength={REMARK_MAX}
            placeholder={t('roles.form.remarkPlaceholder')}
            autosize={{ minRows: 2, maxRows: 4 }}
            onChange={(v) => setRemark(v)}
          />
        </div>
        <div className="flex flex-col items-start gap-1">
          <Typography.Text strong>{t('roles.form.enabled')}</Typography.Text>
          <Switch
            checked={enabled}
            onChange={(checked) => setEnabled(checked)}
            aria-label={t('roles.form.enabled')}
          />
          <Typography.Text type="secondary" size="small">
            {t('roles.form.enabledHint')}
          </Typography.Text>
        </div>
      </div>
    </SideSheet>
  );
}
