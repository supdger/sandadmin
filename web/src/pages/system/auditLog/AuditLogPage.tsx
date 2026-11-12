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
import { useViewportWidth } from '@/pages/org/shared/useViewportWidth';
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

interface ChangeRow {
  key: string;
  label: string;
  before: string | null;
  after: string | null;
}

/**
 * 契约 changes 是对象 map：值是改后新值，或 {before, after} 对。
 * 键的中文名看 change_labels，未知键回显键名兜底；口令类值后端
 * 已替换为「已修改」标记，这里只回显、不再二次遮掩。
 */
function changeRows(
  changes: Record<string, unknown> | null | undefined,
  labels: Record<string, string> | null | undefined,
): ChangeRow[] {
  if (!changes || typeof changes !== 'object' || Array.isArray(changes)) return [];
  const rows: ChangeRow[] = [];
  for (const [key, value] of Object.entries(changes)) {
    const label = labels?.[key] ?? key;
    if (value && typeof value === 'object' && !Array.isArray(value)) {
      const pair = value as { before?: unknown; after?: unknown };
      const before = pair.before == null ? null : String(pair.before);
      const after = pair.after == null ? null : String(pair.after);
      // 前后相同的占位项不列。
      if (before === after) continue;
      rows.push({ key, label, before, after });
    } else {
      rows.push({ key, label, before: null, after: value == null ? null : String(value) });
    }
  }
  return rows;
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

  // 窄屏（<1440）藏「地点」列：契约 location 在条目里有，宽屏再展示。
  const viewportWidth = useViewportWidth();

  const columns = useMemo(
    () => [
      {
        title: t('columns.actor'),
        dataIndex: 'actor_name',
        render: (_: unknown, row: AuditLog) => row.actor_name,
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
      ...(viewportWidth >= 1440
        ? [
            {
              title: t('columns.location'),
              dataIndex: 'location',
              render: (_: unknown, row: AuditLog) => row.location,
            },
          ]
        : []),
      {
        title: t('columns.time'),
        dataIndex: 'create_time',
        render: (_: unknown, row: AuditLog) => formatDateTime(row.create_time),
      },
    ],
    [t, viewportWidth],
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
  const changes = changeRows(detail?.changes, detail?.change_labels);

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
          presets={[
            { text: t('filters.rangeToday'), start: () => new Date(), end: () => new Date() },
            {
              text: t('filters.range7d'),
              start: () => new Date(Date.now() - 6 * 86_400_000),
              end: () => new Date(),
            },
            {
              text: t('filters.range30d'),
              start: () => new Date(Date.now() - 29 * 86_400_000),
              end: () => new Date(),
            },
          ]}
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
              // 契约：module-options 的 data 直接是 [{key, name}] 数组。
              ...(modulesQuery.data ?? []).map((m) => ({ value: m.key, label: m.name })),
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
                { key: t('columns.actor'), value: detail.actor_name },
                { key: t('columns.action'), value: detail.action_text },
                { key: t('columns.module'), value: detail.module_label ?? '' },
                { key: t('columns.result'), value: detail.succeeded ? t('result.ok') : t('result.failed') },
                { key: t('columns.time'), value: formatDateTime(detail.create_time) },
                ...(detail.location ? [{ key: t('columns.location'), value: detail.location }] : []),
                ...(detail.device_label ? [{ key: t('detail.device'), value: detail.device_label }] : []),
                ...(detail.ip ? [{ key: t('detail.source'), value: detail.ip }] : []),
                { key: t('detail.request'), value: detail.request_id },
              ]}
            />
            <div>
              <div className="font-medium mb-2">{t('detail.changes')}</div>
              {changes.length === 0 ? (
                <div>{t('detail.noChanges')}</div>
              ) : (
                <Table<ChangeRow>
                  columns={[
                    { title: '', dataIndex: 'label', width: 120 },
                    {
                      title: t('detail.before'),
                      dataIndex: 'before',
                      render: (_: unknown, c: ChangeRow) => c.before ?? '',
                    },
                    {
                      title: t('detail.after'),
                      dataIndex: 'after',
                      render: (_: unknown, c: ChangeRow) => c.after ?? '',
                    },
                  ]}
                  dataSource={changes}
                  rowKey="key"
                  pagination={false}
                />
              )}
            </div>
          </div>
        ) : null}
      </SideSheet>
    </div>
  );
}
