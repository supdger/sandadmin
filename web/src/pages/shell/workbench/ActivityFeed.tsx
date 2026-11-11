import { Avatar, Card, Empty, Skeleton, Typography } from '@douyinfe/semi-ui';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import { getRecentActivities } from '@/api/v1/dashboard';
import { formatRelativeTime } from '@/pages/personal/relativeTime';

const ACTIVITIES_QUERY_KEY = ['dashboard', 'activities'] as const;

/** 最近动态 card — audit-log style feed; hidden content on error/empty. */
export function ActivityFeed() {
  const { t } = useTranslation('workbench');
  const activities = useQuery({
    queryKey: ACTIVITIES_QUERY_KEY,
    queryFn: () => getRecentActivities(10),
  });

  return (
    <Card title={t('activities.title')}>
      {activities.isPending ? (
        <Skeleton loading placeholder={<Skeleton.Paragraph rows={4} />}>
          <div />
        </Skeleton>
      ) : activities.isError ? (
        <Typography.Text type="danger" size="small">
          {t('cards.error')}
        </Typography.Text>
      ) : (activities.data?.items.length ?? 0) === 0 ? (
        <Empty title={t('activities.empty')} description={undefined} />
      ) : (
        <div className="flex flex-col gap-3">
          {activities.data!.items.map((item, index) => (
            <div key={`${item.actor_name}-${item.create_time}-${index}`} className="flex items-start gap-2">
              <Avatar size="extra-small">{item.actor_name.slice(0, 1)}</Avatar>
              <div className="min-w-0 flex-1">
                <Typography.Text size="small">
                  <Typography.Text strong size="small">
                    {item.actor_name}
                  </Typography.Text>{' '}
                  {item.action_text}
                  {item.target_label ? `「${item.target_label}」` : ''}
                </Typography.Text>
                <div>
                  <Typography.Text type="tertiary" size="small">
                    {formatRelativeTime(t, item.create_time)}
                  </Typography.Text>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </Card>
  );
}
