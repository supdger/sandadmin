import { useEffect, useRef, useState } from 'react';
// SAND-DEV-BEGIN
import { useCallback } from 'react';
// SAND-DEV-END

import { Banner, Button, Card, Form, Modal, Typography } from '@douyinfe/semi-ui';
// SAND-DEV-BEGIN
import { Spin } from '@douyinfe/semi-ui';
// SAND-DEV-END
import { IconSemiLogo } from '@douyinfe/semi-icons';
import type { FormApi } from '@douyinfe/semi-ui/lib/es/form';
import { useTranslation } from 'react-i18next';
import { useLocation, useNavigate, useSearchParams } from 'react-router';
import { z } from 'zod';

import { login } from '@/api/auth';
// SAND-DEV-BEGIN
import { devLogin, getDevLoginAvailable } from '@/api/auth';
import type { DevLoginAvailability } from '@/api/auth';
// SAND-DEV-END
import { env } from '@/config/env';
import { apiErrorMessage, ApiError } from '@/lib/apiClient';
import { ClickCaptcha } from './ClickCaptcha';

/** Business code the mock/API uses to demand a captcha before retrying. */
const CODE_NEED_CAPTCHA = 4001;

// SAND-DEV-BEGIN
// The password box is pre-filled with dots that only signal "a password is
// set on the backend" — it is never submitted, the dev-login endpoint needs
// no password at all.
const DEV_PASSWORD_PLACEHOLDER = '••••••••';
// SAND-DEV-END

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
  // SAND-DEV-BEGIN
  const autoEnterOnce = useRef(false);
  // SAND-DEV-END

  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [needCaptcha, setNeedCaptcha] = useState(false);
  const [captchaEpoch, setCaptchaEpoch] = useState(0);
  const [verified, setVerified] = useState<VerifiedCaptcha | null>(null);
  // SAND-DEV-BEGIN
  // null while the availability request is in flight — nothing renders until
  // the backend has answered, so production never flashes dev elements.
  const [dev, setDev] = useState<DevLoginAvailability | null>(null);
  const [isPlainLogin, setIsPlainLogin] = useState(false);
  // SAND-DEV-END

  const stateRedirect = (location.state as { redirect?: string } | null)?.redirect;
  const redirectTo = stateRedirect ?? searchParams.get('redirect') ?? '/';

  // SAND-DEV-BEGIN
  const devMode = dev?.available === true;
  const devEntry = devMode && !isPlainLogin;
  // SAND-DEV-END

  const schema = z.object({
    username: z.string().trim().min(1, t('login.usernamePlaceholder')),
    password: z.string().min(1, t('login.passwordPlaceholder')),
  });

  // SAND-DEV-BEGIN
  const handleDevEnter = useCallback(async () => {
    setSubmitting(true);
    setError(null);
    try {
      const res = await devLogin();
      if (res.must_change_password) {
        navigate('/force-change-password', { replace: true });
      } else {
        navigate(redirectTo, { replace: true });
      }
    } catch (e) {
      setError(apiErrorMessage(e, t('login.devEnterFailed')));
      // A 404 means the backend has dev login turned off (e.g. production) —
      // fall back to the plain production form instead of a dead button.
      if (e instanceof ApiError && e.status === 404) {
        setDev({ available: false });
      }
    } finally {
      setSubmitting(false);
    }
  }, [navigate, redirectTo, t]);

  useEffect(() => {
    let cancelled = false;
    void getDevLoginAvailable().then((res) => {
      if (!cancelled) {
        setDev(res);
      }
    });
    return () => {
      cancelled = true;
    };
  }, []);

  // ?dev=1 performs the one-click entry once after availability is known;
  // a failure just leaves the button for a manual retry.
  useEffect(() => {
    if (searchParams.get('dev') === '1' && devEntry && !autoEnterOnce.current) {
      autoEnterOnce.current = true;
      void handleDevEnter();
    }
  }, [devEntry, searchParams, handleDevEnter]);
  // SAND-DEV-END

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
      if (res.must_change_password) {
        navigate('/force-change-password', { replace: true });
      } else {
        navigate(redirectTo, { replace: true });
      }
    } catch (e) {
      const message = apiErrorMessage(e, t('login.captchaFailed'));
      setError(message);
      let captchaNeeded = e instanceof ApiError && e.code === CODE_NEED_CAPTCHA;
      // SAND-DEV-BEGIN
      // Dev mode never shows a captcha, no matter how many attempts fail.
      captchaNeeded = captchaNeeded && !devMode;
      // SAND-DEV-END
      if (captchaNeeded) {
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

  // SAND-DEV-BEGIN
  const handleOtherAccount = () => {
    setIsPlainLogin(true);
    setError(null);
  };
  // SAND-DEV-END

  const showForgot = () => {
    Modal.info({
      title: t('login.forgotTitle'),
      content: t('login.forgotContent'),
    });
  };

  // SAND-DEV-BEGIN
  if (dev === null) {
    return (
      <div
        className="flex min-h-screen items-center justify-center"
        style={{ background: 'var(--semi-color-bg-0)' }}
      >
        <Spin size="large" />
      </div>
    );
  }
  // SAND-DEV-END

  let captchaVisible = needCaptcha;
  let showPlainSubmit = true;
  // SAND-DEV-BEGIN
  captchaVisible = captchaVisible && !devMode;
  showPlainSubmit = !devEntry;
  // SAND-DEV-END

  return (
    <div
      className="flex min-h-screen flex-col"
      style={{ background: 'var(--semi-color-bg-0)' }}
    >
      {/* SAND-DEV-BEGIN */}
      {devMode ? (
        <Banner
          type="warning"
          fullMode
          description={t('login.devBanner')}
          closeIcon={null}
        />
      ) : null}
      {/* SAND-DEV-END */}

      <div className="flex flex-1 items-center justify-center">
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
            /* SAND-DEV-BEGIN */
            key={devEntry ? 'dev' : 'plain'}
            initValues={
              devEntry
                ? ({
                    username: dev?.username ?? '',
                    password: DEV_PASSWORD_PLACEHOLDER,
                  } as Record<string, unknown>)
                : undefined
            }
            /* SAND-DEV-END */
            onSubmit={(values) => {
              // SAND-DEV-BEGIN
              if (devEntry) {
                void handleDevEnter();
                return;
              }
              // SAND-DEV-END
              void handleSubmit(values as Record<string, unknown>);
            }}
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

            {captchaVisible ? (
              <div className="pb-2">
                <ClickCaptcha
                  key={captchaEpoch}
                  onVerified={(captchaId, points) => setVerified({ captchaId, points })}
                />
              </div>
            ) : null}

            {/* SAND-DEV-BEGIN */}
            {devEntry ? (
              <>
                <Button
                  autoFocus
                  htmlType="submit"
                  type="primary"
                  theme="solid"
                  block
                  loading={submitting}
                >
                  {submitting ? t('login.devEntering') : t('login.devEnter')}
                </Button>
                <div className="flex justify-center pt-3">
                  <Button theme="borderless" size="small" onClick={handleOtherAccount}>
                    {t('login.devOtherAccount')}
                  </Button>
                </div>
              </>
            ) : null}
            {/* SAND-DEV-END */}
            {showPlainSubmit ? (
              <>
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
              </>
            ) : null}
          </Form>
        </Card>
      </div>
    </div>
  );
}
