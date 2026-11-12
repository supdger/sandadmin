import type { ReactNode } from 'react';

import { Button, Toast, Typography } from '@douyinfe/semi-ui';
import { useTranslation } from 'react-i18next';

import { AdminApiError, isAdminSystemError } from '@/lib/adminClient';
import { adminProblemReport } from '@/lib/adminError';
import { isNetworkFailure } from '@/lib/networkFailure';

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

/** admin 契约系统错误的「复制问题信息」按钮。 */
export function AdminCopyButton({ error }: { error: AdminApiError }) {
  const { t } = useTranslation();

  const onCopy = () => {
    void copyText(adminProblemReport(error)).then((copied) => {
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

export interface AdminErrorNoticeProps {
  /** 捕获到的错误；admin 系统故障渲染统一提示。 */
  error: unknown;
  onRetry?: () => void;
  /** 非系统错误时渲染的内容（业务错误走调用方自己的 UI）。 */
  fallback?: ReactNode;
}

/**
 * admin-v1 基础设施故障（断网、5xx、响应读不懂）的统一提示；
 * 业务错误交给 fallback。
 */
export function AdminErrorNotice({ error, onRetry, fallback }: AdminErrorNoticeProps) {
  const { t } = useTranslation();

  if (isNetworkFailure(error)) {
    return (
      <div className="flex flex-wrap items-center gap-2">
        <Typography.Text type="danger">{t('errors.network.message')}</Typography.Text>
        {onRetry ? (
          <Button size="small" onClick={onRetry}>
            {t('actions.retry')}
          </Button>
        ) : null}
      </div>
    );
  }

  if (!isAdminSystemError(error)) {
    return <>{fallback ?? null}</>;
  }

  return (
    <div className="flex flex-wrap items-center gap-2">
      <Typography.Text type="danger">{t('errors.system.message')}</Typography.Text>
      <AdminCopyButton error={error} />
      {onRetry ? (
        <Button size="small" onClick={onRetry}>
          {t('actions.retry')}
        </Button>
      ) : null}
    </div>
  );
}
