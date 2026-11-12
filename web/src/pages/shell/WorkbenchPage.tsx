import { useEffect, useState } from 'react';

import { Banner, Button } from '@douyinfe/semi-ui';
import { useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import { isNetworkFailure } from '@/lib/networkFailure';
import { ActivityFeed } from './workbench/ActivityFeed';
import { GreetingHeader } from './workbench/GreetingHeader';
import { NoticeBanner } from './workbench/NoticeBanner';
import { OnboardingCard } from './workbench/OnboardingCard';
import { ShortcutBar } from './workbench/ShortcutBar';
import { SummaryCards } from './workbench/SummaryCards';
import { TodoPanel } from './workbench/TodoPanel';
import { TrendCharts } from './workbench/TrendCharts';

function useDashboardOffline(): boolean {
  const queryClient = useQueryClient();
  const [offline, setOffline] = useState(false);

  useEffect(() => {
    const update = () => {
      const browserOff = typeof navigator !== 'undefined' && navigator.onLine === false;
      const queries = queryClient.getQueryCache().findAll({ queryKey: ['dashboard'] });
      const failed = queries.some((query) => {
        if (query.state.fetchStatus === 'paused') {
          return true;
        }
        return query.state.status === 'error' && isNetworkFailure(query.state.error);
      });
      setOffline(browserOff || failed);
    };
    update();
    const unsubscribe = queryClient.getQueryCache().subscribe(update);
    window.addEventListener('online', update);
    window.addEventListener('offline', update);
    return () => {
      unsubscribe();
      window.removeEventListener('online', update);
      window.removeEventListener('offline', update);
    };
  }, [queryClient]);

  return offline;
}

/**
 * Workbench landing page — the first screen after login. Every block owns
 * its query and degrades independently: a failed card never blanks the page,
 * and permission-filtered sections simply don't render for unauthorized
 * users (the API omits them).
 */
export function WorkbenchPage() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const offline = useDashboardOffline();

  return (
    <div className="flex flex-col gap-4">
      {offline ? (
        <Banner
          type="warning"
          fullMode={false}
          closeIcon={null}
          description={
            <span className="inline-flex items-center gap-3">
              {t('errors.network.message')}
              <Button size="small" onClick={() => void queryClient.invalidateQueries({ queryKey: ['dashboard'] })}>
                {t('actions.retry')}
              </Button>
            </span>
          }
        />
      ) : null}
      <GreetingHeader />
      {offline ? (
        <>
          <div data-testid="offline-placeholder" className="h-24 rounded-lg" style={{ background: 'var(--semi-color-fill-0)' }} />
          <div className="grid grid-cols-1 gap-4 xl:grid-cols-3">
            <div data-testid="offline-placeholder" className="h-40 rounded-lg xl:col-span-2" style={{ background: 'var(--semi-color-fill-0)' }} />
            <div data-testid="offline-placeholder" className="h-40 rounded-lg" style={{ background: 'var(--semi-color-fill-0)' }} />
          </div>
        </>
      ) : (
        <>
          <NoticeBanner />
          <SummaryCards />
          <div className="grid grid-cols-1 gap-4 xl:grid-cols-3">
            <div className="flex flex-col gap-4 xl:col-span-2">
              <TrendCharts />
              <ShortcutBar />
            </div>
            <div className="flex flex-col gap-4">
              <TodoPanel />
              <OnboardingCard />
              <ActivityFeed />
            </div>
          </div>
        </>
      )}
    </div>
  );
}
