import type { ReactNode } from 'react';

import { Button, Toast, Typography } from '@douyinfe/semi-ui';
import { useTranslation } from 'react-i18next';

import { isSystemApiError, type ApiError } from '@/lib/apiClient';
import { buildProblemReport } from '@/lib/problemReport';

async function copyText(text: string): Promise<boolean> {
  const clipboard = typeof navigator === 'undefined' ? undefined : navigator.clipboard;
  if (!clipboard?.writeText) {
    return false;
  }
  try {
    await clipboard.writeText(text);
    return true;
  } catch {
    return false;
  }
}

/** "Copy problem info" button shared by every system-error surface. */
export function CopyProblemInfoButton({ error }: { error: ApiError }) {
  const { t } = useTranslation();

  const onCopy = () => {
    void copyText(buildProblemReport(error)).then((copied) => {
      if (copied) {
        Toast.success(t('errors.system.copied'));
      } else {
        Toast.error(t('errors.system.copyFailed'));
      }
    });
  };

  return (
    <Button size="small" onClick={onCopy}>
      {t('errors.system.copy')}
    </Button>
  );
}

export interface SystemErrorNoticeProps {
  /** Caught error; non-system failures render `fallback` instead. */
  error: unknown;
  onRetry?: () => void;
  fallback?: ReactNode;
}

/**
 * Unified notice for infrastructural API failures (network, HTTP 5xx,
 * unreadable responses). Business errors keep the caller's own UI through
 * `fallback`.
 */
export function SystemErrorNotice({ error, onRetry, fallback }: SystemErrorNoticeProps) {
  const { t } = useTranslation();

  if (!isSystemApiError(error)) {
    return <>{fallback ?? null}</>;
  }

  return (
    <div className="flex flex-wrap items-center gap-2">
      <Typography.Text type="danger">{t('errors.system.message')}</Typography.Text>
      <CopyProblemInfoButton error={error} />
      {onRetry ? (
        <Button size="small" onClick={onRetry}>
          {t('actions.retry')}
        </Button>
      ) : null}
    </div>
  );
}
