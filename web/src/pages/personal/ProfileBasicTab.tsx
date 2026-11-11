import { useRef, useState } from 'react';

import { Avatar, Button, Card, Descriptions, Form, Skeleton, Toast, Typography } from '@douyinfe/semi-ui';
import type { FormApi } from '@douyinfe/semi-ui/lib/es/form';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';

import { getMyProfile, putMyProfile, uploadFile, type UserProfile } from '@/api/profile';
import { useMe } from '@/hooks/useMe';
import { apiErrorMessage, ApiError } from '@/lib/apiClient';
import { ErrorState } from '@/components/ErrorState';

const PROFILE_QUERY_KEY = ['me', 'profile'] as const;
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const AVATAR_MAX_BYTES = 2 * 1024 * 1024;
const AVATAR_TYPES = ['image/jpeg', 'image/png'];

export interface ProfileBasicTabProps {
  onDirtyChange: (dirty: boolean) => void;
}

/**
 * Editable profile: avatar (uploaded first via POST /files, id saved through
 * PUT /me/profile), nickname, phone, email — plus read-only account context.
 */
export function ProfileBasicTab({ onDirtyChange }: ProfileBasicTabProps) {
  const { t } = useTranslation('profile');
  const queryClient = useQueryClient();
  const me = useMe();
  const profile = useQuery({ queryKey: PROFILE_QUERY_KEY, queryFn: getMyProfile });
  const formApi = useRef<FormApi<Record<string, unknown>> | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [saving, setSaving] = useState(false);
  const [avatarPreview, setAvatarPreview] = useState<string | null>(null);
  const [avatarError, setAvatarError] = useState<string | null>(null);

  const data = profile.data;
  // avatar_file_id stays out of the form state — Semi's setValues replaces
  // the whole value map and would wipe the text fields.
  const [pickedAvatarId, setPickedAvatarId] = useState<string | null>(null);
  const effectiveAvatarId = pickedAvatarId ?? data?.avatar_file_id ?? null;

  const schema = z.object({
    nickname: z
      .string()
      .trim()
      .min(1, t('basic.nicknameRequired'))
      .max(20, t('basic.nicknameTooLong')),
    phone: z.string().regex(/^\d{11}$/, t('basic.phoneInvalid')),
    email: z
      .string()
      .refine((v) => v === '' || EMAIL_PATTERN.test(v), t('basic.emailInvalid')),
  });

  const roleLabel = me.data?.is_super
    ? t('basic.roleSuper')
    : me.data?.is_admin
      ? t('basic.roleAdmin')
      : t('basic.roleMember');

  const readOnlyRows = [
    {
      key: t('basic.username'),
      value: me.data?.username || t('basic.usernameEmpty'),
    },
    { key: t('basic.dept'), value: me.data?.dept_name || t('basic.deptEmpty') },
    { key: t('basic.role'), value: roleLabel },
  ];

  const handleAvatarPick = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) {
      return;
    }
    if (!AVATAR_TYPES.includes(file.type)) {
      setAvatarError(t('basic.avatarType'));
      return;
    }
    if (file.size > AVATAR_MAX_BYTES) {
      setAvatarError(t('basic.avatarTooLarge'));
      return;
    }
    setAvatarError(null);
    try {
      const uploaded = await uploadFile(file);
      setPickedAvatarId(uploaded.id);
      onDirtyChange(true);
      if (typeof URL.createObjectURL === 'function') {
        setAvatarPreview(URL.createObjectURL(file));
      }
    } catch (e) {
      setAvatarError(apiErrorMessage(e, t('basic.saveFailed')));
    }
  };

  const handleSubmit = async (values: Record<string, unknown>) => {
    const parsed = schema.safeParse(values);
    if (!parsed.success) {
      const shown = new Set<string>();
      for (const issue of parsed.error.issues) {
        const field = String(issue.path[0]);
        if (!shown.has(field)) {
          shown.add(field);
          formApi.current?.setError(field, issue.message);
        }
      }
      return;
    }
    setSaving(true);
    try {
      const saved = await putMyProfile({
        nickname: parsed.data.nickname,
        phone: parsed.data.phone,
        email: parsed.data.email === '' ? null : parsed.data.email,
        avatar_file_id: effectiveAvatarId,
      });
      queryClient.setQueryData<UserProfile>(PROFILE_QUERY_KEY, saved);
      void queryClient.invalidateQueries({ queryKey: ['me'] });
      Toast.success(t('basic.saved'));
      onDirtyChange(false);
      setAvatarPreview(null);
      setPickedAvatarId(null);
    } catch (e) {
      const fields = e instanceof ApiError ? e.fields : undefined;
      if (fields && Object.keys(fields).length > 0) {
        for (const [field, messages] of Object.entries(fields)) {
          if (field === 'avatar_file_id') {
            setAvatarError(messages[0] ?? '');
          } else {
            formApi.current?.setError(field, messages[0] ?? '');
          }
        }
      } else {
        Toast.error(apiErrorMessage(e, t('basic.saveFailed')));
      }
    } finally {
      setSaving(false);
    }
  };

  if (profile.isPending) {
    return (
      <Card style={{ maxWidth: 640 }}>
        <Skeleton loading placeholder={<Skeleton.Paragraph rows={6} />}>
          <div />
        </Skeleton>
      </Card>
    );
  }

  if (profile.isError || !data) {
    return <ErrorState kind="error" error={profile.error} onRetry={() => void profile.refetch()} />;
  }

  const avatarSrc = avatarPreview ?? data.avatar ?? me.data?.avatar ?? undefined;
  const avatarName = data.nickname || me.data?.nickname || '';

  return (
    <div className="flex max-w-3xl flex-col gap-4">
      <Card>
        <Form
          initValues={{
            nickname: data.nickname,
            phone: data.phone,
            email: data.email ?? '',
          }}
          labelPosition="top"
          onSubmit={(values) => void handleSubmit(values)}
          onValueChange={() => onDirtyChange(true)}
          getFormApi={(api) => {
            formApi.current = api as unknown as FormApi<Record<string, unknown>>;
          }}
        >
          <div className="flex items-center gap-4 pb-4">
            <Avatar size="large" src={avatarSrc} alt={avatarName}>
              {avatarName.slice(0, 1)}
            </Avatar>
            <div className="flex flex-col gap-1">
              <div>
                <Button size="small" onClick={() => fileInputRef.current?.click()}>
                  {t('basic.changeAvatar')}
                </Button>
              </div>
              <Typography.Text type="tertiary" size="small">
                {t('basic.avatarHint')}
              </Typography.Text>
              {avatarError ? (
                <Typography.Text type="danger" size="small">
                  {avatarError}
                </Typography.Text>
              ) : null}
            </div>
            <input
              ref={fileInputRef}
              type="file"
              accept="image/jpeg,image/png"
              className="hidden"
              onChange={(e) => void handleAvatarPick(e)}
            />
          </div>

          <Form.Input
            field="nickname"
            label={t('basic.nickname')}
            placeholder={t('basic.nicknamePlaceholder')}
            maxLength={20}
            rules={[{ required: true, message: t('basic.nicknameRequired') }]}
          />
          <Form.Input
            field="phone"
            label={t('basic.phone')}
            placeholder={t('basic.phonePlaceholder')}
            prefix="+86"
            maxLength={13}
            rules={[{ required: true, message: t('basic.phoneInvalid') }]}
          />
          <Form.Input
            field="email"
            label={t('basic.email')}
            placeholder={t('basic.emailPlaceholder')}
            rules={[{ required: false }]}
          />
          <Button htmlType="submit" type="primary" theme="solid" loading={saving}>
            {t('basic.save')}
          </Button>
        </Form>
      </Card>

      <Card title={t('basic.readOnlyTitle')}>
        <Descriptions size="small" data={readOnlyRows} />
        <Typography.Text type="tertiary" size="small">
          {t('basic.contactAdmin')}
        </Typography.Text>
      </Card>
    </div>
  );
}
