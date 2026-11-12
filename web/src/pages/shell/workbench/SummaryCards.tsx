import { Card, Skeleton, Typography } from '@douyinfe/semi-ui';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { getSummaryCards, type DashboardCard } from '@/api/v1/dashboard';
import { isNetworkFailure } from '@/lib/networkFailure';
import { LoadError } from './LoadError';
import { listOrEmpty } from './listOrEmpty';

const CARD_TITLES: Record<string, string> = {
  users: 'cards.users.title',
  logins_today: 'cards.logins.title',
  ai_usage_month: 'cards.ai_usage.title',
  workflow_pending: 'cards.workflow_pending.title',
};

const CARD_HINTS: Record<string, string> = {
  users: 'cards.users.hint',
  logins_today: 'cards.logins.hint',
  ai_usage_month: 'cards.ai_usage.hint',
  workflow_pending: 'cards.workflow_pending.hint',
};

const SUB_TONES: Record<string, string> = {
  up: 'var(--semi-color-success)',
  down: 'var(--semi-color-danger)',
  warning: 'var(--semi-color-warning)',
  plain: 'var(--semi-color-text-2)',
};

const SUMMARY_QUERY_KEY = ['dashboard', 'summary'] as const;

function SummaryCard({ card }: { card: DashboardCard }) {
  const { t } = useTranslation('workbench');
  const title = CARD_TITLES[card.key];
  if (!title) {
    return null;
  }
  const body = (
    <Card
      className="h-full"
      style={card.link ? { cursor: 'pointer' } : undefined}
    >
      <div className="flex flex-col gap-1">
        <Typography.Text type="secondary" size="small">
          {t(title)}
        </Typography.Text>
        {card.error ? (
          <Typography.Text type="danger" size="small">
            {t('cards.error')}
          </Typography.Text>
        ) : (
          <>
            <Typography.Title heading={3} style={{ margin: 0 }}>
              {card.value ?? '—'}
              {card.unit ? (
                <Typography.Text type="secondary" size="small" className="ml-1">
                  {card.unit}
                </Typography.Text>
              ) : null}
            </Typography.Title>
            {card.sub?.text ? (
              <Typography.Text
                size="small"
                style={{ color: SUB_TONES[card.sub.tone ?? 'plain'] ?? SUB_TONES.plain }}
              >
                {card.sub.text}
              </Typography.Text>
            ) : null}
          </>
        )}
        <Typography.Text type="tertiary" size="small">
          {t(CARD_HINTS[card.key])}
        </Typography.Text>
      </div>
    </Card>
  );
  return card.link ? <Link to={card.link}>{body}</Link> : body;
}

/** Up-to-four permission-filtered number cards from GET /dashboard/summary. */
export function SummaryCards() {
  const summary = useQuery({ queryKey: SUMMARY_QUERY_KEY, queryFn: getSummaryCards });

  if (summary.isPaused || isNetworkFailure(summary.error)) {
    return null;
  }
  if (summary.isError) {
    return <LoadError error={summary.error} paused={summary.isPaused} onRetry={() => void summary.refetch()} />;
  }
  if (summary.isPending) {
    return (
      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        {[0, 1, 2, 3].map((i) => (
          <Card key={i}>
            <Skeleton loading placeholder={<Skeleton.Paragraph rows={2} />}>
              <div />
            </Skeleton>
          </Card>
        ))}
      </div>
    );
  }
  if (!summary.data) {
    return null;
  }
  const cards = listOrEmpty(summary.data.cards).filter((c) => CARD_TITLES[c.key]);
  if (cards.length === 0) {
    return null;
  }
  return (
    <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
      {cards.slice(0, 4).map((card) => (
        <SummaryCard key={card.key} card={card} />
      ))}
    </div>
  );
}
