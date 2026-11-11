import { useEffect, useMemo, useRef, useState } from 'react';

import {
  Button,
  Checkbox,
  DatePicker,
  Dropdown,
  ImagePreview,
  Input,
  Modal,
  Pagination,
  Radio,
  Select,
  Skeleton,
  Table,
  Tag,
  Toast,
  Typography,
} from '@douyinfe/semi-ui';
import type { ColumnProps } from '@douyinfe/semi-ui/lib/es/table/interface';
import {
  IconDownload,
  IconEyeOpened,
  IconMore,
  IconSearch,
  IconUpload,
} from '@douyinfe/semi-icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { useSearchParams } from 'react-router';

import {
  cleanupFiles,
  deleteFile,
  downloadFile,
  getTask,
  listFiles,
  uploadFile,
  type FileObject,
} from '@/api/v1/files';
import { Empty } from '@/components/Empty';
import { ErrorState } from '@/components/ErrorState';
import { PageHeader } from '@/components/PageHeader';
import { useCan } from '@/hooks/useCan';
import { adminErrorMessage } from '@/lib/adminError';
import { FileThumb } from './FileThumb';
import { blobObjectUrl, isPdfPreview, openBlobTab, saveBlob } from './fileActions';
import { formatBytes as formatSize, formatDateTime as formatTime, toDateKey } from './format';
import { useDebouncedValue } from './useDebouncedValue';
import { useMediaQuery } from './useMediaQuery';

const FILES_QUERY_KEY = ['files'] as const;
const PAGE_SIZE = 20;
const MAX_FILE_BYTES = 100 * 1024 * 1024;
const VIEW_STORAGE_KEY = 'sand.data.files.view';

const KIND_OPTIONS = ['image', 'document', 'sheet', 'archive', 'other'] as const;
const USAGE_OPTIONS = ['used', 'unused', 'unknown'] as const;

type UploadStatus = 'uploading' | 'done' | 'failed';

interface UploadItem {
  key: string;
  name: string;
  status: UploadStatus;
  error?: string;
}

function usageText(
  file: FileObject,
  t: (key: string, options?: Record<string, unknown>) => string,
): { text: string; muted: boolean } {
  if (file.usage_state === 'used') {
    return { text: file.usage_label ?? t('table.usageUsed'), muted: false };
  }
  if (file.usage_state === 'unknown') {
    return { text: t('table.usageUnknown'), muted: true };
  }
  return { text: t('table.usageUnused'), muted: true };
}

/** 文件管理：列表/图片两种视图，上传、预览、下载、批量删除、一键清理。 */
export function FilesPage() {
  const { t } = useTranslation('files');
  const queryClient = useQueryClient();
  const [searchParams, setSearchParams] = useSearchParams();
  const canDelete = useCan('system:file:delete');
  const wideEnough = useMediaQuery('(min-width: 1280px)');

  const keywordParam = searchParams.get('kw') ?? '';
  const [keywordInput, setKeywordInput] = useState(keywordParam);
  const debouncedKeyword = useDebouncedValue(keywordInput);
  const kind = searchParams.get('kind') ?? '';
  const usage = searchParams.get('usage') ?? '';
  const dateFrom = searchParams.get('from') ?? '';
  const dateTo = searchParams.get('to') ?? '';
  const viewParam = searchParams.get('view');
  const view: 'list' | 'grid' =
    viewParam === 'grid' || viewParam === 'list'
      ? viewParam
      : localStorage.getItem(VIEW_STORAGE_KEY) === 'grid'
        ? 'grid'
        : 'list';
  const page = Math.max(1, Number(searchParams.get('page') ?? '1') || 1);
  const pageSize = Math.min(100, Math.max(1, Number(searchParams.get('size') ?? String(PAGE_SIZE)) || PAGE_SIZE));

  const [moreFilters, setMoreFilters] = useState(usage !== '');
  const [selectedIds, setSelectedIds] = useState<string[]>([]);
  const [uploads, setUploads] = useState<UploadItem[]>([]);
  const [dragActive, setDragActive] = useState(false);
  const [preview, setPreview] = useState<{ url: string; name: string } | null>(null);
  const [cleanupRunning, setCleanupRunning] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const dragDepth = useRef(0);

  // 输入防抖后写回 URL；关键词真正变化时回到第一页。
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

  const apiParams = useMemo(() => {
    const fromIso = dateFrom ? new Date(`${dateFrom}T00:00:00`).toISOString() : undefined;
    const toIso = dateTo ? new Date(`${dateTo}T23:59:59.999`).toISOString() : undefined;
    return {
      keyword: debouncedKeyword || undefined,
      kind: (kind || undefined) as FileObject['kind'] | undefined,
      usage_state: (usage || undefined) as FileObject['usage_state'] | undefined,
      create_time_from: fromIso,
      create_time_to: toIso,
      page,
      page_size: pageSize,
    };
  }, [debouncedKeyword, kind, usage, dateFrom, dateTo, page, pageSize]);

  const files = useQuery({
    queryKey: [...FILES_QUERY_KEY, 'list', apiParams],
    queryFn: () => listFiles(apiParams),
  });

  // 页头「已用」汇总：拉一页足够大的全量做求和（mock 与真实后端 page_size 上限一致）。
  const space = useQuery({
    queryKey: [...FILES_QUERY_KEY, 'space'],
    queryFn: () => listFiles({ page: 1, page_size: 100 }),
    staleTime: 30_000,
  });
  const usedBytes = useMemo(
    () => (space.data?.items ?? []).reduce((sum, f) => sum + f.size_bytes, 0),
    [space.data],
  );

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

  const setView = (next: 'list' | 'grid') => {
    localStorage.setItem(VIEW_STORAGE_KEY, next);
    patchParams({ view: next === 'list' ? null : next });
  };

  const invalidateFiles = () => {
    void queryClient.invalidateQueries({ queryKey: FILES_QUERY_KEY });
    setSelectedIds([]);
  };

  /* 上传 */

  const startUploads = (fileList: File[]) => {
    const accepted = fileList.filter((file) => {
      if (file.size > MAX_FILE_BYTES) {
        Toast.error(t('sizeLimit'));
        return false;
      }
      return true;
    });
    if (accepted.length === 0) return;

    const items = accepted.map((file, i) => ({
      key: `${Date.now()}-${i}-${file.name}`,
      name: file.name,
      status: 'uploading' as const,
    }));
    setUploads((prev) => [...items, ...prev]);

    void Promise.allSettled(
      accepted.map((file, i) =>
        uploadFile(file).then(
          () => {
            setUploads((prev) =>
              prev.map((u) => (u.key === items[i].key ? { ...u, status: 'done' as const } : u)),
            );
          },
          (error: unknown) => {
            setUploads((prev) =>
              prev.map((u) =>
                u.key === items[i].key
                  ? { ...u, status: 'failed' as const, error: adminErrorMessage(error, t('downloadFailed')) }
                  : u,
              ),
            );
          },
        ),
      ),
    ).then((results) => {
      const done = results.filter((r) => r.status === 'fulfilled').length;
      if (done > 0) {
        Toast.success(t('uploaded', { count: done }));
        invalidateFiles();
      }
    });
  };

  const onPickFiles = (list: FileList | null) => {
    if (!list || list.length === 0) return;
    startUploads(Array.from(list));
    if (fileInputRef.current) fileInputRef.current.value = '';
  };

  /* 下载与预览 */

  const onDownload = async (file: FileObject) => {
    try {
      const blob = (await downloadFile(file.id)).blob;
      saveBlob(blob, file.original_name);
    } catch (error) {
      Toast.error(adminErrorMessage(error, t('downloadFailed')));
    }
  };

  const onBatchDownload = async () => {
    const targets = (files.data?.items ?? []).filter((f) => selectedIds.includes(f.id));
    // 契约没有批量打包接口：逐个走授权下载，浏览器会分别保存。
    for (const file of targets) {
      await onDownload(file);
    }
  };

  const onPreview = async (file: FileObject) => {
    try {
      const blob = (await downloadFile(file.id)).blob;
      if (isPdfPreview({ fileId: file.id, name: file.original_name, mediaType: file.media_type, kind: file.kind })) {
        openBlobTab(blob);
        return;
      }
      if (file.kind === 'image') {
        setPreview({ url: blobObjectUrl(blob), name: file.original_name });
        return;
      }
      saveBlob(blob, file.original_name);
    } catch (error) {
      Toast.error(adminErrorMessage(error, t('downloadFailed')));
    }
  };

  /* 删除 */

  const deleteMutation = useMutation({
    mutationFn: (ids: string[]) => Promise.all(ids.map((id) => deleteFile(id))),
    onSuccess: (_data, ids) => {
      const freed = (files.data?.items ?? [])
        .filter((f) => ids.includes(f.id))
        .reduce((sum, f) => sum + f.size_bytes, 0);
      Toast.success(t('deleted', { count: ids.length, size: formatSize(freed) }));
      invalidateFiles();
    },
    onError: (error) => {
      Toast.error(adminErrorMessage(error, t('downloadFailed')));
      invalidateFiles();
    },
  });

  const confirmDelete = (targets: FileObject[], mode: 'all' | 'unusedOnly') => {
    const ids =
      mode === 'unusedOnly' ? targets.filter((f) => f.usage_state === 'unused').map((f) => f.id) : targets.map((f) => f.id);
    if (ids.length === 0) return;
    deleteMutation.mutate(ids);
  };

  const requestDelete = (targets: FileObject[]) => {
    if (targets.length === 0) return;
    const unknowns = targets.filter((f) => f.usage_state === 'unknown');
    // 「暂时看不出用在哪里」不能批量删，只能单个删。
    if (targets.length > 1 && unknowns.length > 0) {
      Modal.warning({
        title: t('batch.deleteBlockedTitle'),
        content: t('batch.deleteBlocked'),
        okText: t('think'),
      });
      return;
    }
    const used = targets.filter((f) => f.usage_state === 'used');
    const freed = targets.reduce((sum, f) => sum + f.size_bytes, 0);

    if (targets.length === 1) {
      const file = targets[0];
      if (file.usage_state === 'used') {
        Modal.confirm({
          title: t('confirmDeleteUsedSingleTitle', { name: file.original_name }),
          content: t('confirmDeleteUsedSingleContent', { usage: file.usage_label ?? t('table.usageUsed') }),
          okText: t('deleteAnyway'),
          cancelText: t('think'),
          okType: 'danger',
          onOk: () => deleteMutation.mutateAsync([file.id]).catch(() => undefined),
        });
        return;
      }
      if (file.usage_state === 'unknown') {
        Modal.confirm({
          title: t('confirmDeleteUnknownTitle', { name: file.original_name }),
          content: t('confirmDeleteUnknownContent'),
          okText: t('deleteAnyway'),
          cancelText: t('think'),
          okType: 'danger',
          onOk: () => deleteMutation.mutateAsync([file.id]).catch(() => undefined),
        });
        return;
      }
      Modal.confirm({
        title: t('confirmDeleteUnusedTitle', { count: 1 }),
        content: t('confirmDeleteUnusedContent', { size: formatSize(file.size_bytes) }),
        okText: t('deleteOk'),
        cancelText: t('think'),
        okType: 'danger',
        onOk: () => deleteMutation.mutateAsync([file.id]).catch(() => undefined),
      });
      return;
    }

    if (used.length > 0) {
      const labels = [...new Set(used.map((f) => f.usage_label ?? t('table.usageUsed')))].join('、');
      Modal.confirm({
        title: t('confirmDeleteUsedTitle', { count: used.length }),
        content: t('confirmDeleteUsedContent', { count: used.length, labels }),
        footer: (
          <div className="flex justify-end gap-2">
            <Button
              theme="solid"
              type="danger"
              onClick={() => {
                Modal.destroyAll();
                confirmDelete(targets, 'all');
              }}
            >
              {t('deleteAnyway')}
            </Button>
            <Button
              onClick={() => {
                Modal.destroyAll();
                confirmDelete(targets, 'unusedOnly');
              }}
            >
              {t('onlyUnused')}
            </Button>
          </div>
        ),
        closable: true,
      });
      return;
    }

    Modal.confirm({
      title: t('confirmDeleteUnusedTitle', { count: targets.length }),
      content: t('confirmDeleteUnusedContent', { size: formatSize(freed) }),
      okText: t('deleteOk'),
      cancelText: t('think'),
      okType: 'danger',
      onOk: () => deleteMutation.mutateAsync(targets.map((f) => f.id)).catch(() => undefined),
    });
  };

  /* 清理 */

  const runCleanup = async () => {
    setCleanupRunning(true);
    try {
      const accepted = await cleanupFiles();
      // 轮询任务直到结束；mock 立即成功，真实后端为异步任务。
      for (let i = 0; i < 40; i += 1) {
        await new Promise((resolve) => setTimeout(resolve, 800));
        const task = await getTask(accepted.task_id);
        if (task.state === 'succeeded') {
          const result = task.result ?? {};
          Toast.success(
            t('cleaned', {
              count: Number(result.deleted_count ?? 0),
              size: formatSize(Number(result.freed_bytes ?? 0)),
            }),
          );
          invalidateFiles();
          return;
        }
        if (task.state === 'failed' || task.state === 'cancelled') {
          throw new Error(task.error?.message ?? 'cleanup failed');
        }
      }
      throw new Error('cleanup timeout');
    } catch {
      Toast.error(t('cleanedFailed'));
    } finally {
      setCleanupRunning(false);
    }
  };

  const confirmCleanup = () => {
    Modal.confirm({
      title: t('confirmCleanupTitle'),
      content: t('confirmCleanupContent'),
      okText: t('cleanupOk'),
      cancelText: t('think'),
      okType: 'danger',
      onOk: () => runCleanup(),
    });
  };

  /* 渲染 */

  const items = files.data?.items ?? [];
  const total = files.data?.total ?? 0;
  const filtering = Boolean(debouncedKeyword || kind || usage || dateFrom || dateTo);
  const selectedFiles = items.filter((f) => selectedIds.includes(f.id));
  const selectedHasUnknown = selectedFiles.some((f) => f.usage_state === 'unknown');

  const columns: ColumnProps<FileObject>[] = [
    {
      title: t('table.file'),
      render: (_text, record) => (
        <div className="flex min-w-0 items-center gap-2">
          <FileThumb file={record} />
          <Typography.Text ellipsis={{ showTooltip: true }} className="min-w-0">
            {record.original_name}
          </Typography.Text>
        </div>
      ),
    },
    {
      title: t('table.size'),
      width: 90,
      render: (_text, record) => formatSize(record.size_bytes),
    },
    ...(wideEnough
      ? [
          {
            title: t('table.usage'),
            width: 180,
            render: (_text: unknown, record: FileObject) => {
              const usage = usageText(record, t);
              return (
                <Typography.Text type={usage.muted ? 'tertiary' : undefined} ellipsis={{ showTooltip: true }}>
                  {usage.text}
                </Typography.Text>
              );
            },
          } satisfies ColumnProps<FileObject>,
          {
            title: t('table.uploader'),
            width: 100,
            render: (_text: unknown, record: FileObject) => record.uploader_name ?? '—',
          } satisfies ColumnProps<FileObject>,
        ]
      : []),
    {
      title: t('table.createTime'),
      width: 150,
      render: (_text, record) => formatTime(record.create_time),
    },
    {
      title: t('table.actions'),
      width: 160,
      fixed: 'right',
      render: (_text, record) => (
        <div className="flex items-center gap-1">
          {(record.kind === 'image' || isPdfPreview({ fileId: record.id, name: record.original_name, mediaType: record.media_type, kind: record.kind })) ? (
            <Button theme="borderless" size="small" icon={<IconEyeOpened />} onClick={() => void onPreview(record)}>
              {t('table.preview')}
            </Button>
          ) : null}
          <Button theme="borderless" size="small" icon={<IconDownload />} onClick={() => void onDownload(record)}>
            {t('table.download')}
          </Button>
          {canDelete ? (
            <Dropdown
              trigger="click"
              position="bottomRight"
              render={
                <Dropdown.Menu>
                  <Dropdown.Item type="danger" onClick={() => requestDelete([record])}>
                    {t('table.delete')}
                  </Dropdown.Item>
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
    <div
      className="relative flex flex-col gap-4"
      onDragEnter={(e) => {
        e.preventDefault();
        dragDepth.current += 1;
        setDragActive(true);
      }}
      onDragOver={(e) => e.preventDefault()}
      onDragLeave={() => {
        dragDepth.current = Math.max(0, dragDepth.current - 1);
        if (dragDepth.current === 0) setDragActive(false);
      }}
      onDrop={(e) => {
        e.preventDefault();
        dragDepth.current = 0;
        setDragActive(false);
        if (e.dataTransfer.files.length > 0) onPickFiles(e.dataTransfer.files);
      }}
    >
      <PageHeader
        title={t('title')}
        subtitle={t('subtitle')}
        extra={
          <div className="flex items-center gap-3">
            <Typography.Text type="secondary">
              {t('usedSpace', { size: formatSize(usedBytes) })}
            </Typography.Text>
            {canDelete ? (
              <Button theme="borderless" size="small" loading={cleanupRunning} onClick={confirmCleanup}>
                {t('cleanup')}
              </Button>
            ) : null}
            <Button theme="solid" icon={<IconUpload />} onClick={() => fileInputRef.current?.click()}>
              {t('upload')}
            </Button>
            <input
              ref={fileInputRef}
              type="file"
              multiple
              className="hidden"
              onChange={(e) => onPickFiles(e.target.files)}
            />
          </div>
        }
      />

      {/* 筛选区：搜索、类型、上传时间常驻；使用情况收进「更多条件」。 */}
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
          placeholder={t('filters.kindAll')}
          value={kind || undefined}
          showClear
          className="w-32"
          onChange={(v) => patchParams({ kind: (v as string) || null })}
          optionList={KIND_OPTIONS.map((k) => ({
            value: k,
            label: t(`filters.kind${k[0].toUpperCase()}${k.slice(1)}`),
          }))}
        />
        <DatePicker
          type="dateRange"
          placeholder={[t('filters.timeFrom'), t('filters.timeTo')]}
          value={dateFrom && dateTo ? [dateFrom, dateTo] : undefined}
          showClear
          onChange={(_dates, dateString) => {
            const pair = Array.isArray(dateString) ? dateString : [];
            patchParams({ from: toDateKey(pair[0]), to: toDateKey(pair[1]) });
          }}
        />
        <Button
          theme={moreFilters || usage ? 'solid' : 'borderless'}
          type={moreFilters || usage ? 'primary' : 'tertiary'}
          onClick={() => setMoreFilters((v) => !v)}
        >
          {moreFilters ? t('filters.hideFilters') : t('filters.moreFilters')}
        </Button>
        {moreFilters ? (
          <Select
            placeholder={t('filters.usageAll')}
            value={usage || undefined}
            showClear
            className="w-44"
            onChange={(v) => {
              const next = (v as string) || '';
              if (!next) setMoreFilters(false);
              patchParams({ usage: next || null });
            }}
            optionList={USAGE_OPTIONS.map((u) => ({
              value: u,
              label: t(`filters.usage${u[0].toUpperCase()}${u.slice(1)}`),
            }))}
          />
        ) : null}
        {filtering ? (
          <Button
            theme="borderless"
            type="tertiary"
            onClick={() => {
              setKeywordInput('');
              setMoreFilters(false);
              patchParams({ kw: null, kind: null, usage: null, from: null, to: null });
            }}
          >
            {t('filters.clearFilters')}
          </Button>
        ) : null}
        <div className="ml-auto">
          <Radio.Group
            type="button"
            value={view}
            onChange={(e) => setView(e.target.value as 'list' | 'grid')}
          >
            <Radio value="list">{t('view.list')}</Radio>
            <Radio value="grid">{t('view.grid')}</Radio>
          </Radio.Group>
        </div>
      </div>

      {/* 批量操作条 */}
      {selectedIds.length > 0 ? (
        <div className="flex items-center gap-3 rounded-lg border border-[var(--semi-color-primary)] bg-[var(--semi-color-primary-light-default)] px-4 py-2">
          <Typography.Text>{t('batch.selected', { count: selectedIds.length })}</Typography.Text>
          <Button size="small" icon={<IconDownload />} onClick={() => void onBatchDownload()}>
            {t('batch.download')}
          </Button>
          {canDelete ? (
            <Button
              size="small"
              type="danger"
              theme="borderless"
              disabled={selectedHasUnknown}
              onClick={() => requestDelete(selectedFiles)}
            >
              {t('batch.delete')}
            </Button>
          ) : null}
          {selectedHasUnknown ? (
            <Typography.Text type="tertiary" size="small">
              {t('batch.deleteBlocked')}
            </Typography.Text>
          ) : null}
        </div>
      ) : null}

      {/* 上传队列 */}
      {uploads.length > 0 ? (
        <div className="flex flex-col gap-1 rounded-lg border border-[var(--semi-color-border)] p-3">
          {uploads.slice(0, 5).map((u) => (
            <div key={u.key} className="flex items-center gap-2 text-sm">
              <Typography.Text ellipsis={{ showTooltip: true }} className="min-w-0 flex-1">
                {u.name}
              </Typography.Text>
              {u.status === 'uploading' ? (
                <Tag color="blue" size="small">{t('uploadPanel.uploading')}</Tag>
              ) : u.status === 'done' ? (
                <Tag color="green" size="small">{t('uploadPanel.done')}</Tag>
              ) : (
                <Tag color="red" size="small">{t('uploadPanel.failed')}</Tag>
              )}
            </div>
          ))}
        </div>
      ) : null}

      {files.isPending ? (
        <div className="rounded-lg border border-[var(--semi-color-border)] p-4">
          <Skeleton placeholder={<Skeleton.Paragraph rows={8} />} loading />
        </div>
      ) : files.isError ? (
        <div className="rounded-lg border border-[var(--semi-color-border)] p-4">
          <ErrorState kind="error" error={files.error} onRetry={() => void files.refetch()} showHome={false} />
        </div>
      ) : view === 'list' ? (
        <Table<FileObject>
          size="default"
          columns={columns}
          dataSource={items}
          rowKey="id"
          pagination={false}
          rowSelection={
            canDelete
              ? {
                  selectedRowKeys: selectedIds,
                  onChange: (keys) => setSelectedIds((keys ?? []).map(String)),
                }
              : undefined
          }
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
                    setMoreFilters(false);
                    patchParams({ kw: null, kind: null, usage: null, from: null, to: null });
                  }}
                >
                  {t('filters.clearFilters')}
                </Button>
              ) : null}
            </Empty>
          }
        />
      ) : (
        <div className="grid grid-cols-2 gap-3 min-[1280px]:grid-cols-4 min-[1440px]:grid-cols-5 min-[1920px]:grid-cols-7">
          {items.length === 0 ? (
            <div className="col-span-full rounded-lg border border-[var(--semi-color-border)] p-10">
              <Empty
                title={filtering ? t('noResult') : t('empty.title')}
                description={filtering ? undefined : t('empty.desc')}
              />
            </div>
          ) : (
            items.map((file) => {
              const usage = usageText(file, t);
              const checked = selectedIds.includes(file.id);
              return (
                <div
                  key={file.id}
                  className={`relative flex flex-col gap-2 rounded-lg border p-3 ${
                    checked
                      ? 'border-[var(--semi-color-primary)] bg-[var(--semi-color-primary-light-default)]'
                      : 'border-[var(--semi-color-border)]'
                  }`}
                >
                  {canDelete ? (
                    <Checkbox
                      checked={checked}
                      onChange={(e) => {
                        setSelectedIds((prev) =>
                          e.target.checked ? [...prev, file.id] : prev.filter((id) => id !== file.id),
                        );
                      }}
                      className="absolute right-2 top-2 z-10"
                    />
                  ) : null}
                  <button
                    type="button"
                    className="flex items-center justify-center"
                    onClick={() => void onPreview(file)}
                  >
                    <FileThumb file={file} size={72} />
                  </button>
                  <Typography.Text strong ellipsis={{ showTooltip: true }}>
                    {file.original_name}
                  </Typography.Text>
                  <Typography.Text type="tertiary" size="small">
                    {formatSize(file.size_bytes)} · {formatTime(file.create_time)}
                  </Typography.Text>
                  <Typography.Text type={usage.muted ? 'tertiary' : 'secondary'} size="small" ellipsis={{ showTooltip: true }}>
                    {usage.text}
                  </Typography.Text>
                  <div className="flex items-center gap-1">
                    <Button theme="borderless" size="small" icon={<IconDownload />} onClick={() => void onDownload(file)}>
                      {t('table.download')}
                    </Button>
                    {canDelete ? (
                      <Dropdown
                        trigger="click"
                        position="bottomRight"
                        render={
                          <Dropdown.Menu>
                            <Dropdown.Item
                              type="danger"
                              onClick={() => requestDelete([file])}
                            >
                              {t('table.delete')}
                            </Dropdown.Item>
                          </Dropdown.Menu>
                        }
                      >
                        <Button theme="borderless" size="small" icon={<IconMore />} aria-label={t('table.more')} />
                      </Dropdown>
                    ) : null}
                  </div>
                </div>
              );
            })
          )}
        </div>
      )}

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

      {/* 拖拽上传蒙层 */}
      {dragActive ? (
        <div className="pointer-events-none absolute inset-0 z-50 flex items-center justify-center rounded-lg border-2 border-dashed border-[var(--semi-color-primary)] bg-[var(--semi-color-bg-0)]/80">
          <Typography.Title heading={4} className="text-[var(--semi-color-primary)]">
            {t('dropHint')}
          </Typography.Title>
        </div>
      ) : null}

      {/* 图片预览 */}
      {preview ? (
        <ImagePreview
          src={preview.url}
          visible
          showTooltip={false}
          onClose={() => {
            URL.revokeObjectURL(preview.url);
            setPreview(null);
          }}
        />
      ) : null}
    </div>
  );
}
