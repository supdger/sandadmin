import { useEffect, useMemo, useRef, useState } from 'react';

import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import {
  Banner,
  Button,
  Form,
  Modal,
  SideSheet,
  Space,
  Toast,
  Typography,
} from '@douyinfe/semi-ui';
import type { FormApi } from '@douyinfe/semi-ui/lib/es/form/interface';

import { createUser, getUser, updateUser, type User, type UserWrite } from '@/api/v1/users';
import { isAdminSystemError, type AdminApiError } from '@/lib/adminClient';
import { adminErrorMessage } from '@/lib/adminError';
import { AdminCopyButton } from '@/components/AdminErrorNotice';
import { useEnabledPostOptions, useRoleOptions, useUserDeptTree } from '../shared/useOrgOptions';
import type { DeptTreeOption } from '../shared/deptTree';

interface FormValues {
  nickname?: string;
  phone?: string;
  username?: string;
  email?: string;
  dept_id?: string;
  post_id?: string;
  role_ids?: string[];
  password?: string;
  status?: boolean;
}

export interface UserFormSheetProps {
  visible: boolean;
  mode: 'create' | 'edit';
  /** 编辑时的行数据；打开后会拉详情回填。 */
  user: User | null;
  /** 新建时部门默认值（当前左侧选中的部门）。 */
  defaultDeptId?: string;
  onClose: () => void;
  onSaved: () => void;
}

const PHONE_PATTERN = /^1\d{10}$/;
const USERNAME_PATTERN = /^[A-Za-z0-9_]{4,20}$/;
const EMAIL_PATTERN = /^[^@\s]+@[^@\s]+\.[^@\s]+$/;

const FORM_KEYS = [
  'nickname',
  'phone',
  'username',
  'email',
  'dept_id',
  'post_id',
  'role_ids',
  'password',
  'status',
] as const;

/** 取消前判断是否有改动：按字段逐个序列化，避免键序差异。 */
function snapshotOf(values: FormValues): string {
  return FORM_KEYS.map((key) => JSON.stringify(values[key] ?? null)).join('|');
}

/** 编辑提交不带 password（契约 UserWrite 标了必填，见报告里的契约缺口）。 */
type UserUpdateBody = Omit<UserWrite, 'password'> & { password?: string };

export function UserFormSheet({ visible, mode, user, defaultDeptId, onClose, onSaved }: UserFormSheetProps) {
  const { t } = useTranslation('org');
  const [formApi, setFormApi] = useState<FormApi<FormValues> | null>(null);
  const [saving, setSaving] = useState(false);
  const [systemError, setSystemError] = useState<AdminApiError | null>(null);
  const initialSnapshot = useRef('');

  const isEdit = mode === 'edit';

  const detailQuery = useQuery({
    queryKey: ['v1', 'users', 'detail', user?.id],
    queryFn: () => getUser(user!.id),
    enabled: visible && isEdit && Boolean(user),
  });

  const deptTreeQuery = useUserDeptTree();
  const postsQuery = useEnabledPostOptions();
  const rolesQuery = useRoleOptions();

  const deptOptions = useMemo(() => deptTreeQuery.data?.options ?? [], [deptTreeQuery.data]);

  // 部门选项：编辑时若当前部门是停用的（不在启用清单里），补一条保证回显。
  const deptTreeOptions = useMemo<DeptTreeOption[]>(() => {
    const options = [...deptOptions];
    if (isEdit && user?.dept_id && !findOption(options, user.dept_id)) {
      options.push({
        value: user.dept_id,
        label: user.dept_name ?? user.dept_id,
        children: [],
      });
    }
    return options;
  }, [deptOptions, isEdit, user]);

  const postOptions = useMemo(() => {
    const list = (postsQuery.data?.items ?? []).map((p) => ({ value: p.id, label: p.name }));
    if (isEdit && user?.post_id && !list.some((o) => o.value === user.post_id)) {
      // 停用岗位不在启用清单里；详情里的岗位名在 detailQuery 返回后补。
      const postName = detailQuery.data?.post?.name;
      if (postName) {
        list.push({ value: user.post_id, label: postName });
      }
    }
    return list;
  }, [postsQuery.data, isEdit, user, detailQuery.data]);

  const roleOptions = useMemo(() => {
    const list: { value: string; label: string; remark?: string; disabled: boolean }[] = (
      rolesQuery.data?.items ?? []
    ).map((r) => ({
      value: r.id,
      label: r.name,
      remark: r.remark ?? undefined,
      disabled: r.status === 'disabled' && !user?.role_ids.includes(r.id),
    }));
    // 用户已分配但清单里没有的角色（停用角色被过滤等）补一条。
    if (isEdit && user) {
      for (const rid of user.role_ids) {
        if (!list.some((o) => o.value === rid)) {
          const name = detailQuery.data?.roles.find((r) => r.id === rid)?.name;
          if (name) {
            list.push({ value: rid, label: name, disabled: false });
          }
        }
      }
    }
    return list;
  }, [rolesQuery.data, isEdit, user, detailQuery.data]);

  useEffect(() => {
    if (!visible || !formApi) {
      return;
    }
    // systemError 只在提交失败时出现，提交时会先清掉；打开新表单由父级 key 重挂载保证干净。
    if (isEdit) {
      if (!detailQuery.data) {
        return;
      }
      const detail = detailQuery.data;
      const values: FormValues = {
        nickname: detail.nickname,
        phone: detail.phone,
        username: detail.username ?? '',
        email: detail.email ?? '',
        dept_id: detail.dept_id,
        post_id: detail.post_id ?? undefined,
        role_ids: detail.role_ids,
        status: detail.status === 'enabled',
      };
      formApi.setValues(values);
      initialSnapshot.current = snapshotOf(values);
    } else {
      const values: FormValues = {
        nickname: '',
        phone: '',
        username: '',
        email: '',
        dept_id: defaultDeptId,
        post_id: undefined,
        role_ids: [],
        password: '',
        status: true,
      };
      formApi.reset();
      formApi.setValues(values);
      initialSnapshot.current = snapshotOf(values);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [visible, isEdit, detailQuery.data, formApi]);

  const isDirty = () => {
    if (!formApi) {
      return false;
    }
    return snapshotOf(formApi.getValues()) !== initialSnapshot.current;
  };

  const requestClose = () => {
    if (isDirty()) {
      Modal.confirm({
        title: t('users.form.discardTitle'),
        content: t('users.form.discardContent'),
        okText: t('users.form.discard'),
        cancelText: t('users.form.keepEditing'),
        okType: 'danger',
        onOk: onClose,
      });
      return;
    }
    onClose();
  };

  const applyFieldErrors = (fields: Record<string, string[]> | undefined) => {
    if (!fields || !formApi) {
      return;
    }
    for (const [field, messages] of Object.entries(fields)) {
      const first = messages[0];
      if (first) {
        formApi.setError(field as keyof FormValues, first);
      }
    }
  };

  const handleSaveError = (error: unknown) => {
    if (isAdminSystemError(error)) {
      setSystemError(error);
      return;
    }
    if (error && typeof error === 'object' && 'fields' in error) {
      applyFieldErrors((error as { fields?: Record<string, string[]> }).fields);
    }
    Toast.error(adminErrorMessage(error, t('common:errors.system.message')));
  };

  const handleSubmit = async (values: FormValues) => {
    if (!formApi || saving) {
      return;
    }
    setSaving(true);
    setSystemError(null);
    try {
      const payload = {
        nickname: (values.nickname ?? '').trim(),
        phone: (values.phone ?? '').trim(),
        username: values.username?.trim() ? values.username.trim() : null,
        email: values.email?.trim() ? values.email.trim() : null,
        dept_id: values.dept_id as string,
        post_id: values.post_id || null,
        role_ids: values.role_ids ?? [],
        status: values.status ? ('enabled' as const) : ('disabled' as const),
      };
      if (isEdit && user) {
        const body: UserUpdateBody = { ...payload };
        await updateUser(user.id, body as UserWrite);
        Toast.success(t('users.form.saved'));
      } else {
        const body: UserWrite = { ...payload, password: values.password ?? '' };
        await createUser(body);
        Toast.success(
          t('users.form.added', { name: payload.nickname || user?.nickname || '' }),
        );
      }
      onSaved();
      onClose();
    } catch (error) {
      handleSaveError(error);
    } finally {
      setSaving(false);
    }
  };

  return (
    <SideSheet
      visible={visible}
      title={isEdit ? t('users.editUser') : t('users.addUser')}
      size="small"
      onCancel={requestClose}
      footer={
        <Space>
          <Button onClick={requestClose} disabled={saving}>
            {t('common.cancel')}
          </Button>
          <Button theme="solid" loading={saving} onClick={() => formApi?.submitForm()}>
            {saving ? t('common.saving') : t('common.save')}
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
      {isEdit && detailQuery.isPending ? (
        <Typography.Text type="secondary">{t('common:loading')}</Typography.Text>
      ) : (
        <Form<FormValues>
          getFormApi={(api) => setFormApi(api)}
          layout="vertical"
          onSubmit={handleSubmit}
        >
          <Typography.Title heading={6}>{t('users.form.sectionBasic')}</Typography.Title>
          <Form.Input
            field="nickname"
            label={t('users.form.nickname')}
            required
            maxLength={20}
            showClear
            rules={[
              { required: true, message: t('users.form.nicknameRequired') },
              { type: 'string', max: 20, message: t('users.form.nicknameTooLong') },
            ]}
          />
          <Form.Input
            field="phone"
            label={t('users.form.phone')}
            required
            showClear
            helpText={t('users.form.phoneHint')}
            rules={[
              { required: true, message: t('users.form.phoneRequired') },
              { pattern: PHONE_PATTERN, message: t('users.form.phoneInvalid') },
            ]}
          />
          <Form.TreeSelect
            field="dept_id"
            label={t('users.form.dept')}
            treeData={deptTreeOptions}
            filterTreeNode
            showClear
            rules={[{ required: true, message: t('users.form.deptRequired') }]}
          />
          <Form.Select
            field="post_id"
            label={t('users.form.post')}
            placeholder={t('common.unset')}
            showClear
            filter
            helpText={t('users.form.postHint')}
            optionList={postOptions}
          />
          <Form.Select
            field="role_ids"
            label={t('users.form.roles')}
            multiple
            filter
            showClear
            helpText={t('users.form.rolesHint')}
            optionList={roleOptions}
            renderOptionItem={(option) => (
              <div>
                <div>{option.label}</div>
                {option.remark ? (
                  <Typography.Text type="secondary" size="small">
                    {option.remark}
                  </Typography.Text>
                ) : null}
              </div>
            )}
            validator={(value) =>
              Array.isArray(value) && value.length > 0 ? '' : t('users.form.rolesRequired')
            }
          />

          <Typography.Title heading={6}>{t('users.form.sectionLogin')}</Typography.Title>
          <Form.Input
            field="username"
            label={t('users.form.username')}
            showClear
            helpText={t('users.form.usernameHint')}
            validator={(value) =>
              !value || USERNAME_PATTERN.test(value) ? '' : t('users.form.usernameInvalid')
            }
          />
          {!isEdit ? (
            <Form.Input
              field="password"
              label={t('users.form.password')}
              required
              mode="password"
              helpText={t('users.form.passwordHint')}
              rules={[
                { required: true, message: t('users.form.passwordRequired') },
                { type: 'string', min: 6, message: t('users.form.passwordTooShort') },
              ]}
            />
          ) : null}
          <Typography.Title heading={6}>{t('users.form.sectionMore')}</Typography.Title>
          <Form.Input
            field="email"
            label={t('users.form.email')}
            showClear
            helpText={t('users.form.emailHint')}
            validator={(value) => (!value || EMAIL_PATTERN.test(value) ? '' : t('users.form.emailInvalid'))}
          />
          <Form.Switch field="status" label={t('users.form.status')} helpText={t('users.form.statusHint')} />
        </Form>
      )}
    </SideSheet>
  );
}

function findOption(nodes: DeptTreeOption[], id: string): boolean {
  for (const node of nodes) {
    if (node.value === id) {
      return true;
    }
    if (node.children?.length && findOption(node.children, id)) {
      return true;
    }
  }
  return false;
}
