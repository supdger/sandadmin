import { Button } from '@douyinfe/semi-ui';
import { useTranslation } from 'react-i18next';
import { useLocation, useNavigate } from 'react-router';

import { ResultPage } from '@/components/ResultPage';

interface ResultRouteState {
  title?: string;
  description?: string;
  /** Where the primary "continue" button navigates. Defaults to /login. */
  next?: string;
}

export interface ResultRoutePageProps {
  status: 'success' | 'fail';
}

/**
 * Bare result routes (`/result/success`, `/result/fail`). Content arrives via
 * `navigate(..., { state })` from the calling flow; without state it falls
 * back to generic copy.
 */
export function ResultRoutePage({ status }: ResultRoutePageProps) {
  const { t } = useTranslation('shell');
  const location = useLocation();
  const navigate = useNavigate();
  const state = (location.state ?? {}) as ResultRouteState;

  return (
    <div className="flex min-h-screen items-center justify-center">
      <ResultPage
        status={status}
        title={state.title ?? t(`result.${status}.title`)}
        description={state.description ?? t(`result.${status}.description`)}
        extra={
          <Button theme="solid" onClick={() => navigate(state.next ?? '/login')}>
            {t('result.continue')}
          </Button>
        }
      />
    </div>
  );
}
