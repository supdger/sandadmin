import { useEffect, useState } from 'react';

import { IconBell } from '@douyinfe/semi-icons';
import {
  Badge,
  Button,
  IconButton,
  Popover,
  Skeleton,
  Toast,
  Tooltip,
  Typography,
} from '@douyinfe/semi-ui';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import {
  getNotifications,
  getUnreadCount,
  markAllNotificationsRead,
  markInboxItemRead,
  type NotificationItem,
} from '@/api/v1/notifications';
import { Empty } from '@/components/Empty';
import { adminErrorMessage } from '@/lib/adminError';
import { formatRelativeTime } from '@/pages/personal/relativeTime';

const NOTIFICATIONS_QUERY_KEY = ['notifications'] as const;
const UNREAD_COUNT_KEY = ['notifications', 'unread-count'] as const;
const PANEL_KEY = ['notifications', 'panel'] as const;

/**
 * Header bell: badge polls the unread count every 60s (paused while the tab
 * is hidden, refetched on return); the popover lists the latest six inbox
 * items and marks them read through the endpoint matching each kind.
 */
export function NotificationBell() {
  const { t } = useTranslation('notifications');
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [open, setOpen] = useState(false);

  const countQuery = useQuery({
    queryKey: UNREAD_COUNT_KEY,
    queryFn: getUnreadCount,
    refetchInterval: 60_000,
    retry: 1,
  });
  const { refetch: refetchCount } = countQuery;

  useEffect(() => {
    const onVisible = () => {
      if (document.visibilityState === 'visible') {
        void refetchCount();
      }
    };
    document.addEventListener('visibilitychange', onVisible);
    return () => document.removeEventListener('visibilitychange', onVisible);
  }, [refetchCount]);

  const panelQuery = useQuery({
    queryKey: PANEL_KEY,
    queryFn: () => getNotifications({ page: 1, page_size: 6 }),
    enabled: open,
  });

  const invalidateAll = () =>
    void queryClient.invalidateQueries({ queryKey: NOTIFICATIONS_QUERY_KEY });

  const handleOpenItem = async (item: NotificationItem) => {
    setOpen(false);
    if (!item.read_time) {
      try {
        await markInboxItemRead(item);
        invalidateAll();
      } catch {
        // Best-effort: the item still navigates even if marking fails.
      }
    }
    if (item.link) {
      navigate(item.link);
    }
  };

  const handleMarkAll = async () => {
    try {
      await markAllNotificationsRead();
      invalidateAll();
    } catch (e) {
      Toast.error(adminErrorMessage(e, t('panel.loadFailed')));
    }
  };

  const count = countQuery.data?.count ?? 0;
  const items = panelQuery.data?.items ?? [];

  return (
    <Popover
      visible={open}
      onVisibleChange={setOpen}
      trigger="click"
      position="bottomRight"
      content={
        <div className="w-[360px]">
          <div className="flex items-center justify-between border-b px-3 py-2" style={{ borderColor: 'var(--semi-color-border)' }}>
            <Typography.Text strong>{t('panel.title')}</Typography.Text>
            <Button size="small" theme="borderless" onClick={() => void handleMarkAll()}>
              {t('panel.markAll')}
            </Button>
          </div>
          <div className="max-h-[420px] overflow-y-auto">
            {panelQuery.isPending ? (
              <div className="flex flex-col gap-3 p-3">
                {[0, 1, 2].map((i) => (
                  <Skeleton key={i} loading placeholder={<Skeleton.Paragraph rows={1} />}>
                    <div />
                  </Skeleton>
                ))}
              </div>
            ) : panelQuery.isError ? (
              <div className="flex flex-col items-center gap-2 p-6">
                <Typography.Text type="danger" size="small">
                  {t('panel.loadFailed')}
                </Typography.Text>
                <Button size="small" onClick={() => void panelQuery.refetch()}>
                  {t('common:actions.retry')}
                </Button>
              </div>
            ) : items.length === 0 ? (
              <Empty title={t('panel.empty')} description={t('panel.emptyDesc')} />
            ) : (
              <div className="flex flex-col">
                {items.map((item) => {
                  const unread = !item.read_time;
                  return (
                    <button
                      key={`${item.kind}-${item.id}`}
                      type="button"
                      onClick={() => void handleOpenItem(item)}
                      className="flex w-full items-start gap-2 px-3 py-2 text-left hover:bg-[var(--semi-color-fill-0)]"
                    >
                      <span
                        className="mt-1.5 inline-block h-2 w-2 shrink-0 rounded-full"
                        style={{
                          backgroundColor: unread ? 'var(--semi-color-primary)' : 'transparent',
                        }}
                        aria-hidden
                      />
                      <div className="min-w-0 flex-1">
                        <Typography.Text strong={unread} size="small" ellipsis={{ showTooltip: true }}>
                          {item.kind === 'announcement' ? (item.title ?? item.text) : item.text}
                        </Typography.Text>
                        <div>
                          <Typography.Text type="tertiary" size="small">
                            {formatRelativeTime(t, item.create_time)}
                          </Typography.Text>
                        </div>
                      </div>
                    </button>
                  );
                })}
              </div>
            )}
          </div>
          <div className="border-t px-3 py-2 text-center" style={{ borderColor: 'var(--semi-color-border)' }}>
            <Button size="small" theme="borderless" onClick={() => { setOpen(false); navigate('/notifications'); }}>
              {t('panel.viewAll')}
            </Button>
          </div>
        </div>
      }
    >
      <span className="inline-flex">
        <Badge count={count > 0 ? count : undefined} overflowCount={99} type="danger">
          <Tooltip content={t('bell.tooltip')} position="bottom">
            <IconButton icon={<IconBell />} aria-label={t('bell.tooltip')} />
          </Tooltip>
        </Badge>
      </span>
    </Popover>
  );
}
