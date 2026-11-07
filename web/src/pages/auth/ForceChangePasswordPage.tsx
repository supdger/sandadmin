import { useMemo, useRef, useState } from 'react';

import { Banner, Button, Card, Form, Toast, Typography } from '@douyinfe/semi-ui';
import { IconSemiLogo, IconTickCircle } from '@douyinfe/semi-icons';
import type { FormApi } from '@douyinfe/semi-ui/lib/es/form';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Navigate, useNavigate } from 'react-router';
import { z } from 'zod';

import { forceChangePassword, getPasswordPolicy } from '@/api/auth';
import { Loading } from '@/components/Loading';
import { env } from '@/config/env';
import { useMe } from '@/hooks/useMe';
import { ApiError } from '@/lib/apiClient';
import { clearToken, getToken } from '@/lib/authToken';

export default function ForceChangePasswordPage() {
  const { t } = useTranslation('auth');
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const token = getToken();
  const me = useMe();
  const policyQuery = useQuery({ queryKey: ['passwordPolicy'], queryFn: getPasswordPolicy });
  const formApi = useRef<FormApi<Record<string, unknown>> | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const policy = policyQuery.data;

  const schema = useMemo(() => {
    if (!policy) return null;
    return z
      .object({
        oldPassword: z.string().min(1, t('forceChange.required')),
        newPassword: z
          .string()
          .min(policy.minLength, t('forceChange.rules.minLength', { count: policy.minLength }))
          .refine(
            (v) => !policy.requireMixedCase || (/[a-z]/.test(v) && /[A-Z]/.test(v)),
            t('forceChange.rules.mixedCase'),
          )
          .refine((v) => !policy.requireDigit || /\d/.test(v), t('forceChange.rules.digit'))
          .refine(
            (v) => !policy.requireSymbol || /[^A-Za-z0-9]/.test(v),
            t('forceChange.rules.symbol'),
          ),
        confirmPassword: z.string().min(1, t('forceChange.required')),
      })
      .refine((d) => d.newPassword === d.confirmPassword, {
        path: ['confirmPassword'],
        message: t('forceChange.mismatch'),
      });
  }, [policy, t]);

  if (!token) {
    return <Navigate to="/login" replace />;
  }
  if (me.isPending || policyQuery.isPending) {
    return <Loading fullPage />;
  }
  if (me.isError) {
    clearToken();
    return <Navigate to="/login" replace />;
  }

  const handleSubmit = async (values: Record<string, unknown>) => {
    if (!schema) return;
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

    setSubmitting(true);
    setError(null);
    try {
      await forceChangePassword(parsed.data.oldPassword, parsed.data.newPassword);
      Toast.success(t('forceChange.success'));
      clearToken();
      queryClient.clear();
      navigate('/login', { replace: true });
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t('forceChange.required'));
      if (e instanceof ApiError && e.fields) {
        for (const [field, messages] of Object.entries(e.fields)) {
          formApi.current?.setError(field, messages[0] ?? '');
        }
      }
    } finally {
      setSubmitting(false);
    }
  };

  const rules: string[] = policy
    ? [
        t('forceChange.rules.minLength', { count: policy.minLength }),
        ...(policy.requireMixedCase ? [t('forceChange.rules.mixedCase')] : []),
        ...(policy.requireDigit ? [t('forceChange.rules.digit')] : []),
        ...(policy.requireSymbol ? [t('forceChange.rules.symbol')] : []),
        ...(policy.historyCheckCount > 0
          ? [t('forceChange.rules.history', { count: policy.historyCheckCount })]
          : []),
      ]
    : [];

  return (
    <div
      className="flex min-h-screen items-center justify-center"
      style={{ background: 'var(--semi-color-bg-0)' }}
    >
      <Card style={{ width: 440 }} shadows="hover">
        <div className="flex flex-col items-center gap-2 pb-4">
          {env.appLogo ? (
            <img src={env.appLogo} alt={env.appName} style={{ height: 44 }} />
          ) : (
            <IconSemiLogo size="extra-large" style={{ color: 'var(--semi-color-primary)' }} />
          )}
          <Typography.Title heading={4} style={{ margin: 0 }}>
            {t('forceChange.title')}
          </Typography.Title>
          <Typography.Text type="secondary" size="small">
            {t('forceChange.description')}
          </Typography.Text>
        </div>

        {policy ? (
          <div className="pb-4">
            <Typography.Text strong size="small">
              {t('forceChange.policyTitle')}
            </Typography.Text>
            <ul className="m-0 flex flex-col gap-1 pt-2" style={{ listStyle: 'none', padding: 0 }}>
              {rules.map((rule) => (
                <li key={rule} className="flex items-center gap-1">
                  <IconTickCircle style={{ color: 'var(--semi-color-success)' }} />
                  <Typography.Text size="small">{rule}</Typography.Text>
                </li>
              ))}
            </ul>
          </div>
        ) : null}

        {error ? (
          <Banner
            type="danger"
            fullMode={false}
            description={error}
            style={{ marginBottom: 12 }}
            closeIcon={null}
          />
        ) : null}

        <Form
          onSubmit={(values) => void handleSubmit(values as Record<string, unknown>)}
          getFormApi={(api) => {
            formApi.current = api as FormApi<Record<string, unknown>>;
          }}
        >
          <Form.Input
            field="oldPassword"
            type="password"
            label={t('forceChange.oldPassword')}
            placeholder={t('forceChange.oldPasswordPlaceholder')}
            rules={[{ required: true, message: t('forceChange.required') }]}
          />
          <Form.Input
            field="newPassword"
            type="password"
            label={t('forceChange.newPassword')}
            placeholder={t('forceChange.newPasswordPlaceholder')}
            rules={[{ required: true, message: t('forceChange.required') }]}
          />
          <Form.Input
            field="confirmPassword"
            type="password"
            label={t('forceChange.confirmPassword')}
            placeholder={t('forceChange.confirmPasswordPlaceholder')}
            rules={[{ required: true, message: t('forceChange.required') }]}
          />
          <Button htmlType="submit" type="primary" theme="solid" block loading={submitting}>
            {t('forceChange.submit')}
          </Button>
        </Form>
      </Card>
    </div>
  );
}
