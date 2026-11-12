import { useMemo, useState } from 'react';

import {
  Banner,
  Button,
  Card,
  Progress,
  Skeleton,
  Toast,
  Typography,
} from '@douyinfe/semi-ui';
import { IconCopy, IconPause, IconPlay } from '@douyinfe/semi-icons';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { getServerStatus, type ServerStatus } from '@/api/v1/monitor';
import { ErrorState } from '@/components/ErrorState';
import { PageHeader } from '@/components/PageHeader';

const REFRESH_MS = 10_000;

function fmtTime(iso: string | null | undefined): string | null {
  if (!iso) return null;
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return null;
  const pad = (num: number) => String(num).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
}

function fmtDuration(seconds: number | null | undefined): string | null {
  if (seconds == null || seconds < 0) return null;
  const days = Math.floor(seconds / 86400);
  const hours = Math.floor((seconds % 86400) / 3600);
  if (days > 0) return `${days} 天 ${hours} 小时`;
  const minutes = Math.floor((seconds % 3600) / 60);
  if (hours > 0) return `${hours} 小时 ${minutes} 分钟`;
  return `${minutes} 分钟`;
}

/** 磁盘剩多少算警告：不足 10% 或不足 5GB。 */
function diskWarnLevel(disk: ServerStatus['disk'] | undefined): 'ok' | 'warn' | 'danger' {
  if (!disk || disk.free_gb == null || disk.total_gb == null || disk.total_gb <= 0) {
    return 'ok';
  }
  const ratio = disk.free_gb / disk.total_gb;
  if (ratio <= 0.05 || disk.free_gb < 2) return 'danger';
  if (ratio <= 0.1 || disk.free_gb < 5) return 'warn';
  return 'ok';
}

export function MonitorPage() {
  const { t } = useTranslation('monitor');
  const [paused, setPaused] = useState(false);

  const query = useQuery({
    queryKey: ['monitor', 'server'],
    queryFn: getServerStatus,
    refetchInterval: paused ? false : REFRESH_MS,
    refetchIntervalInBackground: false,
  });

  const status = query.data;
  const diskLevel = diskWarnLevel(status?.disk);
  const dbDown = status?.db_ok === false;
  const dbUnknown = status?.db_ok == null;

  const conclusion = useMemo(() => {
    if (!status) return null;
    if (dbDown) return { type: 'danger' as const, text: t('conclusion.dbDown') };
    if (diskLevel !== 'ok') return { type: 'warning' as const, text: t('conclusion.warn') };
    return { type: 'success' as const, text: t('conclusion.ok') };
  }, [status, dbDown, diskLevel, t]);

  const copySummary = async () => {
    if (!status) return;
    const lines = [
      `${t('cards.cpu')}: ${status.cpu?.usage_percent ?? t('cards.unread')}%`,
      `${t('cards.memory')}: ${status.memory?.used_mb ?? t('cards.unread')} / ${status.memory?.total_mb ?? t('cards.unread')} MB`,
      `${t('cards.disk')}: ${status.disk?.free_gb ?? t('cards.unread')} / ${status.disk?.total_gb ?? t('cards.unread')} GB`,
      `${t('cards.database')}: ${dbDown ? t('cards.dbDown') : dbUnknown ? t('cards.unread') : t('cards.dbOk')}`,
    ];
    if (status.version) lines.push(t('info.version', { version: status.version }));
    const at = fmtTime(status.collected_time);
    if (at) lines.push(t('info.updatedAt', { time: at }));
    try {
      await navigator.clipboard.writeText(lines.join('\n'));
      Toast.success(t('actions.copied'));
    } catch {
      Toast.error(t('loadFailed'));
    }
  };

  if (query.isError && !query.data) {
    return (
      <div className="p-6">
        <PageHeader title={t('title')} subtitle={t('subtitle')} />
        <ErrorState error={query.error} onRetry={() => void query.refetch()} />
      </div>
    );
  }

  const unread = t('cards.unread');
  const memUsed = status?.memory?.used_mb;
  const memTotal = status?.memory?.total_mb;
  const diskFree = status?.disk?.free_gb;
  const diskTotal = status?.disk?.total_gb;
  const memPercent =
    memUsed != null && memTotal ? Math.round((memUsed / memTotal) * 100) : null;
  const diskPercent =
    diskFree != null && diskTotal ? Math.round((diskFree / diskTotal) * 100) : null;

  return (
    <div className="p-6 flex flex-col gap-4">
      <PageHeader
        title={t('title')}
        subtitle={t('subtitle')}
        extra={
          <>
            <Button
              icon={paused ? <IconPlay /> : <IconPause />}
              onClick={() => setPaused((p) => !p)}
            >
              {paused ? t('actions.resume') : t('actions.pause')}
            </Button>
            <Button icon={<IconCopy />} onClick={() => void copySummary()} disabled={!status}>
              {t('actions.copy')}
            </Button>
          </>
        }
      />

      {paused ? (
        <Typography.Text type="secondary" size="small">
          {t('actions.paused')}
        </Typography.Text>
      ) : null}

      {conclusion ? (
        <Banner type={conclusion.type} description={conclusion.text} closeIcon={null} />
      ) : null}

      {dbDown ? (
        <Banner
          type="danger"
          description={
            <div className="flex flex-col gap-1">
              <span>{t('cards.dbDownDesc')}</span>
              <span>{t('cards.dbNext')}</span>
              {status?.db_checked_time ? (
                <span>{t('cards.dbCheckTime', { time: fmtTime(status.db_checked_time) })}</span>
              ) : null}
            </div>
          }
          closeIcon={null}
        />
      ) : null}

      {query.isLoading ? (
        <Skeleton placeholder={<Skeleton.Paragraph rows={4} />} loading />
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
          <Card>
            <div className="flex flex-col gap-2">
              <Typography.Text type="secondary">{t('cards.cpu')}</Typography.Text>
              <Typography.Title heading={3} style={{ margin: 0 }}>
                {status?.cpu?.usage_percent != null
                  ? `${status.cpu.usage_percent}%`
                  : unread}
              </Typography.Title>
              {status?.cpu?.usage_percent != null ? (
                <Progress percent={status.cpu.usage_percent} showInfo={false} aria-label={t('cards.cpu')} />
              ) : null}
              <Typography.Text type="tertiary" size="small">
                {t('cards.cpuSub')}
              </Typography.Text>
            </div>
          </Card>

          <Card>
            <div className="flex flex-col gap-2">
              <Typography.Text type="secondary">{t('cards.memory')}</Typography.Text>
              <Typography.Title heading={3} style={{ margin: 0 }}>
                {memUsed != null && memTotal != null
                  ? `${memUsed} / ${memTotal} MB`
                  : unread}
              </Typography.Title>
              {memPercent != null ? (
                <Progress percent={memPercent} showInfo={false} aria-label={t('cards.memory')} />
              ) : null}
              <Typography.Text type="tertiary" size="small">
                {t('cards.memorySub')}
              </Typography.Text>
            </div>
          </Card>

          <Card>
            <div className="flex flex-col gap-2">
              <Typography.Text type="secondary">{t('cards.disk')}</Typography.Text>
              <Typography.Title
                heading={3}
                style={{ margin: 0 }}
                type={diskLevel === 'danger' ? 'danger' : diskLevel === 'warn' ? 'warning' : undefined}
              >
                {diskFree != null && diskTotal != null
                  ? `${diskFree} / ${diskTotal} GB`
                  : unread}
              </Typography.Title>
              {diskPercent != null ? (
                <Progress
                  percent={diskPercent}
                  showInfo={false}
                  stroke={diskLevel === 'danger' ? 'var(--semi-color-danger)' : diskLevel === 'warn' ? 'var(--semi-color-warning)' : undefined}
                  aria-label={t('cards.disk')}
                />
              ) : null}
              {diskLevel !== 'ok' ? (
                <Link to="/data/file">
                  <Typography.Text link>{t('actions.goFiles')}</Typography.Text>
                </Link>
              ) : (
                <Typography.Text type="tertiary" size="small">
                  {t('cards.diskSub')}
                </Typography.Text>
              )}
            </div>
          </Card>

          <Card>
            <div className="flex flex-col gap-2">
              <Typography.Text type="secondary">{t('cards.database')}</Typography.Text>
              <Typography.Title
                heading={3}
                style={{ margin: 0 }}
                type={dbDown ? 'danger' : undefined}
              >
                {dbDown ? t('cards.dbDown') : dbUnknown ? unread : t('cards.dbOk')}
              </Typography.Title>
              <Typography.Text type="tertiary" size="small">
                {status?.db_checked_time
                  ? t('cards.dbCheckTime', { time: fmtTime(status.db_checked_time) })
                  : unread}
              </Typography.Text>
            </div>
          </Card>
        </div>
      )}

      {status ? (
        <Typography.Text type="tertiary" size="small">
          {[
            status.version ? t('info.version', { version: status.version }) : null,
            fmtTime(status.server_time)
              ? t('info.serverTime', { time: fmtTime(status.server_time) })
              : null,
            fmtDuration(status.process?.uptime_seconds)
              ? t('info.uptime', { duration: fmtDuration(status.process?.uptime_seconds) })
              : null,
            fmtTime(status.collected_time)
              ? t('info.updatedAt', { time: fmtTime(status.collected_time) })
              : null,
          ]
            .filter(Boolean)
            .join(' · ')}
        </Typography.Text>
      ) : null}
    </div>
  );
}
