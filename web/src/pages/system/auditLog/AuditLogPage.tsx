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
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import {
  exportAuditLogs,
  getAuditLog,
  getAuditModuleOptions,
  listAuditLogs,
  type AuditLog,
  type AuditLogQuery,
} from '@/api/v1/auditLogs';
import { getUserOptions, type UserOption } from '@/api/v1/org';
import { Empty } from '@/components/Empty';
import { ErrorState } from '@/components/ErrorState';
import { PageHeader } from '@/components/PageHeader';
import { useCan } from '@/hooks/useCan';
import { useUrlQuery } from '@/hooks/useUrlQuery';
import { adminErrorMessage } from '@/lib/adminError';
import { formatDateTime } from '@/lib/format';
import { runExport } from '@/pages/system/shared/runExport';

const PAGE_SIZE = 20;
const MAX_RANGE_DAYS = 180;

const URL_FIELDS = {
  keyword: {},
  actor_id: {},
  module: {},
  succeeded: {},
  create_time_from: {},
  create_time_to: {},
  page: { kind: 'number' as const, defaultValue: 1 },
};

function fmtDate(d: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/** 密码/密钥类改动：只显示「已修改」。 */
function isMasked(label: string, value: string | null): boolean {
  return /密码|口令|密钥|secret/i.test(label) || value === '***';
}

export function AuditLogPage() {
  const { t } = useTranslation('auditLog');
  const canIndex = useCan('system:audit-log:index');
  const canExport = useCan('system:audit-log:export');

  const { values, setValues } = useUrlQuery(URL_FIELDS);
  const keyword = (values.keyword as string | undefined) ?? '';
  const actorId = (values.actor_id as string | undefined) ?? '';
  const moduleKey = (values.module as string | undefined) ?? '';
  const succeeded = (values.succeeded as string | undefined) ?? '';
  const from = (values.create_time_from as string | undefined) ?? '';
  const to = (values.create_time_to as string | undefined) ?? '';
  const page = (values.page as number | undefined) ?? 1;
  const [moreOpen, setMoreOpen] = useState(false);

  const queryParams: AuditLogQuery = {
    keyword: keyword || undefined,
    actor_id: actorId || undefined,
    module: moduleKey || undefined,
    succeeded: succeeded === '' ? undefined : succeeded === 'true',
    create_time_from: from || undefined,
    create_time_to: to || undefined,
    page,
    page_size: PAGE_SIZE,
  };

  const query = useQuery({
    queryKey: ['auditLogs', queryParams],
    queryFn: () => listAuditLogs(queryParams),
    enabled: canIndex,
  });

  const modulesQuery = useQuery({
    queryKey: ['auditLogs', 'moduleOptions'],
    queryFn: getAuditModuleOptions,
    enabled: canIndex,
    staleTime: 5 * 60_000,
  });

  const actorsQuery = useQuery({
    queryKey: ['org', 'userOptions'],
    queryFn: () => getUserOptions(),
    enabled: canIndex,
    staleTime: 5 * 60_000,
  });

  const [detailId, setDetailId] = useState<string | null>(null);
  const detailQuery = useQuery({
    queryKey: ['auditLogs', 'detail', detailId],
    queryFn: () => getAuditLog(detailId!),
    enabled: detailId !== null,
  });

  const [exporting, setExporting] = useState(false);
  const doExport = async () => {
    setExporting(true);
    try {
      const outcome = await runExport(
        () => exportAuditLogs({ ...queryParams, page: undefined, page_size: undefined }),
        'audit-logs.csv',
      );
      if (outcome.ok) Toast.success(t('exportAccepted'));
      else Toast.error(outcome.message ?? t('loadFailed'));
    } catch (error) {
      Toast.error(adminErrorMessage(error, t('loadFailed')));
    } finally {
      setExporting(false);
    }
  };

  const disabledDate = (date?: Date) => {
    if (!date) return false;
    return date.getTime() < Date.now() - (MAX_RANGE_DAYS + 1) * 86_400_000;
  };

  const hasFilter = Boolean(keyword || actorId || moduleKey || succeeded || from || to);

  const columns = useMemo(
    () => [
      {
        title: t('columns.actor'),
        dataIndex: 'actor_name',
        render: (_: unknown, row: AuditLog) => row.actor_name ?? '',
      },
      { title: t('columns.action'), dataIndex: 'action_text' },
      {
        title: t('columns.module'),
        dataIndex: 'module_label',
        render: (_: unknown, row: AuditLog) => row.module_label ?? '',
      },
      {
        title: t('columns.result'),
        dataIndex: 'succeeded',
        render: (_: unknown, row: AuditLog) =>
          row.succeeded ? (
            <Tag color="green" size="small">{t('result.ok')}</Tag>
          ) : (
            <Tag color="red" size="small">{t('result.failed')}</Tag>
          ),
      },
      {
        title: t('columns.time'),
        dataIndex: 'create_time',
        render: (_: unknown, row: AuditLog) => formatDateTime(row.create_time),
      },
    ],
    [t],
  );

  if (!canIndex) {
    return (
      <div className="p-6">
        <PageHeader title={t('title')} subtitle={t('subtitle')} />
        <Empty title={t('noPermission')} />
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
  const changes = (detail?.changes ?? []).filter((c) => c.before !== c.after || isMasked(c.label, c.after));

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
          placeholder={t('filters.actorPlaceholder')}
          value={actorId || undefined}
          optionList={(actorsQuery.data?.items ?? []).map((u: UserOption) => ({
            value: u.id,
            label: u.nickname,
          }))}
          onChange={(v) => setValues({ actor_id: (v as string) || undefined, page: 1 })}
          style={{ width: 160 }}
          showClear
          filter
        />
        <DatePicker
          type="dateRange"
          value={from && to ? [from, to] : undefined}
          disabledDate={disabledDate}
          placeholder={t('filters.rangePlaceholder')}
          onChange={(dates) => {
            if (Array.isArray(dates) && dates.length === 2 && dates[0] && dates[1]) {
              const toDate = (d: Date | string) => fmtDate(typeof d === 'string' ? new Date(d) : d);
              setValues({ create_time_from: toDate(dates[0]), create_time_to: toDate(dates[1]), page: 1 });
            } else if (!dates || (Array.isArray(dates) && dates.length === 0)) {
              setValues({ create_time_from: undefined, create_time_to: undefined, page: 1 });
            }
          }}
        />
        <Button onClick={() => setMoreOpen((o) => !o)}>
          {moreOpen ? t('filters.collapse') : t('filters.more')}
        </Button>
        {hasFilter ? (
          <Button
            onClick={() =>
              setValues({
                keyword: undefined,
                actor_id: undefined,
                module: undefined,
                succeeded: undefined,
                create_time_from: undefined,
                create_time_to: undefined,
                page: 1,
              })
            }
          >
            {t('filters.clear')}
          </Button>
        ) : null}
      </div>

      {moreOpen ? (
        <div className="flex flex-wrap items-center gap-3">
          <Select
            placeholder={t('filters.module')}
            value={moduleKey || undefined}
            optionList={[
              { value: '', label: t('filters.moduleAll') },
              ...(modulesQuery.data?.items ?? []).map((m) => ({ value: m.key, label: m.label })),
            ]}
            onChange={(v) => setValues({ module: (v as string) || undefined, page: 1 })}
            style={{ width: 180 }}
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
        </div>
      ) : null}

      <Table<AuditLog>
        columns={columns}
        dataSource={query.data?.items ?? []}
        rowKey="id"
        loading={query.isLoading}
        pagination={false}
        empty={<Empty title={hasFilter ? t('empty.noResult') : t('empty.noData')} />}
        scroll={{ x: 860 }}
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
          <div className="flex justify-end w-full">
            <Button onClick={() => setDetailId(null)}>{t('detail.close')}</Button>
          </div>
        }
      >
        {detail ? (
          <div className="flex flex-col gap-4">
            <Descriptions
              align="left"
              data={[
                { key: t('columns.actor'), value: detail.actor_name ?? '' },
                { key: t('columns.action'), value: detail.action_text },
                { key: t('columns.module'), value: detail.module_label ?? '' },
                { key: t('columns.result'), value: detail.succeeded ? t('result.ok') : t('result.failed') },
                { key: t('columns.time'), value: formatDateTime(detail.create_time) },
              ]}
            />
            <div>
              <div className="font-medium mb-2">{t('detail.changes')}</div>
              {changes.length === 0 ? (
                <div>{t('detail.noChanges')}</div>
              ) : (
                <Table<{ label: string; before: string | null; after: string | null }>
                  columns={[
                    { title: '', dataIndex: 'label', width: 120 },
                    {
                      title: t('detail.before'),
                      dataIndex: 'before',
                      render: (_: unknown, c: { label: string; before: string | null; after: string | null }) =>
                        isMasked(c.label, c.before) ? t('detail.masked') : (c.before ?? ''),
                    },
                    {
                      title: t('detail.after'),
                      dataIndex: 'after',
                      render: (_: unknown, c: { label: string; before: string | null; after: string | null }) =>
                        isMasked(c.label, c.after) ? t('detail.masked') : (c.after ?? ''),
                    },
                  ]}
                  dataSource={changes}
                  rowKey="label"
                  pagination={false}
                />
              )}
            </div>
            {detail.remark ? (
              <Descriptions align="left" data={[{ key: t('detail.remark'), value: detail.remark }]} />
            ) : null}
          </div>
        ) : null}
      </SideSheet>
    </div>
  );
}
