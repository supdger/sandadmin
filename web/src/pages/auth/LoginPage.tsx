import { useRef, useState } from 'react';

import { Banner, Button, Card, Form, Modal, Typography } from '@douyinfe/semi-ui';
import { IconSemiLogo } from '@douyinfe/semi-icons';
import type { FormApi } from '@douyinfe/semi-ui/lib/es/form';
import { useTranslation } from 'react-i18next';
import { useLocation, useNavigate, useSearchParams } from 'react-router';
import { z } from 'zod';

import { login } from '@/api/auth';
import { env } from '@/config/env';
import { ApiError } from '@/lib/apiClient';
import { ClickCaptcha } from './ClickCaptcha';

/** Business code the mock/API uses to demand a captcha before retrying. */
const CODE_NEED_CAPTCHA = 4001;

interface VerifiedCaptcha {
  captchaId: string;
  points: number[];
}

export default function LoginPage() {
  const { t } = useTranslation('auth');
  const navigate = useNavigate();
  const location = useLocation();
  const [searchParams] = useSearchParams();
  const formApi = useRef<FormApi<Record<string, unknown>> | null>(null);

  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [needCaptcha, setNeedCaptcha] = useState(false);
  const [captchaEpoch, setCaptchaEpoch] = useState(0);
  const [verified, setVerified] = useState<VerifiedCaptcha | null>(null);

  const stateRedirect = (location.state as { redirect?: string } | null)?.redirect;
  const redirectTo = stateRedirect ?? searchParams.get('redirect') ?? '/';

  const schema = z.object({
    username: z.string().trim().min(1, t('login.usernamePlaceholder')),
    password: z.string().min(1, t('login.passwordPlaceholder')),
  });

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

    setSubmitting(true);
    setError(null);
    try {
      const res = await login({
        username: parsed.data.username,
        password: parsed.data.password,
        ...(verified
          ? { captchaId: verified.captchaId, captchaAnswer: verified.points }
          : {}),
      });
      if (res.mustChangePassword) {
        navigate('/force-change-password', { replace: true });
      } else {
        navigate(redirectTo, { replace: true });
      }
    } catch (e) {
      const message = e instanceof ApiError ? e.message : t('login.captchaFailed');
      setError(message);
      if (e instanceof ApiError && e.code === CODE_NEED_CAPTCHA) {
        setNeedCaptcha(true);
      }
      if (needCaptcha) {
        // The consumed captcha is single-use — ask for a fresh one.
        setVerified(null);
        setCaptchaEpoch((n) => n + 1);
      }
    } finally {
      setSubmitting(false);
    }
  };

  const showForgot = () => {
    Modal.info({
      title: t('login.forgotTitle'),
      content: t('login.forgotContent'),
    });
  };

  return (
    <div
      className="flex min-h-screen items-center justify-center"
      style={{ background: 'var(--semi-color-bg-0)' }}
    >
      <Card style={{ width: 400 }} shadows="hover">
        <div className="flex flex-col items-center gap-2 pb-4">
          {env.appLogo ? (
            <img src={env.appLogo} alt={env.appName} style={{ height: 44 }} />
          ) : (
            <IconSemiLogo size="extra-large" style={{ color: 'var(--semi-color-primary)' }} />
          )}
          <Typography.Title heading={4} style={{ margin: 0 }}>
            {env.appName}
          </Typography.Title>
          <Typography.Text type="secondary" size="small">
            {t('login.subtitle')}
          </Typography.Text>
        </div>

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
            field="username"
            label={t('login.username')}
            placeholder={t('login.usernamePlaceholder')}
            rules={[{ required: true, message: t('login.usernamePlaceholder') }]}
          />
          <Form.Input
            field="password"
            type="password"
            label={t('login.password')}
            placeholder={t('login.passwordPlaceholder')}
            rules={[{ required: true, message: t('login.passwordPlaceholder') }]}
          />

          {needCaptcha ? (
            <div className="pb-2">
              <ClickCaptcha
                key={captchaEpoch}
                onVerified={(captchaId, points) => setVerified({ captchaId, points })}
              />
            </div>
          ) : null}

          <Button
            htmlType="submit"
            type="primary"
            theme="solid"
            block
            loading={submitting}
          >
            {submitting ? t('login.submitting') : t('login.submit')}
          </Button>

          <div className="flex justify-end pt-3">
            <Button theme="borderless" size="small" onClick={showForgot}>
              {t('login.forgot')}
            </Button>
          </div>
        </Form>
      </Card>
    </div>
  );
}
