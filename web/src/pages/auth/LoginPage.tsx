import { useCallback, useEffect, useRef, useState } from 'react';

import { Banner, Button, Card, Form, Modal, Spin, Typography } from '@douyinfe/semi-ui';
import { IconSemiLogo } from '@douyinfe/semi-icons';
import type { FormApi } from '@douyinfe/semi-ui/lib/es/form';
import { useTranslation } from 'react-i18next';
import { useLocation, useNavigate, useSearchParams } from 'react-router';
import { z } from 'zod';

import { devLogin, getDevLoginAvailable, login } from '@/api/auth';
import type { DevLoginAvailability } from '@/api/auth';
import { env } from '@/config/env';
import { useSiteSettings } from '@/hooks/useSiteSettings';
import { apiErrorMessage, ApiError } from '@/lib/apiClient';
import { ClickCaptcha } from './ClickCaptcha';

/** Business code the mock/API uses to demand a captcha before retrying. */
const CODE_NEED_CAPTCHA = 4001;

// The password box is pre-filled with dots that only signal "a password is
// set on the backend" — it is never submitted, the dev-login endpoint needs
// no password at all.
const DEV_PASSWORD_PLACEHOLDER = '••••••••';

interface VerifiedCaptcha {
  captchaId: string;
  points: number[];
}

export default function LoginPage() {
  const { t } = useTranslation('auth');
  const navigate = useNavigate();
  const location = useLocation();
  const [searchParams] = useSearchParams();
  // 系统设置改了名字/标志，登录页同源立即换。
  const site = useSiteSettings();
  const appName = site.appName || env.appName;
  const appLogo = site.appLogo ?? env.appLogo;
  const formApi = useRef<FormApi<Record<string, unknown>> | null>(null);
  const autoEnterOnce = useRef(false);

  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    document.title = appName;
  }, [appName]);
  const [needCaptcha, setNeedCaptcha] = useState(false);
  const [captchaEpoch, setCaptchaEpoch] = useState(0);
  const [verified, setVerified] = useState<VerifiedCaptcha | null>(null);
  // null while the availability request is in flight — nothing renders until
  // the backend has answered, so production never flashes dev elements.
  const [dev, setDev] = useState<DevLoginAvailability | null>(null);
  const [isPlainLogin, setIsPlainLogin] = useState(false);

  const stateRedirect = (location.state as { redirect?: string } | null)?.redirect;
  const redirectTo = stateRedirect ?? searchParams.get('redirect') ?? '/';

  const devMode = dev?.available === true;
  const devEntry = devMode && !isPlainLogin;

  const schema = z.object({
    username: z.string().trim().min(1, t('login.usernamePlaceholder')),
    password: z.string().min(1, t('login.passwordPlaceholder')),
  });

  const handleDevEnter = useCallback(async () => {
    setSubmitting(true);
    setError(null);
    try {
      const res = await devLogin();
      if (res.mustChangePassword) {
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
      const message = apiErrorMessage(e, t('login.captchaFailed'));
      setError(message);
      // Dev mode never shows a captcha, no matter how many attempts fail.
      if (e instanceof ApiError && e.code === CODE_NEED_CAPTCHA && !devMode) {
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

  const handleOtherAccount = () => {
    setIsPlainLogin(true);
    setError(null);
  };

  const showForgot = () => {
    Modal.info({
      title: t('login.forgotTitle'),
      content: t('login.forgotContent'),
    });
  };

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

  return (
    <div
      className="flex min-h-screen flex-col"
      style={{ background: 'var(--semi-color-bg-0)' }}
    >
      {devMode ? (
        <Banner
          type="warning"
          fullMode
          description={t('login.devBanner')}
          closeIcon={null}
        />
      ) : null}

      <div className="flex flex-1 items-center justify-center">
        <Card style={{ width: 400 }} shadows="hover">
          <div className="flex flex-col items-center gap-2 pb-4">
            {appLogo ? (
              <img src={appLogo} alt={appName} style={{ height: 44 }} />
            ) : (
              <IconSemiLogo size="extra-large" style={{ color: 'var(--semi-color-primary)' }} />
            )}
            <Typography.Title heading={4} style={{ margin: 0 }}>
              {appName}
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
            key={devEntry ? 'dev' : 'plain'}
            initValues={
              devEntry
                ? ({
                    username: dev?.username ?? '',
                    password: DEV_PASSWORD_PLACEHOLDER,
                  } as Record<string, unknown>)
                : undefined
            }
            onSubmit={(values) =>
              devEntry
                ? void handleDevEnter()
                : void handleSubmit(values as Record<string, unknown>)
            }
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

            {needCaptcha && !devMode ? (
              <div className="pb-2">
                <ClickCaptcha
                  key={captchaEpoch}
                  onVerified={(captchaId, points) => setVerified({ captchaId, points })}
                />
              </div>
            ) : null}

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
            ) : (
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
            )}
          </Form>
        </Card>
      </div>
    </div>
  );
}
