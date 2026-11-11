import { useEffect, useRef, useState } from 'react';

import { useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import { Banner, Button, Form, Modal, Space, Toast } from '@douyinfe/semi-ui';
import type { FormApi } from '@douyinfe/semi-ui/lib/es/form/interface';

import { createPost, updatePost, type Post, type PostWrite } from '@/api/v1/posts';
import type { UserStatus } from '@/api/v1/types';
import { isAdminApiError, isAdminSystemError, type AdminApiError } from '@/lib/adminClient';
import { adminErrorMessage } from '@/lib/adminError';
import { AdminCopyButton } from '@/components/AdminErrorNotice';

export interface PostFormModalProps {
  visible: boolean;
  post: Post | null;
  onClose: () => void;
  onSaved: () => void;
}

interface FormValues {
  name?: string;
  remark?: string;
  status?: boolean;
}

export function PostFormModal({ visible, post, onClose, onSaved }: PostFormModalProps) {
  const { t } = useTranslation('org');
  const queryClient = useQueryClient();
  const formApiRef = useRef<FormApi<FormValues> | null>(null);
  const [saving, setSaving] = useState(false);
  const [systemError, setSystemError] = useState<AdminApiError | null>(null);
  const [submitError, setSubmitError] = useState('');

  const isEdit = Boolean(post);

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
    api.setValues({
      name: post?.name ?? '',
      remark: post?.remark ?? '',
      status: post ? post.status === 'enabled' : true,
    });
  }, [visible, post]);

  const handleSubmit = async (values: FormValues) => {
    if (saving) {
      return;
    }
    setSaving(true);
    setSystemError(null);
    setSubmitError('');
    const body: PostWrite = {
      name: (values.name ?? '').trim(),
      remark: values.remark?.trim() || null,
      status: (values.status === false ? 'disabled' : 'enabled') as UserStatus,
    };
    try {
      if (isEdit && post) {
        await updatePost(post.id, { ...body, sort: post.sort });
        Toast.success(t('posts.form.saved'));
      } else {
        await createPost(body);
        Toast.success(t('posts.form.added'));
      }
      void queryClient.invalidateQueries({ queryKey: ['v1', 'posts'] });
      onSaved();
      onClose();
    } catch (error) {
      if (isAdminSystemError(error)) {
        setSystemError(error);
      } else if (isAdminApiError(error)) {
        const nameErr = error.fields?.name?.[0];
        if (nameErr) {
          formApiRef.current?.setError('name', nameErr);
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
    <Modal
      visible={visible}
      title={isEdit ? t('posts.form.editTitle') : t('posts.form.addTitle')}
      onCancel={onClose}
      footer={
        <Space>
          <Button onClick={onClose} disabled={saving}>
            {t('common.thinkAgain')}
          </Button>
          <Button theme="solid" loading={saving} onClick={() => formApiRef.current?.submitForm()}>
            {isEdit ? t('common.save') : t('posts.form.create')}
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
          label={t('posts.form.name')}
          placeholder={t('posts.form.name')}
          rules={[{ required: true, message: t('posts.form.nameRequired') }]}
          maxLength={30}
          showClear
          autoFocus
        />
        <Form.TextArea
          field="remark"
          label={t('posts.form.remark')}
          maxLength={100}
          showClear
          autosize={{ minRows: 2, maxRows: 4 }}
        />
        <Form.Switch field="status" label={t('posts.form.status')} />
      </Form>
    </Modal>
  );
}
