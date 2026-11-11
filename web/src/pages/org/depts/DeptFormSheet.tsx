import { useEffect, useMemo, useRef, useState } from 'react';

import { useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import { Banner, Button, Form, SideSheet, Space, Toast, withField } from '@douyinfe/semi-ui';
import type { FormApi } from '@douyinfe/semi-ui/lib/es/form/interface';

import { createDept, updateDept, type Dept, type DeptWrite } from '@/api/v1/depts';
import { isAdminApiError, isAdminSystemError, type AdminApiError } from '@/lib/adminClient';
import { adminErrorMessage } from '@/lib/adminError';
import { AdminCopyButton } from '@/components/AdminErrorNotice';
import { collectDescendantIds, deptTreeToOptions } from '../shared/deptTree';
import { UserOptionSelect } from '../shared/UserOptionSelect';
import type { DeptTreeOption } from '../shared/deptTree';

const FormLeaderSelect = withField(UserOptionSelect, {
  valueKey: 'value',
  onKeyChangeFnName: 'onChange',
});

export interface DeptFormSheetProps {
  visible: boolean;
  mode: 'create' | 'edit' | 'child';
  dept: Dept | null;
  /** 当前页面树，用于上级部门选项（编辑时剔除自身与下级）。 */
  tree: Dept[];
  onClose: () => void;
  onSaved: () => void;
}

interface FormValues {
  name?: string;
  parent_id?: string;
  leader_id?: string;
  phone?: string;
  status?: boolean;
}

export function DeptFormSheet({ visible, mode, dept, tree, onClose, onSaved }: DeptFormSheetProps) {
  const { t } = useTranslation('org');
  const queryClient = useQueryClient();
  const formApiRef = useRef<FormApi<FormValues> | null>(null);
  const [saving, setSaving] = useState(false);
  const [systemError, setSystemError] = useState<AdminApiError | null>(null);
  const [submitError, setSubmitError] = useState('');

  const isEdit = mode === 'edit' && Boolean(dept);
  const isChild = mode === 'child' && Boolean(dept);

  // 上级部门选项：编辑时剔除自身与下级，避免成环；不选即顶层。
  const parentOptions = useMemo<DeptTreeOption[]>(() => {
    let source = tree;
    if (isEdit && dept) {
      const excluded = collectDescendantIds(tree, dept.id);
      excluded.add(dept.id);
      const prune = (nodes: Dept[]): Dept[] =>
        nodes
          .filter((node) => !excluded.has(node.id))
          .map((node) => ({ ...node, children: prune(node.children) }));
      source = prune(tree);
    }
    return deptTreeToOptions(source);
  }, [tree, isEdit, dept]);

  useEffect(() => {
    if (!visible) {
      return;
    }
    const api = formApiRef.current;
    if (!api) {
      return;
    }
    api.reset();
    setSystemError(null);
    setSubmitError('');
    if (isEdit && dept) {
      api.setValues({
        name: dept.name,
        parent_id: dept.parent_id ?? undefined,
        leader_id: dept.leader_id ?? undefined,
        phone: dept.phone ?? undefined,
        status: dept.status === 'enabled',
      });
    } else {
      api.setValues({
        name: '',
        parent_id: isChild ? dept?.id : undefined,
        leader_id: undefined,
        phone: '',
        status: true,
      });
    }
  }, [visible, isEdit, isChild, dept]);

  const handleSubmit = async (values: FormValues) => {
    if (saving) {
      return;
    }
    setSaving(true);
    setSystemError(null);
    setSubmitError('');
    const parentId = values.parent_id || null;
    const body: DeptWrite = {
      parent_id: parentId,
      name: (values.name ?? '').trim(),
      leader_id: values.leader_id || null,
      phone: values.phone?.trim() || null,
      status: values.status === false ? 'disabled' : 'enabled',
    };
    try {
      const saved = isEdit && dept
        ? await updateDept(dept.id, { ...body, sort: dept.sort })
        : await createDept(body);
      Toast.success(t(isEdit ? 'depts.form.saved' : 'depts.form.added', { name: saved.name }));
      void queryClient.invalidateQueries({ queryKey: ['v1', 'depts'] });
      onSaved();
      onClose();
    } catch (error) {
      if (isAdminSystemError(error)) {
        setSystemError(error);
      } else if (isAdminApiError(error)) {
        const nameErr = error.fields?.name?.[0];
        const phoneErr = error.fields?.phone?.[0];
        if (nameErr) {
          formApiRef.current?.setError('name', nameErr);
        } else if (phoneErr) {
          formApiRef.current?.setError('phone', phoneErr);
        } else {
          setSubmitError(adminErrorMessage(error, t('common:errors.system.message')));
        }
      } else {
        setSubmitError(adminErrorMessage(error, t('common:errors.system.message')));
      }
    } finally {
      setSaving(false);
    }
  };

  return (
    <SideSheet
      visible={visible}
      width={520}
      title={
        isEdit
          ? t('depts.form.editTitle')
          : isChild
            ? t('depts.form.addChildTitle', { name: dept?.name ?? '' })
            : t('depts.form.addTitle')
      }
      onCancel={onClose}
      footer={
        <Space>
          <Button onClick={onClose} disabled={saving}>
            {t('common.thinkAgain')}
          </Button>
          <Button theme="solid" loading={saving} onClick={() => formApiRef.current?.submitForm()}>
            {isEdit ? t('common.save') : t('depts.form.create')}
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
      {submitError ? (
        <Banner type="danger" closeIcon={null} description={submitError} style={{ marginBottom: 12 }} />
      ) : null}
      <Form<FormValues>
        getFormApi={(api) => {
          formApiRef.current = api;
        }}
        onSubmit={handleSubmit}
        labelPosition="top"
      >
        <Form.Input
          field="name"
          label={t('depts.form.name')}
          rules={[{ required: true, message: t('depts.form.nameRequired') }]}
          maxLength={30}
          showClear
          autoFocus
        />
        <Form.TreeSelect
          field="parent_id"
          label={t('depts.form.parent')}
          treeData={parentOptions}
          filterTreeNode
          showClear
          placeholder={t('depts.form.parentPlaceholder')}
        />
        <FormLeaderSelect
          field="leader_id"
          label={t('depts.form.leader')}
          currentLabel={dept?.leader_name ?? undefined}
          placeholder={t('depts.form.leaderPlaceholder')}
        />
        <Form.Input
          field="phone"
          label={t('depts.form.phone')}
          maxLength={20}
          showClear
          rules={[{ pattern: /^[\d+\-()\s]{5,20}$/, message: t('depts.form.phoneInvalid') }]}
        />
        <Form.Switch field="status" label={t('depts.form.status')} />
      </Form>
    </SideSheet>
  );
}
