import type { ReactNode } from 'react';

import { Button, Empty as SemiEmpty } from '@douyinfe/semi-ui';
import {
  IllustrationFailure,
  IllustrationFailureDark,
  IllustrationNoAccess,
  IllustrationNoAccessDark,
  IllustrationNotFound,
  IllustrationNotFoundDark,
} from '@douyinfe/semi-illustrations';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import { isSystemApiError } from '@/lib/apiClient';
import { isAdminSystemError } from '@/lib/adminClient';
import { CopyErrorButton } from './SystemErrorNotice';
import { AdminCopyButton } from './AdminErrorNotice';

export type ErrorKind = '403' | '404' | '500' | 'error';

export interface ErrorStateProps {
  kind?: ErrorKind;
  title?: ReactNode;
  description?: ReactNode;
  /**
   * Caught API error. When it is an infrastructural failure (network, HTTP
   * 5xx, unreadable response) the description switches to the unified system
   * message and a "copy problem info" action is offered; business errors keep
   * the kind-based copy.
   */
  error?: unknown;
  /** Show a retry button that calls this handler. */
  onRetry?: () => void;
  /** Show a "back to home" button (default true). */
  showHome?: boolean;
}

const ILLUSTRATIONS: Record<ErrorKind, { light: ReactNode; dark: ReactNode }> = {
  '403': { light: <IllustrationNoAccess />, dark: <IllustrationNoAccessDark /> },
  '404': { light: <IllustrationNotFound />, dark: <IllustrationNotFoundDark /> },
  '500': { light: <IllustrationFailure />, dark: <IllustrationFailureDark /> },
  error: { light: <IllustrationFailure />, dark: <IllustrationFailureDark /> },
};

const TITLE_KEYS: Record<ErrorKind, string> = {
  '403': 'errors.forbidden.title',
  '404': 'errors.notFound.title',
  '500': 'errors.serverError.title',
  error: 'errors.generic.title',
};

const DESC_KEYS: Record<ErrorKind, string> = {
  '403': 'errors.forbidden.description',
  '404': 'errors.notFound.description',
  '500': 'errors.serverError.description',
  error: 'errors.generic.description',
};

export function ErrorState({
  kind = 'error',
  title,
  description,
  error,
  onRetry,
  showHome = true,
}: ErrorStateProps) {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const illustration = ILLUSTRATIONS[kind];
  // 旧 apiClient 与 adminClient 并存期：两套 system 错误都认。
  const legacyError = isSystemApiError(error) ? error : null;
  const adminError = isAdminSystemError(error) ? error : null;
  const systemError = legacyError ?? adminError;

  return (
    <div className="flex items-center justify-center py-16">
      <SemiEmpty
        image={illustration.light}
        darkModeImage={illustration.dark}
        title={title ?? t(TITLE_KEYS[kind])}
        description={
          description ?? (systemError ? t('errors.system.message') : t(DESC_KEYS[kind]))
        }
      >
        <div className="flex items-center justify-center gap-2">
          {legacyError ? <CopyErrorButton error={legacyError} /> : null}
          {adminError ? <AdminCopyButton error={adminError} /> : null}
          {onRetry ? (
            <Button theme="solid" onClick={onRetry}>
              {t('actions.retry')}
            </Button>
          ) : null}
          {showHome ? (
            <Button onClick={() => navigate('/')}>{t('actions.backHome')}</Button>
          ) : null}
        </div>
      </SemiEmpty>
    </div>
  );
}
