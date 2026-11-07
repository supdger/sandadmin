import { Card, Typography } from '@douyinfe/semi-ui';
import { useTranslation } from 'react-i18next';

import { Empty } from '@/components/Empty';
import { useMe } from '@/hooks/useMe';

function greetingKey(hour: number): 'morning' | 'afternoon' | 'evening' {
  if (hour < 12) {
    return 'morning';
  }
  if (hour < 18) {
    return 'afternoon';
  }
  return 'evening';
}

/**
 * Workbench landing page: a time-aware greeting plus a few placeholder cards
 * that already carry the empty-state style used by real list pages later.
 */
export function WorkbenchPage() {
  const { t } = useTranslation('shell');
  const me = useMe();
  const name = me.data?.nickname || t('workbench.greeting.fallbackName');
  const key = greetingKey(new Date().getHours());

  const cards: Array<{ title: string; empty: string }> = [
    {
      title: t('workbench.recentVisits.title'),
      empty: t('workbench.recentVisits.empty'),
    },
    {
      title: t('workbench.announcements.title'),
      empty: t('workbench.announcements.empty'),
    },
    {
      title: t('workbench.todos.title'),
      empty: t('workbench.todos.empty'),
    },
  ];

  return (
    <div className="flex flex-col gap-6">
      <div>
        <Typography.Title heading={3} style={{ margin: 0 }}>
          {t(`workbench.greeting.${key}`, { name })}
        </Typography.Title>
        <Typography.Text type="secondary">{t('workbench.subtitle')}</Typography.Text>
      </div>
      <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
        {cards.map((card) => (
          <Card key={card.title} title={card.title} style={{ height: '100%' }}>
            <Empty description={card.empty} />
          </Card>
        ))}
      </div>
    </div>
  );
}
