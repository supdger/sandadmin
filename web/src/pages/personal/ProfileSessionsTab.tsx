import { Button, Card, Empty, Popconfirm, Skeleton, Tag, Toast, Typography } from '@douyinfe/semi-ui';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import {
  deleteMySession,
  deleteOtherSessions,
  getMySessions,
  type Session,
} from '@/api/v1/profile';
import { ErrorState } from '@/components/ErrorState';
import { adminErrorMessage } from '@/lib/adminError';
import { formatRelativeTime } from './relativeTime';

const SESSIONS_QUERY_KEY = ['me', 'sessions'] as const;

/** 登录设备 tab: signed-in sessions with per-device and bulk revoke. */
export function ProfileSessionsTab() {
  const { t } = useTranslation('profile');
  const queryClient = useQueryClient();
  const sessions = useQuery({ queryKey: SESSIONS_QUERY_KEY, queryFn: getMySessions });

  const invalidate = () => void queryClient.invalidateQueries({ queryKey: SESSIONS_QUERY_KEY });

  const revokeOne = useMutation({
    mutationFn: (sessionId: string) => deleteMySession(sessionId),
    onSuccess: () => {
      Toast.success(t('devices.loggedOut'));
      invalidate();
    },
    onError: (e) => Toast.error(adminErrorMessage(e, t('devices.logoutFailed'))),
  });

  const revokeOthers = useMutation({
    mutationFn: () => deleteOtherSessions(),
    onSuccess: () => {
      const removed = sessions.data ? sessions.data.items.filter((s) => !s.current).length : 0;
      Toast.success(t('devices.loggedOutOthers', { count: removed }));
      invalidate();
    },
    onError: (e) => Toast.error(adminErrorMessage(e, t('devices.logoutFailed'))),
  });

  if (sessions.isPending) {
    return (
      <Card style={{ maxWidth: 720 }}>
        <Skeleton loading placeholder={<Skeleton.Paragraph rows={4} />}>
          <div />
        </Skeleton>
      </Card>
    );
  }
  if (sessions.isError || !sessions.data) {
    return <ErrorState kind="error" error={sessions.error} onRetry={() => void sessions.refetch()} />;
  }

  const items = sessions.data.items;
  const othersCount = items.filter((s) => !s.current).length;

  return (
    <Card
      style={{ maxWidth: 720 }}
      headerExtraContent={
        othersCount > 0 ? (
          <Popconfirm
            title={t('devices.confirmOthersTitle')}
            content={t('devices.confirmOthersContent')}
            okText={t('devices.confirmOthersOk')}
            cancelText={t('devices.confirmCancel')}
            onConfirm={() => revokeOthers.mutateAsync()}
          >
            <Button size="small" loading={revokeOthers.isPending}>
              {t('devices.logoutOthers')}
            </Button>
          </Popconfirm>
        ) : null
      }
    >
      {items.length === 0 ? (
        <Empty description={t('devices.empty')} />
      ) : (
        <div className="flex flex-col divide-y divide-[var(--semi-color-border)]">
          {items.map((session) => (
            <SessionRow
              key={session.id}
              session={session}
              onRevoke={() => revokeOne.mutate(session.id)}
              revoking={revokeOne.isPending}
            />
          ))}
        </div>
      )}
      {items.length === 1 && items[0]?.current ? (
        <Typography.Text type="tertiary" size="small">
          {t('devices.onlyCurrent')}
        </Typography.Text>
      ) : null}
    </Card>
  );
}

interface SessionRowProps {
  session: Session;
  onRevoke: () => void;
  revoking: boolean;
}

function SessionRow({ session, onRevoke, revoking }: SessionRowProps) {
  const { t } = useTranslation('profile');
  const activeText = session.last_active_time
    ? formatRelativeTime(t, session.last_active_time)
    : '';

  return (
    <div className="flex items-center justify-between gap-4 py-3">
      <div className="min-w-0">
        <div className="flex items-center gap-2">
          <Typography.Text strong ellipsis={{ showTooltip: true }}>
            {session.device_label || t('devices.unknownDevice')}
          </Typography.Text>
          {session.current ? <Tag size="small">{t('devices.currentTag')}</Tag> : null}
        </div>
        <Typography.Text type="tertiary" size="small">
          {[session.location, activeText].filter(Boolean).join(' · ')}
        </Typography.Text>
      </div>
      {session.current ? null : (
        <Popconfirm
          title={t('devices.confirmLogoutTitle')}
          content={t('devices.confirmLogoutContent')}
          okText={t('devices.confirmLogoutOk')}
          cancelText={t('devices.confirmCancel')}
          onConfirm={onRevoke}
        >
          <Button size="small" loading={revoking}>
            {t('devices.logoutDevice')}
          </Button>
        </Popconfirm>
      )}
    </div>
  );
}
