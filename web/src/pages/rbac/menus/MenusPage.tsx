import { useEffect, useMemo, useRef, useState, type DragEvent } from 'react';

import {
  Button,
  Dropdown,
  Input,
  Modal,
  Select,
  Switch,
  Table,
  Tag,
  Toast,
  Tooltip,
  Typography,
} from '@douyinfe/semi-ui';
import type { ColumnProps } from '@douyinfe/semi-ui/lib/es/table/interface';
import {
  IconArrowDown,
  IconArrowUp,
  IconDelete,
  IconEdit,
  IconHandle,
  IconLink,
  IconMore,
  IconPlus,
  IconRestore,
  IconSearch,
} from '@douyinfe/semi-icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import { MY_MENUS_QUERY_KEY } from '@/api/v1/me';
import {
  deleteMenu,
  getMenuTree,
  moveMenu,
  resetMenus,
  setMenuVisible,
  type MenuNode,
  type MenuSourceKind,
} from '@/api/v1/menus';
import { Empty } from '@/components/Empty';
import { ErrorState } from '@/components/ErrorState';
import { PageHeader } from '@/components/PageHeader';
import { useCan } from '@/hooks/useCan';
import { useUrlQuery, type UrlQueryFields } from '@/hooks/useUrlQuery';
import { isAdminApiError } from '@/lib/adminClient';
import { adminErrorMessage } from '@/lib/adminError';
import { menuIconNode } from '@/pages/rbac/lib/menuIcons';
import {
  dropZoneAt,
  findMenuRow,
  resolveDropMove,
  siblingMoveIndex,
  type DropZone,
} from '@/pages/rbac/lib/menuTree';
import { MenuFormSheet, type MenuFormMode } from './MenuFormSheet';
import { MenuPreview } from './MenuPreview';
import { confirmAction } from '@/lib/confirmAction';

const QUERY_FIELDS: UrlQueryFields = {
  keyword: {},
  source: {},
};

const UNDO_MS = 5000;

interface SheetState {
  mode: MenuFormMode;
  node: MenuNode | null;
}

interface UndoState {
  key: string;
  parent_key: string | null;
  index: number;
}

function isStale(error: unknown): boolean {
  return isAdminApiError(error) && error.code === 'STALE_VERSION';
}

function sourceLabel(
  node: MenuNode,
  t: (key: string) => string,
): string {
  const kind = node.source?.kind;
  if (kind === 'plugin') {
    return node.source?.plugin_label ?? t('menus.sourcePlugin');
  }
  if (kind === 'custom') {
    return t('menus.sourceCustom');
  }
  return t('menus.sourceSystem');
}

function typeLabel(node: MenuNode, t: (key: string) => string): string {
  if (node.type === 'group') return t('menus.typeGroup');
  if (node.type === 'link') return t('menus.typeLink');
  return t('menus.typePage');
}

/** Semi 传给 `onExpand` 的 record 可能是普通行，也可能是分组包装。 */
function recordKeyOf(record: unknown): string | undefined {
  if (
    record !== null &&
    typeof record === 'object' &&
    'key' in record &&
    typeof (record as { key: unknown }).key === 'string'
  ) {
    return (record as { key: string }).key;
  }
  return undefined;
}

/**
 * 菜单管理：可筛选的两级树，支持拖拽排序、行内可见性开关、
 * 侧滑编辑器和宽屏实时预览。
 */
export function MenusPage() {
  const { t } = useTranslation('rbac');
  const queryClient = useQueryClient();
  const { values, setValues } = useUrlQuery(QUERY_FIELDS);

  const canUpdate = useCan('system:menu:update');
  const canCreate = useCan('system:menu:create');
  const canDelete = useCan('system:menu:delete');
  const canReset = useCan('system:menu:reset');

  const keyword = typeof values.keyword === 'string' ? values.keyword : '';
  const source = typeof values.source === 'string' ? values.source : '';

  const [keywordInput, setKeywordInput] = useState(keyword);
  const [sheet, setSheet] = useState<SheetState | null>(null);
  const [moveNode, setMoveNode] = useState<MenuNode | null>(null);
  const [moveParent, setMoveParent] = useState('');
  const [dragKey, setDragKey] = useState<string | null>(null);
  const [dropTarget, setDropTarget] = useState<{ key: string; zone: DropZone } | null>(null);
  const [undo, setUndo] = useState<UndoState | null>(null);
  const [expandedKeys, setExpandedKeys] = useState<string[]>([]);
  const undoTimer = useRef<number | undefined>(undefined);
  // 撤销触发的移动不能重新武装撤销条。
  const suppressUndo = useRef(false);

  // URL 是 keyword 的唯一事实源；外部变化（后退、清空筛选）
  // 在渲染期同步进输入框。
  const [prevKeyword, setPrevKeyword] = useState(keyword);
  if (prevKeyword !== keyword) {
    setPrevKeyword(keyword);
    setKeywordInput(keyword);
  }

  useEffect(() => {
    const timer = window.setTimeout(() => {
      const next = keywordInput.trim();
      if (next !== keyword) {
        setValues({ keyword: next || undefined });
      }
    }, 300);
    return () => window.clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [keywordInput]);

  const tree = useQuery({
    queryKey: ['rbac', 'menu-tree', { keyword, source }],
    queryFn: () =>
      getMenuTree({
        keyword: keyword || undefined,
        source: (source || undefined) as MenuSourceKind | undefined,
      }),
  });

  const items = useMemo(() => tree.data?.items ?? [], [tree.data]);
  const menuVersion = tree.data?.menu_version ?? 0;

  const groupKeys = useMemo(
    () => items.filter((n) => n.type === 'group').map((n) => n.key),
    [items],
  );

  // 分组默认全部展开；搜索词非空时全部重新展开，
  // 让折叠分组里的命中也可见。渲染期调整。
  const [prevExpand, setPrevExpand] = useState({ groupKeys, keyword });
  if (
    prevExpand.groupKeys !== groupKeys ||
    (keyword !== '' && prevExpand.keyword === '')
  ) {
    setPrevExpand({ groupKeys, keyword });
    setExpandedKeys(groupKeys);
  } else if (prevExpand.keyword !== keyword || prevExpand.groupKeys !== groupKeys) {
    setPrevExpand({ groupKeys, keyword });
  }

  useEffect(
    () => () => {
      if (undoTimer.current !== undefined) {
        window.clearTimeout(undoTimer.current);
      }
    },
    [],
  );

  // 侧栏（含工作台快捷入口）吃 me/menus，写操作成功后必须一并失效，
  // 否则它要等 staleTime 或下次挂载才更新。
  const refreshTree = () => {
    void queryClient.invalidateQueries({ queryKey: ['rbac', 'menu-tree'] });
    void queryClient.invalidateQueries({ queryKey: MY_MENUS_QUERY_KEY });
  };

  const scheduleUndo = (state: UndoState | null) => {
    if (undoTimer.current !== undefined) {
      window.clearTimeout(undoTimer.current);
    }
    setUndo(state);
    if (state) {
      undoTimer.current = window.setTimeout(() => setUndo(null), UNDO_MS);
    }
  };

  const onWriteError = (error: unknown, fallback: string) => {
    if (isStale(error)) {
      Toast.warning(t('menus.toast.staleVersion'));
      refreshTree();
      return;
    }
    Toast.error(adminErrorMessage(error, fallback));
  };

  const moveMutation = useMutation({
    mutationFn: (args: {
      key: string;
      parent_key: string | null;
      index: number;
      undoTo: UndoState;
    }) =>
      moveMenu(args.key, {
        parent_key: args.parent_key,
        index: args.index,
        base_version: menuVersion,
      }),
    onSuccess: (_result, args) => {
      refreshTree();
      if (suppressUndo.current) {
        suppressUndo.current = false;
        Toast.success(t('menus.toast.undoDone'));
      } else {
        scheduleUndo(args.undoTo);
        Toast.info(t('menus.toast.orderAdjusted'));
      }
    },
    onError: (error) => onWriteError(error, t('menus.loadFailed')),
  });

  const requestMove = (key: string, parentKey: string | null, index: number) => {
    const row = findMenuRow(items, key);
    if (!row) return;
    moveMutation.mutate({
      key,
      parent_key: parentKey,
      index,
      undoTo: { key, parent_key: row.parentKey, index: row.index },
    });
  };

  const undoMove = () => {
    if (!undo) return;
    const row = findMenuRow(items, undo.key);
    suppressUndo.current = true;
    moveMutation.mutate({
      key: undo.key,
      parent_key: undo.parent_key,
      index: undo.index,
      undoTo: row
        ? { key: undo.key, parent_key: row.parentKey, index: row.index }
        : { key: undo.key, parent_key: null, index: 0 },
    });
    scheduleUndo(null);
  };

  const visibleMutation = useMutation({
    mutationFn: ({ node, next }: { node: MenuNode; next: boolean }) =>
      setMenuVisible(node.key, { visible: next, base_version: menuVersion }),
    onSuccess: (_result, { node, next }) => {
      refreshTree();
      Toast.success(
        next
          ? t('menus.toast.shown', { name: node.title })
          : t('menus.toast.hidden', { name: node.title }),
      );
    },
    onError: (error) => onWriteError(error, t('menus.loadFailed')),
  });

  const deleteMutation = useMutation({
    mutationFn: (node: MenuNode) => deleteMenu(node.key),
    onSuccess: (_result, node) => {
      refreshTree();
      Toast.success(t('menus.toast.deleted', { name: node.title }));
    },
    onError: (error) => onWriteError(error, t('menus.loadFailed')),
  });

  const resetMutation = useMutation({
    mutationFn: resetMenus,
    onSuccess: () => {
      refreshTree();
      Toast.success(t('menus.toast.resetDone'));
    },
    onError: (error) => onWriteError(error, t('menus.loadFailed')),
  });

  const confirmDelete = (node: MenuNode) => {
    confirmAction({
      title:
        node.type === 'group'
          ? t('menus.confirm.deleteGroupTitle', { name: node.title })
          : t('menus.confirm.deleteLinkTitle', { name: node.title }),
      content:
        node.type === 'group'
          ? (node.children?.length ?? 0) > 0
            ? t('menus.confirm.deleteGroupBody', {
                count: node.children?.length ?? 0,
              })
            : t('menus.confirm.deleteGroupEmptyBody')
          : t('menus.confirm.deleteLinkBody'),
      okText: t('menus.delete'),
      cancelText: t('menus.confirm.thinkAgain'),
      okButtonProps: { type: 'danger' },
      onOk: () => deleteMutation.mutate(node),
    });
  };

  const confirmReset = () => {
    confirmAction({
      title: t('menus.confirm.resetTitle'),
      content: t('menus.confirm.resetBody'),
      okText: t('menus.confirm.resetOk'),
      cancelText: t('menus.confirm.thinkAgain'),
      okButtonProps: { type: 'primary' },
      onOk: () => resetMutation.mutate(),
    });
  };

  const onDragStart = (key: string) => (e: DragEvent) => {
    setDragKey(key);
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', key);
  };

  const onRowDragOver = (node: MenuNode) => (e: DragEvent<HTMLTableRowElement>) => {
    if (!dragKey || dragKey === node.key) return;
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    const rect = e.currentTarget.getBoundingClientRect();
    setDropTarget({
      key: node.key,
      zone: dropZoneAt(e.clientY, rect.top, rect.height, node.type === 'group'),
    });
  };

  const onRowDrop = (node: MenuNode) => (e: DragEvent<HTMLTableRowElement>) => {
    e.preventDefault();
    const key = dragKey ?? e.dataTransfer.getData('text/plain');
    const zone = dropTarget?.key === node.key ? dropTarget.zone : 'after';
    setDragKey(null);
    setDropTarget(null);
    if (!key || key === node.key) return;
    const resolved = resolveDropMove({ items, dragKey: key, targetKey: node.key, zone });
    if (!resolved.ok) {
      if (resolved.reason === 'depth') {
        Toast.warning(t('menus.toast.depthLimit'));
      } else if (resolved.reason === 'top_locked') {
        Toast.warning(t('menus.toast.topLocked'));
      }
      return;
    }
    requestMove(key, resolved.parent_key, resolved.index);
  };

  const endDrag = () => {
    setDragKey(null);
    setDropTarget(null);
  };

  const kw = keyword.trim().toLowerCase();

  const columns = useMemo<ColumnProps<MenuNode>[]>(
    () => [
      {
        title: t('menus.colMenu'),
        dataIndex: 'title',
        width: 280,
        render: (_v, node) => {
          const renamed = Boolean(node.original_title);
          const isRecentlyAdded = Boolean(
            node.new_until && new Date(node.new_until).getTime() > Date.now(),
          );
          const titleHit = kw && node.title.toLowerCase().includes(kw);
          return (
            // 必须 inline：块级元素会把树形展开箭头挤到上一行。
            <div className="inline-flex items-center gap-2">
              {canUpdate && !node.locked?.move ? (
                <Tooltip content={t('menus.dragTooltip')}>
                  <span
                    draggable
                    onDragStart={onDragStart(node.key)}
                    onDragEnd={endDrag}
                    className="inline-flex cursor-grab items-center text-[var(--semi-color-text-2)] hover:text-[var(--semi-color-text-0)]"
                  >
                    <IconHandle />
                  </span>
                </Tooltip>
              ) : null}
              <span className="text-[var(--semi-color-text-2)]">
                {menuIconNode(node.icon)}
              </span>
              <div className="flex flex-col">
                <div className="flex items-center gap-1">
                  <Typography.Text
                    strong={node.type === 'group'}
                    className={titleHit ? 'rounded bg-[var(--semi-color-primary-light-default)] px-0.5' : undefined}
                  >
                    {node.title}
                  </Typography.Text>
                  {isRecentlyAdded ? (
                    <Tag size="small" color="blue">
                      {t('menus.newTag')}
                    </Tag>
                  ) : null}
                  {node.rename_suggested ? (
                    <Tooltip content={t('menus.renameTagTooltip')}>
                      <Tag size="small" color="orange">
                        {t('menus.renameTag')}
                      </Tag>
                    </Tooltip>
                  ) : null}
                </div>
                {renamed ? (
                  <Typography.Text type="secondary" size="small">
                    {t('menus.originalNamePrefix')}
                    {node.original_title}
                  </Typography.Text>
                ) : null}
              </div>
            </div>
          );
        },
      },
      {
        title: t('menus.colType'),
        dataIndex: 'type',
        width: 100,
        render: (_v, node) => <Tag size="small">{typeLabel(node, t)}</Tag>,
      },
      {
        title: t('menus.colSource'),
        dataIndex: 'source',
        width: 120,
        render: (_v, node) => (
          <Typography.Text size="small">{sourceLabel(node, t)}</Typography.Text>
        ),
      },
      {
        title: t('menus.colVisible'),
        dataIndex: 'visible',
        width: 90,
        render: (_v, node) => {
          const lockedHide = Boolean(node.locked?.hide);
          const disabled = !canUpdate || lockedHide;
          const switchEl = (
            <Switch
              size="small"
              checked={node.visible}
              disabled={disabled}
              onChange={(checked) =>
                visibleMutation.mutate({ node, next: checked })
              }
              aria-label={node.title}
            />
          );
          if (!canUpdate) {
            return (
              <Tooltip content={t('menus.noAccessTooltip')}>
                <span>{switchEl}</span>
              </Tooltip>
            );
          }
          if (lockedHide) {
            return (
              <Tooltip content={t('menus.lockedHideTooltip')}>
                <span>{switchEl}</span>
              </Tooltip>
            );
          }
          return switchEl;
        },
      },
      {
        title: t('menus.colActions'),
        fixed: 'right',
        width: 150,
        render: (_v, node) => {
          const row = findMenuRow(items, node.key);
          const isCustom = node.source?.kind === 'custom';
          return (
            <div className="flex items-center gap-1">
              {canUpdate ? (
                <Button
                  size="small"
                  theme="borderless"
                  icon={<IconEdit />}
                  onClick={() => setSheet({ mode: 'edit', node })}
                >
                  {t('menus.edit')}
                </Button>
              ) : null}
              <Dropdown
                trigger="click"
                position="bottomRight"
                render={
                  <Dropdown.Menu>
                    {canUpdate && node.type !== 'group' ? (
                      <Dropdown.Item
                        onClick={() => {
                          setMoveNode(node);
                          setMoveParent('');
                        }}
                      >
                        {t('menus.moveToGroup')}
                      </Dropdown.Item>
                    ) : null}
                    {canUpdate && row && !node.locked?.move ? (
                      <>
                        <Dropdown.Item
                          icon={<IconArrowUp />}
                          disabled={!siblingMoveIndex(items, row, -1)}
                          onClick={() => {
                            const target = siblingMoveIndex(items, row, -1);
                            if (target) requestMove(node.key, target.parent_key, target.index);
                          }}
                        >
                          {t('menus.moveUp')}
                        </Dropdown.Item>
                        <Dropdown.Item
                          icon={<IconArrowDown />}
                          disabled={!siblingMoveIndex(items, row, 1)}
                          onClick={() => {
                            const target = siblingMoveIndex(items, row, 1);
                            if (target) requestMove(node.key, target.parent_key, target.index);
                          }}
                        >
                          {t('menus.moveDown')}
                        </Dropdown.Item>
                      </>
                    ) : null}
                    {canDelete && isCustom ? (
                      <Dropdown.Item
                        icon={<IconDelete />}
                        type="danger"
                        onClick={() => confirmDelete(node)}
                      >
                        {t('menus.delete')}
                      </Dropdown.Item>
                    ) : null}
                  </Dropdown.Menu>
                }
              >
                <Button
                  size="small"
                  theme="borderless"
                  icon={<IconMore />}
                  aria-label={t('menus.more')}
                />
              </Dropdown>
            </div>
          );
        },
      },
    ],
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [t, items, keyword, canUpdate, canCreate, canDelete, menuVersion],
  );

  const hasFilters = Boolean(keyword || source);

  if (tree.isError || tree.isPaused) {
    const forbidden = isAdminApiError(tree.error) && tree.error.httpStatus === 403;
    return (
      <div className="flex flex-col gap-4">
        <PageHeader title={t('menus.title')} subtitle={t('menus.subtitle')} />
        {forbidden ? (
          <ErrorState kind="403" showHome={false} />
        ) : (
          <ErrorState
            kind="error"
            error={tree.error}
            paused={tree.isPaused}
            onRetry={() => void tree.refetch()}
            showHome={false}
          />
        )}
      </div>
    );
  }

  return (
    <div className="flex gap-4">
      <div className="flex min-w-0 flex-1 flex-col gap-4">
        <PageHeader
          title={t('menus.title')}
          subtitle={t('menus.subtitle')}
          extra={
            <div className="flex items-center gap-2">
              {canCreate ? (
                <Button
                  theme="light"
                  icon={<IconLink />}
                  onClick={() => setSheet({ mode: 'link', node: null })}
                >
                  {t('menus.addLink')}
                </Button>
              ) : null}
              {canCreate ? (
                <Button
                  theme="solid"
                  icon={<IconPlus />}
                  onClick={() => setSheet({ mode: 'group', node: null })}
                >
                  {t('menus.addGroup')}
                </Button>
              ) : null}
              {canReset ? (
                <Button
                  theme="borderless"
                  icon={<IconRestore />}
                  onClick={confirmReset}
                >
                  {t('menus.reset')}
                </Button>
              ) : null}
            </div>
          }
        />

        <div className="flex flex-wrap items-center gap-3">
          <Input
            prefix={<IconSearch />}
            placeholder={t('menus.searchPlaceholder')}
            value={keywordInput}
            onChange={setKeywordInput}
            showClear
            style={{ width: 240 }}
          />
          <Select
            placeholder={t('menus.sourceAll')}
            value={source || undefined}
            onChange={(v) => setValues({ source: v ? String(v) : undefined })}
            showClear
            style={{ width: 160 }}
          >
            <Select.Option value="system">{t('menus.sourceSystem')}</Select.Option>
            <Select.Option value="plugin">{t('menus.sourcePlugin')}</Select.Option>
            <Select.Option value="custom">{t('menus.sourceCustom')}</Select.Option>
          </Select>
        </div>

        <Table<MenuNode>
          columns={columns}
          dataSource={items}
          rowKey="key"
          loading={tree.isPending || tree.isFetching}
          pagination={false}
          childrenRecordName="children"
          indentSize={24}
          expandedRowKeys={expandedKeys}
          onExpand={(expanded, record) => {
            const key = recordKeyOf(record);
            if (!key) return;
            setExpandedKeys((prev) =>
              expanded ? Array.from(new Set([...prev, key])) : prev.filter((k) => k !== key),
            );
          }}
          onRow={(node) =>
            node
              ? {
                  onDragOver: onRowDragOver(node),
                  onDrop: onRowDrop(node),
                  onDragLeave: () => {
                    if (dropTarget?.key === node.key) setDropTarget(null);
                  },
                  className:
                    dropTarget?.key === node.key
                      ? dropTarget.zone === 'into'
                        ? 'outline outline-2 outline-[var(--semi-color-primary)] outline-offset-[-2px]'
                        : dropTarget.zone === 'before'
                          ? 'shadow-[inset_0_2px_0_0_var(--semi-color-primary)]'
                          : 'shadow-[inset_0_-2px_0_0_var(--semi-color-primary)]'
                      : dragKey === node.key
                        ? 'opacity-50'
                        : undefined,
                }
              : {}
          }
          empty={
            hasFilters ? (
              <Empty title={t('menus.emptyFiltered')}>
                <Button
                  onClick={() =>
                    setValues({ keyword: undefined, source: undefined })
                  }
                >
                  {t('menus.clearFilters')}
                </Button>
              </Empty>
            ) : (
              <Empty />
            )
          }
        />
      </div>

      <MenuPreview items={items} />

      {undo ? (
        <div className="fixed bottom-6 left-1/2 z-50 flex -translate-x-1/2 items-center gap-3 rounded-full border border-[var(--semi-color-border)] bg-[var(--semi-color-bg-1)] px-4 py-2 shadow-lg">
          <Typography.Text size="small">{t('menus.toast.orderAdjusted')}</Typography.Text>
          <Button size="small" theme="borderless" onClick={undoMove}>
            {t('menus.toast.undo')}
          </Button>
        </div>
      ) : null}

      {sheet !== null ? (
        <MenuFormSheet
          mode={sheet.mode}
          node={sheet.node}
          items={items}
          onClose={() => setSheet(null)}
          onSaved={refreshTree}
        />
      ) : null}

      <Modal
        visible={moveNode !== null}
        title={t('menus.moveModal.title')}
        okText={t('menus.moveModal.ok')}
        okButtonProps={{ disabled: !moveParent }}
        cancelText={t('common:actions.cancel')}
        onCancel={() => setMoveNode(null)}
        onOk={() => {
          if (!moveNode || !moveParent) return;
          const target = items.find((n) => n.key === moveParent);
          const index = target?.children?.length ?? 0;
          requestMove(moveNode.key, moveParent, index);
          setMoveNode(null);
        }}
      >
        <Select
          className="w-full"
          placeholder={t('menus.form.parentPlaceholder')}
          value={moveParent || undefined}
          onChange={(v) => setMoveParent(String(v ?? ''))}
        >
          {items
            .filter((n) => n.type === 'group' && n.key !== moveNode?.key)
            .map((group) => (
              <Select.Option key={group.key} value={group.key}>
                {group.title}
              </Select.Option>
            ))}
        </Select>
      </Modal>
    </div>
  );
}
