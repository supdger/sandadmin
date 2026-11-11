import { useEffect, useMemo, useState } from 'react';

import {
  Button,
  Dropdown,
  Input,
  Modal,
  Popconfirm,
  Skeleton,
  Table,
  Tag,
  Toast,
  Typography,
} from '@douyinfe/semi-ui';
import type { ColumnProps } from '@douyinfe/semi-ui/lib/es/table/interface';
import { IconHandle, IconMore, IconPlus, IconSearch } from '@douyinfe/semi-icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { useSearchParams } from 'react-router';

import {
  deleteDictItem,
  deleteDictType,
  getDictType,
  listDictTypes,
  sortDictItems,
  updateDictItemStatus,
  type DictItem,
  type DictType,
} from '@/api/dict';
import { Empty } from '@/components/Empty';
import { ErrorState } from '@/components/ErrorState';
import { PageHeader } from '@/components/PageHeader';
import { useCan } from '@/hooks/useCan';
import { apiErrorMessage } from '@/lib/apiClient';
import { DictItemModal } from './DictItemModal';
import { DictTypeModal } from './DictTypeModal';
import { useDebouncedValue } from './useDebouncedValue';

const DICT_QUERY_KEY = ['dict'] as const;

interface ItemSortPayload {
  typeId: string;
  itemIds: string[];
}

/** 选项管理：左栏选项组，右栏该组的选项表格（可拖动排序）。 */
export function DictPage() {
  const { t } = useTranslation('dict');
  const queryClient = useQueryClient();
  const [searchParams, setSearchParams] = useSearchParams();

  const canCreate = useCan('system:dict:create');
  const canUpdate = useCan('system:dict:update');
  const canDelete = useCan('system:dict:delete');

  const keywordParam = searchParams.get('kw') ?? '';
  const [keywordInput, setKeywordInput] = useState(keywordParam);
  const debouncedKeyword = useDebouncedValue(keywordInput);
  const selectedTypeId = searchParams.get('type');

  // 输入防抖后写回 URL，刷新/分享后筛选仍在。
  useEffect(() => {
    setSearchParams(
      (prev) => {
        const next = new URLSearchParams(prev);
        if (debouncedKeyword) next.set('kw', debouncedKeyword);
        else next.delete('kw');
        return next;
      },
      { replace: true },
    );
  }, [debouncedKeyword, setSearchParams]);

  const types = useQuery({
    queryKey: [...DICT_QUERY_KEY, 'types', debouncedKeyword],
    queryFn: () => listDictTypes(debouncedKeyword || undefined),
  });

  const detail = useQuery({
    queryKey: [...DICT_QUERY_KEY, 'type', selectedTypeId],
    queryFn: () => getDictType(selectedTypeId as string),
    enabled: Boolean(selectedTypeId),
  });

  const [typeModal, setTypeModal] = useState<{ open: boolean; type: DictType | null }>({
    open: false,
    type: null,
  });
  const [itemModal, setItemModal] = useState<{ open: boolean; item: DictItem | null }>({
    open: false,
    item: null,
  });
  const [draggingId, setDraggingId] = useState<string | null>(null);
  // used===true 或 null 的删除走自定义弹窗：「改为停用」是主按钮，「仍然删除」是文字按钮。
  const [pendingUsedDelete, setPendingUsedDelete] = useState<DictItem | null>(null);

  const invalidateAll = () =>
    queryClient.invalidateQueries({ queryKey: DICT_QUERY_KEY });

  const sortItems = useMutation({
    mutationFn: ({ typeId, itemIds }: ItemSortPayload) => sortDictItems(typeId, itemIds),
    onMutate: async ({ typeId, itemIds }) => {
      await queryClient.cancelQueries({ queryKey: [...DICT_QUERY_KEY, 'type', typeId] });
      const previous = queryClient.getQueryData([...DICT_QUERY_KEY, 'type', typeId]);
      queryClient.setQueryData([...DICT_QUERY_KEY, 'type', typeId], (old: unknown) => {
        if (!old || typeof old !== 'object' || !('items' in old)) return old;
        const detail = old as { items: DictItem[] };
        const byId = new Map(detail.items.map((i) => [i.id, i]));
        const ordered = itemIds
          .map((id) => byId.get(id))
          .filter((i): i is DictItem => Boolean(i));
        // 兼容接口返回的其他字段，保持其余属性不变。
        return { ...detail, items: ordered } as typeof detail;
      });
      return { previous };
    },
    onSuccess: () => {
      Toast.success(t('confirm.sorted'));
      void invalidateAll();
    },
    onError: (error, _vars, context) => {
      if (context?.previous !== undefined) {
        queryClient.setQueryData(
          [...DICT_QUERY_KEY, 'type', _vars.typeId],
          context.previous,
        );
      }
      Toast.error(apiErrorMessage(error, t('itemModal.saveFailed')));
      void invalidateAll();
    },
  });

  const statusItem = useMutation({
    mutationFn: ({ itemId, status }: { itemId: string; status: 'enabled' | 'disabled' }) =>
      updateDictItemStatus(itemId, status),
    onSuccess: async (_data, vars) => {
      Toast.success(
        t(vars.status === 'disabled' ? 'confirm.disabled' : 'confirm.enabled', {
          name: detail.data?.items.find((i) => i.id === vars.itemId)?.label ?? '',
        }),
      );
      await invalidateAll();
    },
    onError: (error) => {
      Toast.error(apiErrorMessage(error, t('itemModal.saveFailed')));
      void invalidateAll();
    },
  });

  const deleteItem = useMutation({
    mutationFn: (item: DictItem) => deleteDictItem(item.id),
    onSuccess: async (_data, item) => {
      Toast.success(t('confirm.deleted', { name: item.label }));
      await invalidateAll();
    },
    onError: (error) => {
      Toast.error(apiErrorMessage(error, t('itemModal.saveFailed')));
      void invalidateAll();
    },
  });

  const deleteType = useMutation({
    mutationFn: (type: DictType) => deleteDictType(type.id),
    onSuccess: async (_data, type) => {
      Toast.success(t('confirm.deleted', { name: type.name }));
      setSearchParams((prev) => {
        const next = new URLSearchParams(prev);
        if (next.get('type') === type.id) next.delete('type');
        return next;
      });
      await invalidateAll();
    },
    onError: (error) => {
      Toast.error(apiErrorMessage(error, t('typeModal.saveFailed')));
      void invalidateAll();
    },
  });

  const items = useMemo(() => detail.data?.items ?? [], [detail.data]);
  const currentType = detail.data ?? null;

  const selectType = (id: string) => {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev);
      next.set('type', id);
      return next;
    });
  };

  const handleDrop = (e: React.DragEvent, target: DictItem) => {
    e.preventDefault();
    const draggedId = e.dataTransfer.getData('text/plain') || draggingId;
    setDraggingId(null);
    if (!draggedId || !selectedTypeId || draggedId === target.id) return;
    const ids = items.map((i) => i.id);
    const from = ids.indexOf(draggedId);
    const to = ids.indexOf(target.id);
    if (from < 0 || to < 0 || from === to) return;
    const next = [...ids];
    next.splice(from, 1);
    next.splice(to, 0, draggedId);
    if (next.every((id, idx) => id === ids[idx])) return;
    sortItems.mutate({ typeId: selectedTypeId, itemIds: next });
  };

  const requestDeleteItem = (item: DictItem) => {
    // used === true 或 null（查不了）都按「可能已被使用」提醒；is_system 不给删除入口。
    if (item.is_system) return;
    if (item.used === false) {
      Modal.confirm({
        title: t('confirm.deleteItemTitle', { label: item.label }),
        content: t('confirm.deleteItemContent'),
        okText: t('confirm.deleteOk'),
        cancelText: t('confirm.think'),
        okType: 'danger',
        onOk: () => deleteItem.mutateAsync(item).catch(() => undefined),
      });
      return;
    }
    // 已使用或查不清：主按钮是「改为停用」，文字按钮是「仍然删除」。
    setPendingUsedDelete(item);
  };

  const requestDeleteType = (type: DictType) => {
    Modal.confirm({
      title: t('confirm.deleteTypeTitle', { name: type.name }),
      content: t('confirm.deleteTypeContent', { count: type.item_count }),
      okText: t('confirm.deleteOk'),
      cancelText: t('confirm.think'),
      okType: 'danger',
      onOk: () => deleteType.mutateAsync(type).catch(() => undefined),
    });
  };

  const columns: ColumnProps<DictItem>[] = [
    {
      title: '',
      width: 48,
      render: (_text, record) =>
        canUpdate ? (
          <span
            draggable
            role="button"
            aria-label={t('table.dragHandle')}
            className="inline-flex cursor-grab items-center text-[var(--semi-color-text-2)] hover:text-[var(--semi-color-text-0)]"
            onDragStart={(e) => {
              e.dataTransfer.setData('text/plain', record.id);
              e.dataTransfer.effectAllowed = 'move';
              setDraggingId(record.id);
            }}
            onDragEnd={() => setDraggingId(null)}
          >
            <IconHandle />
          </span>
        ) : null,
    },
    {
      title: t('table.name'),
      render: (_text, record) =>
        record.color ? (
          <Tag
            size="small"
            style={{
              color: record.color,
              borderColor: record.color,
              backgroundColor: `${record.color}14`,
            }}
          >
            {record.label}
          </Tag>
        ) : (
          <span>{record.label}</span>
        ),
    },
    {
      title: t('table.color'),
      width: 90,
      render: (_text, record) =>
        record.color ? (
          <span
            className="inline-block h-4 w-4 rounded"
            style={{ background: record.color }}
          />
        ) : (
          <Typography.Text type="tertiary">{t('table.colorDefault')}</Typography.Text>
        ),
    },
    {
      title: t('table.isDefault'),
      width: 90,
      render: (_text, record) => (record.is_default ? t('table.yes') : null),
    },
    {
      title: t('table.status'),
      width: 90,
      render: (_text, record) =>
        record.status === 'enabled' ? (
          <Tag color="green" size="small">
            {t('table.enabled')}
          </Tag>
        ) : (
          <Tag color="grey" size="small">
            {t('table.disabled')}
          </Tag>
        ),
    },
    {
      title: t('table.actions'),
      width: 140,
      fixed: 'right',
      render: (_text, record) => (
        <div className="flex items-center gap-1">
          {canUpdate ? (
            <Button
              theme="borderless"
              size="small"
              onClick={() => setItemModal({ open: true, item: record })}
            >
              {t('table.edit')}
            </Button>
          ) : null}
          {canUpdate || canDelete ? (
            <Dropdown
              trigger="click"
              position="bottomRight"
              render={
                <Dropdown.Menu>
                  {canUpdate ? (
                    record.status === 'enabled' ? (
                      <Popconfirm
                        title={t('confirm.disableTitle', { label: record.label })}
                        content={t('confirm.disableContent')}
                        okText={t('confirm.disableOk')}
                        cancelText={t('confirm.think')}
                        onConfirm={() =>
                          statusItem.mutate({ itemId: record.id, status: 'disabled' })
                        }
                      >
                        <Dropdown.Item>{t('table.disable')}</Dropdown.Item>
                      </Popconfirm>
                    ) : (
                      <Dropdown.Item
                        onClick={() =>
                          statusItem.mutate({ itemId: record.id, status: 'enabled' })
                        }
                      >
                        {t('table.enable')}
                      </Dropdown.Item>
                    )
                  ) : null}
                  {canDelete && !record.is_system ? (
                    <Dropdown.Item
                      type="danger"
                      onClick={() => requestDeleteItem(record)}
                    >
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

  const groups = types.data?.items ?? [];
  const searching = debouncedKeyword.length > 0;

  return (
    <div className="flex flex-col gap-4">
      <PageHeader title={t('title')} subtitle={t('subtitle')} />

      <div className="flex gap-4">
        {/* 左栏：选项组 */}
        <div className="flex w-[240px] shrink-0 flex-col gap-2 min-[1440px]:w-[260px] min-[1920px]:w-[280px]">
          <Input
            prefix={<IconSearch />}
            placeholder={t('group.searchPlaceholder')}
            value={keywordInput}
            onChange={setKeywordInput}
            showClear
          />
          <div className="flex max-h-[560px] flex-col gap-1 overflow-y-auto rounded-lg border border-[var(--semi-color-border)] p-1">
            {types.isPending ? (
              <div className="flex flex-col gap-2 p-2">
                <Skeleton placeholder={<Skeleton.Paragraph rows={6} />} loading />
              </div>
            ) : types.isError ? (
              <ErrorState
                kind="error"
                error={types.error}
                onRetry={() => void types.refetch()}
                showHome={false}
              />
            ) : groups.length === 0 ? (
              <div className="p-3">
                <Empty
                  title={searching ? t('group.noResult') : t('group.emptyTitle')}
                  description={searching ? undefined : t('group.emptyDesc')}
                >
                  {searching ? (
                    <Button
                      size="small"
                      onClick={() => {
                        setKeywordInput('');
                        setSearchParams((prev) => {
                          const next = new URLSearchParams(prev);
                          next.delete('kw');
                          return next;
                        });
                      }}
                    >
                      {t('group.clearSearch')}
                    </Button>
                  ) : canCreate ? (
                    <Button
                      size="small"
                      theme="solid"
                      onClick={() => setTypeModal({ open: true, type: null })}
                    >
                      {t('group.add')}
                    </Button>
                  ) : null}
                </Empty>
              </div>
            ) : (
              groups.map((type) => (
                <button
                  key={type.id}
                  type="button"
                  onClick={() => selectType(type.id)}
                  className={`flex w-full items-center justify-between gap-2 rounded-md px-3 py-2 text-left text-sm transition-colors ${
                    selectedTypeId === type.id
                      ? 'bg-[var(--semi-color-primary-light-default)] text-[var(--semi-color-primary)]'
                      : 'hover:bg-[var(--semi-color-fill-0)]'
                  }`}
                >
                  <span className="flex min-w-0 items-center gap-1.5">
                    <span className="truncate">{type.name}</span>
                    {type.is_system ? (
                      <Tag size="small" color="grey">
                        {t('group.systemTag')}
                      </Tag>
                    ) : null}
                  </span>
                  <span className="shrink-0 text-xs text-[var(--semi-color-text-2)]">
                    {t('group.itemCount', { count: type.item_count })}
                  </span>
                </button>
              ))
            )}
          </div>
          {canCreate ? (
            <Button
              icon={<IconPlus />}
              onClick={() => setTypeModal({ open: true, type: null })}
            >
              {t('group.add')}
            </Button>
          ) : null}
        </div>

        {/* 右栏：选项 */}
        <div className="min-w-0 flex-1">
          {!selectedTypeId ? (
            <div className="rounded-lg border border-[var(--semi-color-border)] p-10">
              <Empty title={t('detail.noSelection')} />
            </div>
          ) : detail.isPending ? (
            <div className="rounded-lg border border-[var(--semi-color-border)] p-4">
              <Skeleton placeholder={<Skeleton.Paragraph rows={8} />} loading />
            </div>
          ) : detail.isError ? (
            <div className="rounded-lg border border-[var(--semi-color-border)] p-4">
              <ErrorState
                kind="error"
                error={detail.error}
                onRetry={() => void detail.refetch()}
                showHome={false}
              />
            </div>
          ) : currentType ? (
            <div className="flex flex-col gap-3">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                  <div className="flex items-center gap-2">
                    <Typography.Title heading={5} style={{ margin: 0 }}>
                      {currentType.name}
                    </Typography.Title>
                    {currentType.is_system ? (
                      <Tag size="small" color="grey">
                        {t('group.systemTag')}
                      </Tag>
                    ) : null}
                  </div>
                  {currentType.remark ? (
                    <Typography.Text type="secondary" size="small">
                      {t('detail.remark')}：{currentType.remark}
                    </Typography.Text>
                  ) : null}
                </div>
                <div className="flex items-center gap-2">
                  {canUpdate ? (
                    <Button
                      size="small"
                      onClick={() => setTypeModal({ open: true, type: currentType })}
                    >
                      {t('detail.editType')}
                    </Button>
                  ) : null}
                  {canCreate ? (
                    <Button
                      theme="solid"
                      size="small"
                      onClick={() => setItemModal({ open: true, item: null })}
                    >
                      {t('detail.addItem')}
                    </Button>
                  ) : null}
                  {canDelete && !currentType.is_system ? (
                    <Dropdown
                      trigger="click"
                      position="bottomRight"
                      render={
                        <Dropdown.Menu>
                          <Dropdown.Item
                            type="danger"
                            onClick={() => requestDeleteType(currentType)}
                          >
                            {t('detail.deleteType')}
                          </Dropdown.Item>
                        </Dropdown.Menu>
                      }
                    >
                      <Button size="small" icon={<IconMore />} aria-label={t('detail.more')} />
                    </Dropdown>
                  ) : null}
                </div>
              </div>

              <Table<DictItem>
                size="default"
                columns={columns}
                dataSource={items}
                rowKey="id"
                pagination={false}
                empty={
                  <Empty title={t('detail.itemEmpty')}>
                    {canCreate ? (
                      <Button
                        size="small"
                        theme="solid"
                        onClick={() => setItemModal({ open: true, item: null })}
                      >
                        {t('detail.addItem')}
                      </Button>
                    ) : null}
                  </Empty>
                }
                onRow={(record) =>
                  record
                    ? {
                        onDragOver: canUpdate ? (e) => e.preventDefault() : undefined,
                        onDrop: canUpdate ? (e) => handleDrop(e, record) : undefined,
                        style: draggingId ? { opacity: 0.6 } : undefined,
                      }
                    : {}
                }
              />
            </div>
          ) : null}
        </div>
      </div>

      <DictTypeModal
        visible={typeModal.open}
        type={typeModal.type}
        onClose={() => setTypeModal({ open: false, type: null })}
        onSaved={(saved) => {
          if (!typeModal.type) selectType(saved.id);
        }}
      />
      <DictItemModal
        visible={itemModal.open}
        typeId={selectedTypeId ?? ''}
        items={items}
        item={itemModal.item}
        onClose={() => setItemModal({ open: false, item: null })}
        onSaved={() => undefined}
      />

      <Modal
        visible={pendingUsedDelete !== null}
        title={t('confirm.deleteUsedTitle', { label: pendingUsedDelete?.label ?? '' })}
        onCancel={() => setPendingUsedDelete(null)}
        footer={
          <div className="flex justify-end gap-2">
            <Button
              theme="solid"
              type="danger"
              onClick={() => {
                const item = pendingUsedDelete;
                setPendingUsedDelete(null);
                if (item) void statusItem.mutateAsync({ itemId: item.id, status: 'disabled' }).catch(() => undefined);
              }}
            >
              {t('confirm.disableInstead')}
            </Button>
            <Button
              type="tertiary"
              theme="borderless"
              className="text-[var(--semi-color-danger)]"
              onClick={() => {
                const item = pendingUsedDelete;
                setPendingUsedDelete(null);
                if (item) void deleteItem.mutateAsync(item).catch(() => undefined);
              }}
            >
              {t('confirm.deleteAnyway')}
            </Button>
            <Button onClick={() => setPendingUsedDelete(null)}>{t('confirm.think')}</Button>
          </div>
        }
      >
        <Typography.Paragraph>{t('confirm.deleteUsedContent')}</Typography.Paragraph>
      </Modal>
    </div>
  );
}
