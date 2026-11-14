import { useMemo, useState } from 'react';

import {
  Button,
  DatePicker,
  Descriptions,
  Input,
  Pagination,
  Select,
  SideSheet,
  Table,
  Tag,
  Toast,
} from '@douyinfe/semi-ui';
import { IconSearch } from '@douyinfe/semi-icons';
import { IllustrationNoAccess, IllustrationNoResult } from '@douyinfe/semi-illustrations';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import {
  exportLoginLogs,
  getLoginLog,
  listLoginLogs,
  type LoginLog,
  type LoginLogQuery,
} from '@/api/v1/loginLogs';
import { Empty } from '@/components/Empty';
import { ErrorState } from '@/components/ErrorState';
import { PageHeader } from '@/components/PageHeader';
import { useCan } from '@/hooks/useCan';
import { useUrlQuery } from '@/hooks/useUrlQuery';
import { useViewportWidth } from '@/pages/org/shared/useViewportWidth';
import { adminErrorMessage } from '@/lib/adminError';
import { formatDateTime } from '@/lib/format';
import { runExport } from '@/pages/system/shared/runExport';

const PAGE_SIZE = 20;
/** 查询范围最多最近 180 天。 */
const MAX_RANGE_DAYS = 180;

const URL_FIELDS = {
  keyword: {},
  succeeded: {},
  login_time_from: {},
  login_time_to: {},
  day: {},
  page: { kind: 'number' as const, defaultValue: 1 },
};

function todayRange(): { from: string; to: string } {
  const now = new Date();
  const pad = (n: number) => String(n).padStart(2, '0');
  const today = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
  return { from: today, to: today };
}

function daysAgoRange(days: number): { from: string; to: string } {
  const end = todayRange().to;
  const start = new Date(Date.now() - (days - 1) * 86_400_000);
  const pad = (n: number) => String(n).padStart(2, '0');
  return { from: `${start.getFullYear()}-${pad(start.getMonth() + 1)}-${pad(start.getDate())}`, to: end };
}

export function LoginLogPage() {
  const { t } = useTranslation('loginLog');
  const navigate = useNavigate();
  const canIndex = useCan('system:login-log:index');
  const canExport = useCan('system:login-log:export');

  const { values, setValues } = useUrlQuery(URL_FIELDS);

  // 默认最近 7 天；?day=today（工作台入口）取今天。
  const defaults = useMemo(() => {
    if (values.day === 'today') return todayRange();
    return daysAgoRange(7);
  }, [values.day]);

  const keyword = (values.keyword as string | undefined) ?? '';
  const succeeded = (values.succeeded as string | undefined) ?? '';
  const from = (values.login_time_from as string | undefined) ?? defaults.from;
  const to = (values.login_time_to as string | undefined) ?? defaults.to;
  const page = (values.page as number | undefined) ?? 1;

  const queryParams: LoginLogQuery = {
    keyword: keyword || undefined,
    succeeded: succeeded === '' ? undefined : succeeded === 'true',
    login_time_from: from || undefined,
    login_time_to: to || undefined,
    page,
    page_size: PAGE_SIZE,
  };

  const query = useQuery({
    queryKey: ['loginLogs', queryParams],
    queryFn: () => listLoginLogs(queryParams),
    enabled: canIndex,
  });

  const [detailId, setDetailId] = useState<string | null>(null);
  const detailQuery = useQuery({
    queryKey: ['loginLogs', 'detail', detailId],
    queryFn: () => getLoginLog(detailId!),
    enabled: detailId !== null,
  });

  const [exporting, setExporting] = useState(false);
  const doExport = async () => {
    setExporting(true);
    try {
      const outcome = await runExport(
        () => exportLoginLogs({ ...queryParams, page: undefined, page_size: undefined }),
        'login-logs.csv',
      );
      if (outcome.ok) {
        Toast.success(t('exportAccepted'));
      } else {
        Toast.error(outcome.message ?? t('loadFailed'));
      }
    } catch (error) {
      Toast.error(adminErrorMessage(error, t('loadFailed')));
    } finally {
      setExporting(false);
    }
  };

  const disabledDate = (date?: Date) => {
    if (!date) return false;
    const min = Date.now() - MAX_RANGE_DAYS * 86_400_000;
    return date.getTime() < min - 86_400_000 || date.getTime() > Date.now() + 86_400_000;
  };

  const hasFilter = Boolean(keyword || succeeded || values.login_time_from || values.login_time_to);

  const clearFilters = () =>
    setValues({
      keyword: undefined,
      succeeded: undefined,
      login_time_from: undefined,
      login_time_to: undefined,
      day: undefined,
      page: 1,
    });

  // 窄屏（<1440）藏「设备」列：横向滚动空间先留给结果与原因。
  const viewportWidth = useViewportWidth();

  const columns = useMemo(
    () => [
      {
        title: t('columns.user'),
        dataIndex: 'nickname',
        render: (_: unknown, row: LoginLog) => row.nickname ?? row.username,
      },
      {
        title: t('columns.result'),
        dataIndex: 'succeeded',
        render: (_: unknown, row: LoginLog) =>
          row.succeeded ? (
            <Tag color="green" size="small">{t('result.ok')}</Tag>
          ) : (
            <Tag color="red" size="small">{t('result.failed')}</Tag>
          ),
      },
      {
        title: t('columns.reason'),
        dataIndex: 'reason_text',
        // 成功行留空；失败但后端没给人话原因时按规格兜底「没有成功」。
        render: (_: unknown, row: LoginLog) =>
          row.succeeded ? '' : row.reason_text || t('result.failed'),
      },
      {
        title: t('columns.time'),
        dataIndex: 'login_time',
        render: (_: unknown, row: LoginLog) => formatDateTime(row.login_time),
      },
      {
        title: t('columns.location'),
        dataIndex: 'location',
        render: (_: unknown, row: LoginLog) => row.location,
      },
      ...(viewportWidth >= 1440
        ? [
            {
              title: t('columns.device'),
              dataIndex: 'device_label',
              render: (_: unknown, row: LoginLog) => row.device_label,
            },
          ]
        : []),
    ],
    [t, viewportWidth],
  );

  if (!canIndex) {
    return (
      <div className="p-6">
        <PageHeader title={t('title')} subtitle={t('subtitle')} />
        <Empty image={<IllustrationNoAccess />} title={t('noPermission')} description="">
          <Button type="primary" onClick={() => navigate('/')}>
            {t('empty.backHome')}
          </Button>
        </Empty>
      </div>
    );
  }

  if (query.isError && !query.data) {
    return (
      <div className="p-6">
        <PageHeader title={t('title')} subtitle={t('subtitle')} />
        <ErrorState error={query.error} onRetry={() => void query.refetch()} />
      </div>
    );
  }

  const detail = detailQuery.data;

  return (
    <div className="p-6 flex flex-col gap-4">
      <PageHeader
        title={t('title')}
        subtitle={t('subtitle')}
        extra={
          canExport ? (
            <Button onClick={() => void doExport()} loading={exporting} disabled={exporting}>
              {t('filters.export')}
            </Button>
          ) : undefined
        }
      />

      <div className="flex flex-wrap items-center gap-3">
        <Input
          prefix={<IconSearch />}
          placeholder={t('filters.keywordPlaceholder')}
          value={keyword}
          onChange={(v) => setValues({ keyword: v || undefined, page: 1 })}
          style={{ width: 220 }}
          showClear
        />
        <Select
          placeholder={t('filters.result')}
          value={succeeded}
          optionList={[
            { value: '', label: t('filters.resultAll') },
            { value: 'true', label: t('filters.succeeded') },
            { value: 'false', label: t('filters.failed') },
          ]}
          onChange={(v) => setValues({ succeeded: (v as string) || undefined, page: 1 })}
          style={{ width: 140 }}
        />
        <DatePicker
          type="dateRange"
          value={[from, to]}
          disabledDate={disabledDate}
          placeholder={t('filters.rangePlaceholder', { returnObjects: true }) as string[]}
          onChange={(dates) => {
            if (Array.isArray(dates) && dates.length === 2 && dates[0] && dates[1]) {
              const fmt = (d: Date | string) => {
                const dd = typeof d === 'string' ? new Date(d) : d;
                const pad = (n: number) => String(n).padStart(2, '0');
                return `${dd.getFullYear()}-${pad(dd.getMonth() + 1)}-${pad(dd.getDate())}`;
              };
              setValues(
                {
                  login_time_from: fmt(dates[0]),
                  login_time_to: fmt(dates[1]),
                  day: undefined,
                  page: 1,
                },
              );
            }
          }}
        />
        {hasFilter ? (
          <Button onClick={clearFilters}>{t('filters.clear')}</Button>
        ) : null}
      </div>

      <Table<LoginLog>
        columns={columns}
        dataSource={query.data?.items ?? []}
        rowKey="id"
        loading={query.isLoading}
        pagination={false}
        empty={
          hasFilter ? (
            <Empty
              image={<IllustrationNoResult />}
              title={t('empty.noResult')}
              description={t('empty.noResultDesc')}
            >
              <Button size="small" onClick={clearFilters}>
                {t('empty.clearFilters')}
              </Button>
            </Empty>
          ) : (
            <Empty title={t('empty.noData')} description={t('empty.noDataDesc')} />
          )
        }
        scroll={{ x: 900 }}
        onRow={(row) => ({
          onClick: () => row && setDetailId(row.id),
          style: { cursor: 'pointer' },
        })}
      />

      <div className="flex justify-end">
        <Pagination
          total={query.data?.total ?? 0}
          currentPage={page}
          pageSize={PAGE_SIZE}
          onPageChange={(p) => setValues({ page: p })}
          showTotal={false}
          style={{ display: (query.data?.total ?? 0) > PAGE_SIZE ? undefined : 'none' }}
        />
      </div>

      <SideSheet
        visible={detailId !== null}
        onCancel={() => setDetailId(null)}
        title={t('detail.title')}
        footer={
          // 规格：底部按钮固定右下，主按钮（查看用户）在最右。
          <div className="flex justify-end gap-2 w-full">
            <Button onClick={() => setDetailId(null)}>{t('detail.close')}</Button>
            {detail?.user_id ? (
              <Button
                type="primary"
                onClick={() => {
                  setDetailId(null);
                  // UsersPage 的 URL 筛选字段是 keyword；用账号定位更准。
                  navigate(`/system/user?keyword=${encodeURIComponent(detail.username)}`);
                }}
              >
                {t('detail.viewUser')}
              </Button>
            ) : null}
          </div>
        }
      >
        {detailQuery.isLoading ? (
          <div>{t('loadFailed')}</div>
        ) : detail ? (
          <Descriptions
            align="left"
            data={[
              { key: t('columns.user'), value: detail.nickname ?? detail.username },
              { key: t('columns.result'), value: detail.succeeded ? t('result.ok') : t('result.failed') },
              ...(detail.succeeded ? [] : [{ key: t('columns.reason'), value: detail.reason_text || t('result.failed') }]),
              { key: t('columns.time'), value: formatDateTime(detail.login_time) },
              { key: t('columns.location'), value: detail.location },
              { key: t('columns.device'), value: detail.device_label },
              // 规格：「登录设备地址」显示 ip；user_agent 仅排查/导出用，页面不渲染。
              { key: t('detail.address'), value: detail.ip },
            ]}
          />
        ) : null}
      </SideSheet>
    </div>
  );
}
