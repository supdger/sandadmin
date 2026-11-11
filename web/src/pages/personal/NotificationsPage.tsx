import { useMemo } from 'react';

import { Button, Empty, List, Pagination, Skeleton, Tabs, Tag, Toast, Typography } from '@douyinfe/semi-ui';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { useNavigate, useSearchParams } from 'react-router';

import {
  getNotifications,
  markAllNotificationsRead,
  markInboxItemRead,
  type NotificationItem,
} from '@/api/notifications';
import { PageHeader } from '@/components/PageHeader';
import { ErrorState } from '@/components/ErrorState';
import { apiErrorMessage } from '@/lib/apiClient';
import { formatRelativeTime } from './relativeTime';

const PAGE_SIZE = 20;
const LIST_QUERY_KEY = ['notifications', 'list'] as const;

/**
 * /notifications — full inbox page. Tab and page live in the URL
 * (?tab=unread&page=2); read state changes invalidate every notifications
 * query so the badge, panel and this page stay in sync.
 */
export function NotificationsPage() {
  const { t } = useTranslation('notifications');
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const queryClient = useQueryClient();

  const unreadOnly = searchParams.get('tab') === 'unread';
  const page = Math.max(1, Number(searchParams.get('page')) || 1);

  const listQuery = useQuery({
    queryKey: [...LIST_QUERY_KEY, unreadOnly, page],
    queryFn: () => getNotifications({ unreadOnly, page, pageSize: PAGE_SIZE }),
  });

  const markAll = useMutation({
    mutationFn: markAllNotificationsRead,
    onSuccess: () => {
      Toast.success(t('page.markedAll'));
      void queryClient.invalidateQueries({ queryKey: ['notifications'] });
    },
    onError: (e) => Toast.error(apiErrorMessage(e, t('page.markAllFailed'))),
  });

  const setParam = (key: string, value: string | null) => {
    setSearchParams(
      (prev) => {
        const next = new URLSearchParams(prev);
        if (value === null) {
          next.delete(key);
        } else {
          next.set(key, value);
        }
        return next;
      },
      { replace: true },
    );
  };

  const handleTabChange = (key: string) => {
    setParam('tab', key === 'unread' ? 'unread' : null);
    setParam('page', null);
  };

  const handlePageChange = (nextPage: number) => {
    setParam('page', nextPage <= 1 ? null : String(nextPage));
    window.scrollTo({ top: 0 });
  };

  const handleOpen = async (item: NotificationItem) => {
    if (!item.read_time) {
      try {
        await markInboxItemRead(item);
        void queryClient.invalidateQueries({ queryKey: ['notifications'] });
      } catch {
        // Read-marking is best-effort; navigation still proceeds.
      }
    }
    if (item.link) {
      navigate(item.link);
    }
  };

  const data = listQuery.data;
  const total = data?.total ?? 0;
  const items = useMemo(() => data?.items ?? [], [data]);
  const isUnreadTab = unreadOnly;

  return (
    <div className="flex flex-col gap-4">
      <PageHeader
        title={t('title')}
        subtitle={t('subtitle')}
        extra={
          <Button
            size="small"
            loading={markAll.isPending}
            onClick={() => markAll.mutate()}
          >
            {t('page.markAll')}
          </Button>
        }
      />
      <Tabs
        activeKey={isUnreadTab ? 'unread' : 'all'}
        onChange={handleTabChange}
        type="button"
      >
        <Tabs.TabPane itemKey="all" tab={t('page.tabAll')} />
        <Tabs.TabPane itemKey="unread" tab={t('page.tabUnread')} />
      </Tabs>

      {listQuery.isPending ? (
        <Skeleton loading placeholder={<Skeleton.Paragraph rows={6} />}>
          <div />
        </Skeleton>
      ) : listQuery.isError ? (
        <ErrorState
          kind="error"
          error={listQuery.error}
          onRetry={() => void listQuery.refetch()}
          showHome={false}
        />
      ) : items.length === 0 ? (
        <Empty
          title={isUnreadTab ? t('page.emptyUnread') : t('page.empty')}
          description={isUnreadTab ? undefined : t('page.emptyDesc')}
        />
      ) : (
        <>
          <List
            className="rounded-lg border"
            style={{ borderColor: 'var(--semi-color-border)' }}
            dataSource={items}
            renderItem={(item) => {
              const unread = !item.read_time;
              return (
                <List.Item
                  key={`${item.kind}-${item.id}`}
                  style={{ cursor: item.link || unread ? 'pointer' : 'default' }}
                  onClick={() => void handleOpen(item)}
                  extra={
                    <Typography.Text type="tertiary" size="small">
                      {formatRelativeTime(t, item.create_time)}
                    </Typography.Text>
                  }
                >
                  <div className="flex items-start gap-3">
                    <span
                      className="mt-2 inline-block h-2 w-2 shrink-0 rounded-full"
                      style={{
                        backgroundColor: unread ? 'var(--semi-color-primary)' : 'transparent',
                      }}
                      aria-hidden
                    />
                    <div className="min-w-0 flex-1">
                      <div className="flex items-center gap-2">
                        {item.kind === 'announcement' ? (
                          <Tag size="small" color="orange">
                            {t('page.announcementTag')}
                          </Tag>
                        ) : null}
                        <Typography.Text strong={unread} ellipsis={{ showTooltip: true }}>
                          {item.title ?? item.text}
                        </Typography.Text>
                      </div>
                      {item.title && item.text !== item.title ? (
                        <Typography.Text type="secondary" size="small" ellipsis={{ showTooltip: true }}>
                          {item.text}
                        </Typography.Text>
                      ) : null}
                    </div>
                  </div>
                </List.Item>
              );
            }}
          />
          {total > PAGE_SIZE ? (
            <Pagination
              currentPage={page}
              pageSize={PAGE_SIZE}
              total={total}
              onPageChange={handlePageChange}
            />
          ) : null}
        </>
      )}
    </div>
  );
}
