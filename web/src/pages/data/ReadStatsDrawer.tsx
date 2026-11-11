import { useState } from 'react';

import { SideSheet, Skeleton, Tabs, Typography } from '@douyinfe/semi-ui';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import { getAnnouncementReadStats, type Announcement } from '@/api/announcements';
import { Empty } from '@/components/Empty';
import { ErrorState } from '@/components/ErrorState';
import { formatDateTime } from './format';

export interface ReadStatsDrawerProps {
  visible: boolean;
  announcement: Announcement | null;
  onClose: () => void;
}

/** 已读情况抽屉：已读/未读两个 Tab 的人员名单。 */
export function ReadStatsDrawer({ visible, announcement, onClose }: ReadStatsDrawerProps) {
  const { t } = useTranslation('announcements');
  const [tab, setTab] = useState<'read' | 'unread'>('read');

  const stats = useQuery({
    queryKey: ['announcements', 'read-stats', announcement?.id],
    queryFn: () => getAnnouncementReadStats(announcement?.id ?? ''),
    enabled: visible && Boolean(announcement),
  });

  const items = stats.data?.items ?? [];
  const readItems = items.filter((i) => i.read_time !== null);
  const unreadItems = items.filter((i) => i.read_time === null);

  return (
    <SideSheet
      visible={visible}
      width={560}
      title={`${t('readStats.title')} · ${announcement?.title ?? ''}`}
      onCancel={onClose}
    >
      {announcement?.state === 'draft' ? (
        <Empty title={t('readStats.empty')} />
      ) : stats.isPending ? (
        <Skeleton placeholder={<Skeleton.Paragraph rows={8} />} loading />
      ) : stats.isError ? (
        <ErrorState kind="error" error={stats.error} onRetry={() => void stats.refetch()} showHome={false} />
      ) : (
        <Tabs
          activeKey={tab}
          onChange={(key) => setTab(key as 'read' | 'unread')}
          type="button"
        >
          <Tabs.TabPane
            tab={t('readStats.readTab', { count: stats.data?.read_count ?? 0 })}
            itemKey="read"
          >
            <PersonList items={readItems} read />
          </Tabs.TabPane>
          <Tabs.TabPane
            tab={t('readStats.unreadTab', { count: stats.data?.unread_count ?? 0 })}
            itemKey="unread"
          >
            <PersonList items={unreadItems} read={false} />
          </Tabs.TabPane>
        </Tabs>
      )}
    </SideSheet>
  );
}

function PersonList({
  items,
  read,
}: {
  items: Array<{ user_id: string; username: string; nickname: string; read_time: string | null }>;
  read: boolean;
}) {
  const { t } = useTranslation('announcements');
  if (items.length === 0) {
    return <Empty title={t('readStats.empty')} />;
  }
  return (
    <div className="flex flex-col divide-y divide-[var(--semi-color-border)]">
      {items.map((item) => (
        <div key={item.user_id} className="flex items-center justify-between py-2.5">
          <Typography.Text>{item.nickname || item.username}</Typography.Text>
          <Typography.Text type="tertiary" size="small">
            {read && item.read_time ? t('readStats.readAt', { time: formatDateTime(item.read_time) }) : '—'}
          </Typography.Text>
        </div>
      ))}
    </div>
  );
}
