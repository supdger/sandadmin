import { useEffect, useMemo, useRef, useState } from 'react';

import {
  Banner,
  Button,
  Card,
  Descriptions,
  Form,
  Progress,
  Steps,
  Typography,
} from '@douyinfe/semi-ui';
import { IconSemiLogo, IconTickCircle } from '@douyinfe/semi-icons';
import type { FormApi } from '@douyinfe/semi-ui/lib/es/form';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';
import { z } from 'zod';

import { getInstallStatus, startInstall } from '@/api/install';
import { getJob, type JobRun } from '@/api/jobs';
import { getPasswordPolicy } from '@/api/auth';
import { Loading } from '@/components/Loading';
import { ResultPage } from '@/components/ResultPage';
import { env } from '@/config/env';
import { apiGet, ApiError } from '@/lib/apiClient';

interface EnvCheckItem {
  key: string;
  name: string;
  ok: boolean;
  detail: string;
}

interface InstallDraft {
  adminUsername: string;
  adminNickname: string;
  adminPassword: string;
  siteName: string;
}

const STEP_ENV = 0;
const STEP_ADMIN = 1;
const STEP_SITE = 2;
const STEP_CONFIRM = 3;

// Mirrors the mock handler's key so a reload can resume the progress view.
const JOB_STORAGE_KEY = 'mock.installJobId';

function readStoredJobId(): string | null {
  try {
    return window.localStorage.getItem(JOB_STORAGE_KEY);
  } catch {
    return null;
  }
}

function storeJobId(jobId: string | null): void {
  try {
    if (jobId) {
      window.localStorage.setItem(JOB_STORAGE_KEY, jobId);
    } else {
      window.localStorage.removeItem(JOB_STORAGE_KEY);
    }
  } catch {
    // storage unavailable — resume-after-reload just won't work
  }
}

export default function InstallPage() {
  const { t } = useTranslation('install');
  const navigate = useNavigate();

  const statusQuery = useQuery({
    queryKey: ['install', 'status'],
    queryFn: getInstallStatus,
    retry: false,
  });

  const [current, setCurrent] = useState(STEP_ENV);
  const [draft, setDraft] = useState<InstallDraft>({
    adminUsername: '',
    adminNickname: '',
    adminPassword: '',
    siteName: env.appName,
  });
  const [jobId, setJobId] = useState<string | null>(() => readStoredJobId());
  const [startError, setStartError] = useState<string | null>(null);

  const envQuery = useQuery({
    queryKey: ['install', 'environment'],
    queryFn: () => apiGet<EnvCheckItem[]>('/install/environment'),
    enabled: current === STEP_ENV,
    retry: false,
  });

  const policyQuery = useQuery({
    queryKey: ['passwordPolicy'],
    queryFn: getPasswordPolicy,
  });

  const jobQuery = useQuery({
    queryKey: ['install', 'job', jobId],
    queryFn: () => getJob(jobId as string),
    enabled: Boolean(jobId),
    refetchInterval: (query) => {
      const status = query.state.data?.status;
      return status === 'success' || status === 'failed' ? false : 1000;
    },
  });

  const job: JobRun | undefined = jobQuery.data;

  useEffect(() => {
    if (job?.status === 'success') {
      storeJobId(null);
      navigate('/result/success', {
        replace: true,
        state: {
          title: t('result.title'),
          description: t('result.description'),
          next: '/login',
        },
      });
    }
  }, [job?.status, navigate, t]);

  const adminSchema = useMemo(() => {
    const policy = policyQuery.data;
    if (!policy) return null;
    return z
      .object({
        adminUsername: z.string().trim().min(1, t('admin.required')),
        adminNickname: z.string().trim().min(1, t('admin.required')),
        adminPassword: z
          .string()
          .min(policy.minLength, t('admin.passwordMin', { count: policy.minLength }))
          .refine(
            (v) => !policy.requireMixedCase || (/[a-z]/.test(v) && /[A-Z]/.test(v)),
            t('admin.passwordMixedCase'),
          )
          .refine((v) => !policy.requireDigit || /\d/.test(v), t('admin.passwordDigit'))
          .refine(
            (v) => !policy.requireSymbol || /[^A-Za-z0-9]/.test(v),
            t('admin.passwordSymbol'),
          ),
        confirmPassword: z.string().min(1, t('admin.required')),
      })
      .refine((d) => d.adminPassword === d.confirmPassword, {
        path: ['confirmPassword'],
        message: t('admin.mismatch'),
      });
  }, [policyQuery.data, t]);

  const adminFormApi = useRef<FormApi<Record<string, unknown>> | null>(null);
  const siteFormApi = useRef<FormApi<Record<string, unknown>> | null>(null);

  const beginInstall = async () => {
    setStartError(null);
    try {
      const { jobId: nextJobId } = await startInstall({
        adminUsername: draft.adminUsername,
        adminPassword: draft.adminPassword,
        adminNickname: draft.adminNickname,
        siteName: draft.siteName.trim() || undefined,
      });
      storeJobId(nextJobId);
      setJobId(nextJobId);
    } catch (e) {
      setStartError(e instanceof ApiError ? e.message : t('failed.title'));
    }
  };

  if (statusQuery.isPending) {
    return <Loading fullPage />;
  }

  if (statusQuery.data?.installed) {
    return (
      <div
        className="flex min-h-screen items-center justify-center"
        style={{ background: 'var(--semi-color-bg-0)' }}
      >
        <Card style={{ width: 560 }}>
          <ResultPage
            status="success"
            title={t('installed.title')}
            description={t('installed.description')}
            extra={
              <Button theme="solid" type="primary" onClick={() => navigate('/login')}>
                {t('installed.goLogin')}
              </Button>
            }
          />
        </Card>
      </div>
    );
  }

  const pageShell = (children: React.ReactNode) => (
    <div
      className="flex min-h-screen justify-center py-10"
      style={{ background: 'var(--semi-color-bg-0)' }}
    >
      <Card style={{ width: 640 }} shadows="hover">
        <div className="flex items-center gap-2 pb-4">
          {env.appLogo ? (
            <img src={env.appLogo} alt={env.appName} style={{ height: 36 }} />
          ) : (
            <IconSemiLogo size="large" style={{ color: 'var(--semi-color-primary)' }} />
          )}
          <div>
            <Typography.Title heading={5} style={{ margin: 0 }}>
              {t('title')}
            </Typography.Title>
            <Typography.Text type="secondary" size="small">
              {t('intro')}
            </Typography.Text>
          </div>
        </div>
        {children}
      </Card>
    </div>
  );

  // Progress view replaces the wizard once the job exists.
  if (jobId) {
    return pageShell(
      <div className="flex flex-col gap-4">
        <Typography.Title heading={5} style={{ margin: 0 }}>
          {t('progress.title')}
        </Typography.Title>
        <Typography.Text type="secondary" size="small" data-testid="job-stage">
          {job ? t('progress.stage', { stage: job.stage }) : t('progress.title')}
        </Typography.Text>
        <Progress percent={job?.progress ?? 0} showInfo aria-label="install-progress" />
        {job?.status === 'failed' ? (
          <div className="flex flex-col gap-3">
            <Banner
              type="danger"
              fullMode={false}
              description={t('failed.reason', { reason: job.error ?? '' })}
              closeIcon={null}
            />
            <div>
              <Button
                theme="solid"
                type="primary"
                onClick={() => {
                  storeJobId(null);
                  setJobId(null);
                  setCurrent(STEP_CONFIRM);
                }}
              >
                {t('failed.retry')}
              </Button>
            </div>
          </div>
        ) : null}
      </div>,
    );
  }

  const envItems = envQuery.data ?? [];
  const envAllOk = envItems.length > 0 && envItems.every((item) => item.ok);

  return pageShell(
    <div className="flex flex-col gap-6">
      <Steps current={current} type="basic" size="small">
        <Steps.Step title={t('steps.env')} />
        <Steps.Step title={t('steps.admin')} />
        <Steps.Step title={t('steps.site')} />
        <Steps.Step title={t('steps.confirm')} />
      </Steps>

      {current === STEP_ENV ? (
        <div className="flex flex-col gap-4">
          {envQuery.isPending ? (
            <Typography.Text type="secondary">{t('env.checking')}</Typography.Text>
          ) : (
            <ul className="m-0 flex flex-col gap-2" style={{ listStyle: 'none', padding: 0 }}>
              {envItems.map((item) => (
                <li key={item.key} className="flex items-center gap-2">
                  <IconTickCircle
                    style={{
                      color: item.ok
                        ? 'var(--semi-color-success)'
                        : 'var(--semi-color-danger)',
                    }}
                  />
                  <Typography.Text>{item.name}</Typography.Text>
                  <Typography.Text type="secondary" size="small">
                    {item.detail}
                  </Typography.Text>
                </li>
              ))}
            </ul>
          )}
          {envQuery.isError ? (
            <Banner
              type="danger"
              fullMode={false}
              description={t('env.failed')}
              closeIcon={null}
            />
          ) : null}
          {envAllOk ? (
            <Typography.Text type="success" size="small">
              {t('env.ready')}
            </Typography.Text>
          ) : null}
          <div className="flex justify-between">
            <Button onClick={() => void envQuery.refetch()}>{t('env.retry')}</Button>
            <Button
              theme="solid"
              type="primary"
              disabled={!envAllOk}
              onClick={() => setCurrent(STEP_ADMIN)}
            >
              {t('actions.next')}
            </Button>
          </div>
        </div>
      ) : null}

      {current === STEP_ADMIN ? (
        <div className="flex flex-col gap-4">
          <Typography.Text type="secondary" size="small">
            {t('admin.tip')}
          </Typography.Text>
          <Form
            initValues={{
              adminUsername: draft.adminUsername,
              adminNickname: draft.adminNickname,
              adminPassword: draft.adminPassword,
            }}
            getFormApi={(api) => {
              adminFormApi.current = api as unknown as FormApi<Record<string, unknown>>;
            }}
            onSubmit={(values) => {
              const parsed = adminSchema?.safeParse(values as Record<string, unknown>);
              if (!parsed?.success) {
                const shown = new Set<string>();
                for (const issue of parsed?.error.issues ?? []) {
                  const field = String(issue.path[0]);
                  if (!shown.has(field)) {
                    shown.add(field);
                    adminFormApi.current?.setError(field, issue.message);
                  }
                }
                return;
              }
              setDraft((d) => ({
                ...d,
                adminUsername: parsed.data.adminUsername,
                adminNickname: parsed.data.adminNickname,
                adminPassword: parsed.data.adminPassword,
              }));
              setCurrent(STEP_SITE);
            }}
          >
            <Form.Input
              field="adminUsername"
              label={t('admin.username')}
              placeholder={t('admin.usernamePlaceholder')}
              rules={[{ required: true, message: t('admin.required') }]}
            />
            <Form.Input
              field="adminNickname"
              label={t('admin.nickname')}
              placeholder={t('admin.nicknamePlaceholder')}
              rules={[{ required: true, message: t('admin.required') }]}
            />
            <Form.Input
              field="adminPassword"
              type="password"
              label={t('admin.password')}
              placeholder={t('admin.passwordPlaceholder')}
              rules={[{ required: true, message: t('admin.required') }]}
            />
            <Form.Input
              field="confirmPassword"
              type="password"
              label={t('admin.confirmPassword')}
              placeholder={t('admin.confirmPasswordPlaceholder')}
              rules={[{ required: true, message: t('admin.required') }]}
            />
            <div className="flex justify-between pt-2">
              <Button onClick={() => setCurrent(STEP_ENV)}>{t('actions.prev')}</Button>
              <Button htmlType="submit" theme="solid" type="primary">
                {t('actions.next')}
              </Button>
            </div>
          </Form>
        </div>
      ) : null}

      {current === STEP_SITE ? (
        <div className="flex flex-col gap-4">
          <Typography.Text type="secondary" size="small">
            {t('site.tip')}
          </Typography.Text>
          <Form
            initValues={{ siteName: draft.siteName }}
            getFormApi={(api) => {
              siteFormApi.current = api as unknown as FormApi<Record<string, unknown>>;
            }}
            onSubmit={(values) => {
              const siteName = String(
                (values as Record<string, unknown>).siteName ?? '',
              ).trim();
              setDraft((d) => ({ ...d, siteName: siteName || env.appName }));
              setCurrent(STEP_CONFIRM);
            }}
          >
            <Form.Input
              field="siteName"
              label={t('site.siteName')}
              placeholder={t('site.siteNamePlaceholder')}
            />
            <div className="flex justify-between pt-2">
              <Button onClick={() => setCurrent(STEP_ADMIN)}>{t('actions.prev')}</Button>
              <Button htmlType="submit" theme="solid" type="primary">
                {t('actions.next')}
              </Button>
            </div>
          </Form>
        </div>
      ) : null}

      {current === STEP_CONFIRM ? (
        <div className="flex flex-col gap-4">
          <Typography.Text type="secondary" size="small">
            {t('confirm.tip')}
          </Typography.Text>
          <Descriptions
            data={[
              { key: t('confirm.adminUsername'), value: draft.adminUsername },
              { key: t('confirm.adminNickname'), value: draft.adminNickname },
              {
                key: t('confirm.siteName'),
                value: draft.siteName || t('confirm.siteNameDefault'),
              },
            ]}
          />
          {startError ? (
            <Banner
              type="danger"
              fullMode={false}
              description={startError}
              closeIcon={null}
            />
          ) : null}
          <div className="flex justify-between">
            <Button onClick={() => setCurrent(STEP_SITE)}>{t('actions.prev')}</Button>
            <Button theme="solid" type="primary" onClick={() => void beginInstall()}>
              {t('confirm.start')}
            </Button>
          </div>
        </div>
      ) : null}
    </div>,
  );
}
