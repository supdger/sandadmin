import { ActivityFeed } from './workbench/ActivityFeed';
import { GreetingHeader } from './workbench/GreetingHeader';
import { NoticeBanner } from './workbench/NoticeBanner';
import { OnboardingCard } from './workbench/OnboardingCard';
import { ShortcutBar } from './workbench/ShortcutBar';
import { SummaryCards } from './workbench/SummaryCards';
import { TodoPanel } from './workbench/TodoPanel';
import { TrendCharts } from './workbench/TrendCharts';

/**
 * Workbench landing page — the first screen after login. Every block owns
 * its query and degrades independently: a failed card never blanks the page,
 * and permission-filtered sections simply don't render for unauthorized
 * users (the API omits them).
 */
export function WorkbenchPage() {
  return (
    <div className="flex flex-col gap-4">
      <GreetingHeader />
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
    </div>
  );
}
