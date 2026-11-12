import { useEffect, useMemo, useRef, useState } from 'react';

import {
  Button,
  Dropdown,
  Input,
  Pagination,
  Select,
  Skeleton,
  Table,
  Tag,
  Toast,
  Typography,
} from '@douyinfe/semi-ui';
import type { ColumnProps } from '@douyinfe/semi-ui/lib/es/table/interface';
import { IconMore, IconPlus, IconSearch } from '@douyinfe/semi-icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { useSearchParams } from 'react-router';

import {
  deleteAnnouncement,
  listAnnouncements,
  publishAnnouncement,
  revokeAnnouncement,
  type Announcement,
  type AnnouncementStateFilter,
} from '@/api/v1/announcements';
import { Empty } from '@/components/Empty';
import { ErrorState } from '@/components/ErrorState';
import { PageHeader } from '@/components/PageHeader';
import { useCan } from '@/hooks/useCan';
import { adminSaveErrorMessage } from '@/lib/adminError';
import { AnnouncementEditor } from './AnnouncementEditor';
import { formatDateTime } from './format';
import { ReadStatsDrawer } from './ReadStatsDrawer';
import { useDebouncedValue } from './useDebouncedValue';
import { confirmAction } from '@/lib/confirmAction';

const ANNOUNCEMENTS_QUERY_KEY = ['announcements'] as const;
const PAGE_SIZE = 20;
// expired 是派生筛选（expire_time 已过即算），Announcement.state 本身没有这个值。
const STATE_OPTIONS = ['draft', 'published', 'withdrawn', 'expired'] as const;

function statusTag(a: Announcement, t: (key: string) => string) {
  if (a.state === 'draft') return <Tag color="grey" size="small">{t('filters.draft')}</Tag>;
  if (a.state === 'withdrawn') return <Tag color="grey" size="small">{t('filters.withdrawn')}</Tag>;
  if (a.expired) return <Tag color="grey" size="small">{t('filters.expired')}</Tag>;
  return <Tag color="green" size="small">{t('filters.published')}</Tag>;
}

/** 公告管理：列表 + 编辑抽屉 + 已读情况抽屉。 */
export function AnnouncementsPage() {
  const { t } = useTranslation('announcements');
  const queryClient = useQueryClient();
  const [searchParams, setSearchParams] = useSearchParams();

  const canCreate = useCan('system:announcement:create');
  const canUpdate = useCan('system:announcement:update');
  const canPublish = useCan('system:announcement:publish');
  const canRevoke = useCan('system:announcement:revoke');
  const canDelete = useCan('system:announcement:delete');

  const keywordParam = searchParams.get('kw') ?? '';
  const [keywordInput, setKeywordInput] = useState(keywordParam);
  const debouncedKeyword = useDebouncedValue(keywordInput);
  const state = searchParams.get('state') ?? '';
  const page = Math.max(1, Number(searchParams.get('page') ?? '1') || 1);
  const pageSize = Math.min(100, Math.max(1, Number(searchParams.get('size') ?? String(PAGE_SIZE)) || PAGE_SIZE));

  const [editor, setEditor] = useState<{ open: boolean; announcement: Announcement | null }>({
    open: false,
    announcement: null,
  });
  const [statsTarget, setStatsTarget] = useState<Announcement | null>(null);

  // 输入防抖后写回 URL；关键词变化回第一页。
  const prevKeyword = useRef(debouncedKeyword);
  useEffect(() => {
    if (prevKeyword.current === debouncedKeyword) return;
    prevKeyword.current = debouncedKeyword;
    setSearchParams(
      (prev) => {
        const next = new URLSearchParams(prev);
        const changed = (next.get('kw') ?? '') !== debouncedKeyword;
        if (debouncedKeyword) next.set('kw', debouncedKeyword);
        else next.delete('kw');
        if (changed) next.delete('page');
        return next;
      },
      { replace: true },
    );
  }, [debouncedKeyword, setSearchParams]);

  const apiParams = useMemo(
    () => ({
      keyword: debouncedKeyword || undefined,
      state: (state || undefined) as AnnouncementStateFilter | undefined,
      page,
      page_size: pageSize,
    }),
    [debouncedKeyword, state, page, pageSize],
  );

  const list = useQuery({
    queryKey: [...ANNOUNCEMENTS_QUERY_KEY, 'list', apiParams],
    queryFn: () => listAnnouncements(apiParams),
  });

  const items = list.data?.items ?? [];

  // 列表超过 10 秒还没出来：骨架屏下面加一句解释和「重新加载」。
  const [slowLoading, setSlowLoading] = useState(false);
  useEffect(() => {
    if (!list.isPending) return;
    const timer = setTimeout(() => setSlowLoading(true), 10_000);
    return () => clearTimeout(timer);
  }, [list.isPending]);
  const showSlowLoading = list.isPending && slowLoading;

  const invalidate = () => {
    void queryClient.invalidateQueries({ queryKey: ANNOUNCEMENTS_QUERY_KEY });
    // 发布后铃铛未读数要马上变，不等 60 秒轮询。
    void queryClient.invalidateQueries({ queryKey: ['notifications'] });
  };

  // 写操作失败文案：STALE_VERSION 是产品定死口径，其余用后端大白话/兜底。
  const actionError = (error: unknown) =>
    adminSaveErrorMessage(error, t('confirm.publishFailed'), t('conflict'));

  const publish = useMutation({
    mutationFn: (a: Announcement) => publishAnnouncement(a.id),
    onSuccess: async (saved) => {
      Toast.success(t('confirm.published', { name: saved.title }));
      await invalidate();
    },
    onError: (error) => Toast.error(actionError(error)),
  });

  const revoke = useMutation({
    mutationFn: (a: Announcement) => revokeAnnouncement(a.id),
    onSuccess: async (saved) => {
      Toast.success(t('confirm.revoked', { name: saved.title }));
      await invalidate();
    },
    onError: (error) => Toast.error(actionError(error)),
  });

  const remove = useMutation({
    mutationFn: (a: Announcement) => deleteAnnouncement(a.id),
    onSuccess: async (_result, a) => {
      Toast.success(t('confirm.deleted', { name: a.title }));
      await invalidate();
    },
    onError: (error) => Toast.error(actionError(error)),
  });

  const requestPublish = (a: Announcement) => {
    confirmAction({
      title: t('editor.publishConfirmTitle', { name: a.title }),
      content:
        a.scope === 'dept'
          ? t('editor.publishDeptContent', { count: a.recipient_count })
          : t('editor.publishConfirmContent', { count: a.recipient_count }),
      okText: a.state === 'withdrawn' ? t('table.republish') : t('table.publish'),
      cancelText: t('editor.think'),
      onOk: () => publish.mutateAsync(a).catch(() => undefined),
    });
  };

  const requestRevoke = (a: Announcement) => {
    confirmAction({
      title: t('confirm.revokeTitle', { name: a.title }),
      content: t('confirm.revokeContent'),
      okText: t('confirm.revokeOk'),
      cancelText: t('confirm.think'),
      okType: 'primary',
      onOk: () => revoke.mutateAsync(a).catch(() => undefined),
    });
  };

  const requestDelete = (a: Announcement) => {
    confirmAction({
      title: t('confirm.deleteTitle', { name: a.title }),
      content: t('confirm.deleteContent'),
      okText: t('confirm.deleteOk'),
      cancelText: t('confirm.think'),
      okType: 'danger',
      onOk: () => remove.mutateAsync(a).catch(() => undefined),
    });
  };

  const patchParams = (patch: Record<string, string | null>, resetPage = true) => {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev);
      for (const [key, value] of Object.entries(patch)) {
        if (value === null || value === '') next.delete(key);
        else next.set(key, value);
      }
      if (resetPage) next.delete('page');
      return next;
    });
  };

  const filtering = Boolean(debouncedKeyword || state);
  const total = list.data?.total ?? 0;

  const columns: ColumnProps<Announcement>[] = [
    {
      title: t('table.title'),
      render: (_text, record) => (
        <div className="flex min-w-0 items-center gap-1.5">
          {record.pinned ? <Tag size="small" color="orange">{t('table.pinned')}</Tag> : null}
          <Typography.Text strong ellipsis={{ showTooltip: true }}>
            {record.title}
          </Typography.Text>
        </div>
      ),
    },
    {
      title: t('table.scope'),
      width: 160,
      render: (_text, record) =>
        record.scope === 'all' ? t('table.scopeAll') : record.dept_names.join('、'),
    },
    {
      title: t('table.status'),
      width: 90,
      render: (_text, record) => statusTag(record, t),
    },
    {
      title: t('table.readStats'),
      width: 100,
      render: (_text, record) => {
        if (record.state === 'draft') return <Typography.Text type="tertiary">—</Typography.Text>;
        // 已读数来自列表行自带字段；read-stats 接口只留给明细抽屉。
        return (
          <button
            type="button"
            className="text-[var(--semi-color-primary)] hover:underline"
            onClick={() => setStatsTarget(record)}
          >
            {record.read_count} / {record.recipient_count}
          </button>
        );
      },
    },
    {
      title: t('table.publishedTime'),
      width: 180,
      render: (_text, record) =>
        record.published_time ? formatDateTime(record.published_time) : t('table.unpublished'),
    },
    {
      title: t('table.actions'),
      width: 140,
      fixed: 'right',
      render: (_text, record) => (
        <div className="flex items-center gap-1">
          {canUpdate ? (
            <Button theme="borderless" size="small" onClick={() => setEditor({ open: true, announcement: record })}>
              {t('table.edit')}
            </Button>
          ) : null}
          {canPublish || canRevoke || canDelete ? (
            <Dropdown
              trigger="click"
              position="bottomRight"
              render={
                <Dropdown.Menu>
                  {canPublish && (record.state === 'draft' || record.state === 'withdrawn') ? (
                    <Dropdown.Item onClick={() => requestPublish(record)}>
                      {record.state === 'withdrawn' ? t('table.republish') : t('table.publish')}
                    </Dropdown.Item>
                  ) : null}
                  {canRevoke && record.state === 'published' ? (
                    <Dropdown.Item onClick={() => requestRevoke(record)}>{t('table.revoke')}</Dropdown.Item>
                  ) : null}
                  {canDelete ? (
                    <Dropdown.Item type="danger" onClick={() => requestDelete(record)}>
                      {t('table.delete')}
                    </Dropdown.Item>
                  ) : null}
                </Dropdown.Menu>
              }
            >
              <Button theme="borderless" size="small" icon={<IconMore />} aria-label={t('table.more')} />
            </Dropdown>
          ) : null}
        </div>
      ),
    },
  ];

  return (
    <div className="flex flex-col gap-4">
      <PageHeader
        title={t('title')}
        subtitle={t('subtitle')}
        extra={
          canCreate ? (
            <Button theme="solid" icon={<IconPlus />} onClick={() => setEditor({ open: true, announcement: null })}>
              {t('write')}
            </Button>
          ) : null
        }
      />

      <div className="flex flex-wrap items-center gap-3">
        <Input
          prefix={<IconSearch />}
          placeholder={t('filters.keywordPlaceholder')}
          value={keywordInput}
          onChange={setKeywordInput}
          showClear
          className="w-56"
        />
        <Select
          placeholder={t('filters.stateAll')}
          value={state || undefined}
          showClear
          className="w-32"
          onChange={(v) => patchParams({ state: (v as string) || null })}
          optionList={STATE_OPTIONS.map((s) => ({
            value: s,
            label: t(`filters.${s}`),
          }))}
        />
        {filtering ? (
          <Button
            theme="borderless"
            type="tertiary"
            onClick={() => {
              setKeywordInput('');
              patchParams({ kw: null, state: null });
            }}
          >
            {t('clearFilters')}
          </Button>
        ) : null}
      </div>

      {list.isPaused || list.isError ? (
        <div className="rounded-lg border border-[var(--semi-color-border)] p-4">
          <ErrorState
            kind="error"
            error={list.error}
            paused={list.isPaused}
            onRetry={() => void list.refetch()}
            showHome={false}
          />
        </div>
      ) : list.isPending ? (
        <div className="rounded-lg border border-[var(--semi-color-border)] p-4">
          <Skeleton placeholder={<Skeleton.Paragraph rows={8} />} loading />
          {showSlowLoading ? (
            <div className="mt-3 flex items-center justify-center gap-2">
              <Typography.Text type="secondary" size="small">
                {t('slowLoading')}
              </Typography.Text>
              <Button size="small" onClick={() => void list.refetch()}>
                {t('reload')}
              </Button>
            </div>
          ) : null}
        </div>
      ) : (
        <>
          <Table<Announcement>
            size="default"
            columns={columns}
            dataSource={items}
            rowKey="id"
            pagination={false}
            empty={
              <Empty
                title={filtering ? t('noResult') : t('empty.title')}
                description={filtering ? undefined : t('empty.desc')}
              >
                {filtering ? (
                  <Button
                    size="small"
                    onClick={() => {
                      setKeywordInput('');
                      patchParams({ kw: null, state: null });
                    }}
                  >
                    {t('clearFilters')}
                  </Button>
                ) : canCreate ? (
                  <Button
                    size="small"
                    theme="solid"
                    onClick={() => setEditor({ open: true, announcement: null })}
                  >
                    {t('write')}
                  </Button>
                ) : null}
              </Empty>
            }
          />
          {total > pageSize ? (
            <div className="flex justify-end">
              <Pagination
                total={total}
                currentPage={page}
                pageSize={pageSize}
                onChange={(p, size) => patchParams({ page: String(p), size: size === PAGE_SIZE ? null : String(size) }, false)}
                showTotal
                showSizeChanger
              />
            </div>
          ) : null}
        </>
      )}

      <AnnouncementEditor
        key={editor.announcement?.id ?? 'new-announcement'}
        visible={editor.open}
        announcement={editor.announcement}
        canPublish={canPublish}
        onClose={() => setEditor({ open: false, announcement: null })}
      />
      <ReadStatsDrawer
        visible={statsTarget !== null}
        announcement={statsTarget}
        onClose={() => setStatsTarget(null)}
      />
    </div>
  );
}
