import { Card, Radio, RadioGroup, Spin, Switch, Toast, Typography } from '@douyinfe/semi-ui';
import type { RadioChangeEvent } from '@douyinfe/semi-ui/lib/es/radio';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import { getPreferences, putPreferences, type Preferences } from '@/api/preferences';
import { ErrorState } from '@/components/ErrorState';
import { PageHeader } from '@/components/PageHeader';
import { useCan } from '@/hooks/useCan';
import { useThemeMode } from '@/layouts/themeMode';
import { apiErrorMessage } from '@/lib/apiClient';
import type { ThemeMode } from '@/lib/theme';

const PREFERENCES_QUERY_KEY = ['me', 'preferences'] as const;

const THEME_OPTIONS: Array<{ value: ThemeMode; labelKey: string }> = [
  { value: 'auto', labelKey: 'preferences.appearance.auto' },
  { value: 'light', labelKey: 'preferences.appearance.light' },
  { value: 'dark', labelKey: 'preferences.appearance.dark' },
];

/**
 * Account preferences. Notification settings are stored server-side via
 * `PUT /me/preferences`; the theme is a per-device display choice kept in
 * localStorage, so it applies immediately and stays editable for read-only
 * accounts.
 */
export function PreferencesPage() {
  const { t } = useTranslation('shell');
  const queryClient = useQueryClient();
  const { mode, setMode } = useThemeMode();
  // Saving notification settings is a write — read-only accounts see the
  // page but those controls stay disabled with an explanatory hint. Theme
  // is exempt: it never leaves the device.
  const canWrite = useCan('preferences:write');

  const prefs = useQuery({
    queryKey: PREFERENCES_QUERY_KEY,
    queryFn: getPreferences,
    staleTime: 60_000,
  });

  const save = useMutation({
    mutationFn: (next: Preferences) => putPreferences(next),
    onSuccess: (_data, next) => {
      queryClient.setQueryData(PREFERENCES_QUERY_KEY, next);
      Toast.success(t('preferences.saved'));
    },
    onError: (error) => {
      // ApiError.message is already user-safe: the backend's plain-language
      // reason on business errors, the unified copy on system failures.
      Toast.error(apiErrorMessage(error, t('preferences.saveFailed')));
    },
  });

  if (prefs.isPending) {
    return (
      <div className="flex items-center justify-center py-16">
        <Spin size="large" />
      </div>
    );
  }

  if (prefs.isError) {
    return (
      <ErrorState kind="error" error={prefs.error} onRetry={() => void prefs.refetch()} />
    );
  }

  const current = prefs.data;
  const emailNotify = current.emailNotify === true;

  const onThemeChange = (e: RadioChangeEvent) => {
    setMode(e.target.value as ThemeMode);
  };

  const onEmailNotifyChange = (checked: boolean) => {
    save.mutate({ ...current, emailNotify: checked });
  };

  return (
    <div className="flex max-w-3xl flex-col gap-4">
      <PageHeader title={t('preferences.title')} />
      {canWrite ? null : (
        <Typography.Text type="tertiary">{t('preferences.readOnlyHint')}</Typography.Text>
      )}

      <Card title={t('preferences.appearance.title')}>
        <div className="flex flex-col gap-2">
          <div className="flex items-baseline gap-2">
            <Typography.Text strong>{t('preferences.appearance.themeLabel')}</Typography.Text>
            <Typography.Text type="tertiary" size="small">
              {t('preferences.appearance.localOnly')}
            </Typography.Text>
          </div>
          <RadioGroup value={mode} onChange={onThemeChange}>
            {THEME_OPTIONS.map((opt) => (
              <Radio key={opt.value} value={opt.value}>
                {t(opt.labelKey)}
              </Radio>
            ))}
          </RadioGroup>
        </div>
      </Card>

      <Card title={t('preferences.notifications.title')}>
        <div className="flex items-center justify-between gap-4">
          <div>
            <Typography.Text strong>{t('preferences.notifications.emailLabel')}</Typography.Text>
            <div>
              <Typography.Text type="secondary" size="small">
                {t('preferences.notifications.emailHint')}
              </Typography.Text>
            </div>
          </div>
          <Switch
            checked={emailNotify}
            onChange={onEmailNotifyChange}
            disabled={save.isPending || !canWrite}
            aria-label={t('preferences.notifications.emailLabel')}
          />
        </div>
      </Card>
    </div>
  );
}
