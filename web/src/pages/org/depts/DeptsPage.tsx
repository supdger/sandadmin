import { useEffect, useMemo, useRef, useState } from 'react';

import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import {
  Avatar,
  Button,
  Dropdown,
  Input,
  Modal,
  Space,
  Table,
  Toast,
  Typography,
} from '@douyinfe/semi-ui';
import { IconPlus, IconSearch } from '@douyinfe/semi-icons';
import type { ColumnProps } from '@douyinfe/semi-ui/lib/es/table/interface';

import { PageHeader } from '@/components/PageHeader';
import { Empty } from '@/components/Empty';
import { useCan } from '@/hooks/useCan';
import { useUrlQuery, type UrlQueryFields } from '@/hooks/useUrlQuery';
import {
  deleteDept,
  getDeptTree,
  updateDept,
  type Dept,
} from '@/api/v1/depts';
import { isAdminApiError } from '@/lib/adminClient';
import { StatusTag } from '../shared/StatusTag';
import { OrgErrorState } from '../shared/OrgErrorState';
import { adminErrorMessage } from '@/lib/adminError';
import { AdminCopyButton } from '@/components/AdminErrorNotice';
import { isAdminSystemError } from '@/lib/adminClient';
import {
  defaultExpandedKeys,
  findDeptNode,
  isDescendantOf,
  nextChildSort,
  nextRootSort,
  relocateDept,
} from '../shared/deptTree';
import { DeptFormSheet } from './DeptFormSheet';

const URL_FIELDS: UrlQueryFields = {
  keyword: {},
};

function flattenIds(depts: Dept[]): string[] {
  return depts.flatMap((d) => [d.id, ...flattenIds(d.children)]);
}

export function DeptsPage() {
  const { t } = useTranslation('org');
  const queryClient = useQueryClient();
  const navigate = useNavigate();

  const canCreate = useCan('system:dept:create');
  const canUpdate = useCan('system:dept:update');
  const canDelete = useCan('system:dept:delete');

  const { values, setValues } = useUrlQuery(URL_FIELDS);
  const keyword = (values.keyword as string | undefined) ?? '';

  const [keywordInput, setKeywordInput] = useState(keyword);
  const lastPushed = useRef(keyword);
  useEffect(() => {
    if (keyword !== lastPushed.current) {
      lastPushed.current = keyword;
      setKeywordInput(keyword);
    }
  }, [keyword]);
  useEffect(() => {
    const timer = setTimeout(() => {
      const next = keywordInput.trim();
      if (next !== keyword) {
        lastPushed.current = next;
        setValues({ keyword: next || undefined });
      }
    }, 300);
    return () => clearTimeout(timer);
  }, [keywordInput, keyword, setValues]);

  const treeQuery = useQuery({
    queryKey: ['v1', 'depts', 'tree', { keyword: keyword || undefined }],
    queryFn: () => getDeptTree(keyword ? { keyword } : undefined),
  });
  const depts = useMemo(() => treeQuery.data?.items ?? [], [treeQuery.data]);

  const allIds = useMemo(() => flattenIds(depts), [depts]);
  const searching = keyword.trim().length > 0;
  const [manualKeys, setManualKeys] = useState<string[] | null>(null);
  const expandedKeys = searching ? allIds : (manualKeys ?? defaultExpandedKeys(depts));

  const onExpand = (expanded: boolean | undefined, record?: Dept | { groupKey: string }) => {
    const id = record && 'id' in record ? record.id : undefined;
    if (!id) {
      return;
    }
    const base = manualKeys ?? defaultExpandedKeys(depts);
    setManualKeys(expanded ? [...new Set([...base, id])] : base.filter((k) => k !== id));
  };

  const [sheetState, setSheetState] = useState<{
    open: boolean;
    mode: 'create' | 'edit' | 'child';
    dept: Dept | null;
  }>({ open: false, mode: 'create', dept: null });

  const invalidateDepts = () => {
    void queryClient.invalidateQueries({ queryKey: ['v1', 'depts'] });
    void queryClient.invalidateQueries({ queryKey: ['v1', 'org', 'dept-options'] });
    void queryClient.invalidateQueries({ queryKey: ['v1', 'users'] });
  };

  const [dragId, setDragId] = useState<string | null>(null);
  const [dropTarget, setDropTarget] = useState<string | null>(null);
  const [dropTop, setDropTop] = useState(false);
  const [moving, setMoving] = useState(false);

  const toastAdminError = (error: unknown, fallback: string) => {
    if (isAdminSystemError(error) && isAdminApiError(error)) {
      Toast.error({
        content: (
          <span>
            {t('common:errors.system.message')} <AdminCopyButton error={error} />
          </span>
        ),
        duration: 8,
      });
      return;
    }
    Toast.error(adminErrorMessage(error, fallback));
  };

  const canDropOn = (targetId: string): boolean => {
    if (!dragId || dragId === targetId || moving) {
      return false;
    }
    return !isDescendantOf(depts, dragId, targetId);
  };

  const treeKey = ['v1', 'depts', 'tree', { keyword: keyword || undefined }];

  const moveDept = async (deptId: string, targetParentId: string | null) => {
    const tree = treeQuery.data?.items;
    const dept = tree ? findDeptNode(tree, deptId) : undefined;
    if (!tree || !dept || moving) {
      return;
    }
    if (targetParentId && isDescendantOf(tree, deptId, targetParentId)) {
      Toast.error(t('depts.form.selfDescendant'));
      return;
    }
    const target = targetParentId ? findDeptNode(tree, targetParentId) : undefined;
    if (targetParentId && !target) {
      return;
    }
    const newSort = target ? nextChildSort(target, deptId) : nextRootSort(tree, deptId);
    const prevParentId = dept.parent_id;
    const prevSort = dept.sort;
    const previous = treeQuery.data;

    setMoving(true);
    queryClient.setQueryData(treeKey, { items: relocateDept(tree, deptId, targetParentId, newSort) });
    try {
      await updateDept(deptId, {
        name: dept.name,
        parent_id: targetParentId,
        leader_id: dept.leader_id,
        phone: dept.phone,
        sort: newSort,
        status: dept.status,
      });
      const moved = targetParentId !== prevParentId;
      Toast.success({
        content: (
          <span>
            {moved
              ? t('depts.form.moved', { name: dept.name })
              : t('depts.form.reordered', { name: dept.name })}{' '}
            <Button
              type="tertiary"
              size="small"
              theme="borderless"
              onClick={() => void undoMove(deptId, prevParentId, prevSort)}
            >
              {t('depts.form.undo')}
            </Button>
          </span>
        ),
        duration: 5,
      });
      invalidateDepts();
    } catch (error) {
      if (previous) {
        queryClient.setQueryData(treeKey, previous);
      }
      toastAdminError(error, t('depts.form.moveFailed'));
    } finally {
      setMoving(false);
      setDragId(null);
      setDropTarget(null);
      setDropTop(false);
    }
  };

  const undoMove = async (deptId: string, parentId: string | null, sort: number) => {
    const tree = treeQuery.data?.items;
    const dept = tree ? findDeptNode(tree, deptId) : undefined;
    if (!dept) {
      return;
    }
    try {
      await updateDept(deptId, {
        name: dept.name,
        parent_id: parentId,
        leader_id: dept.leader_id,
        phone: dept.phone,
        sort,
        status: dept.status,
      });
      Toast.success(t('depts.form.undoDone'));
      invalidateDepts();
    } catch (error) {
      toastAdminError(error, t('depts.form.moveFailed'));
    }
  };

  const toggleStatus = (dept: Dept) => {
    const next = dept.status === 'enabled' ? 'disabled' : 'enabled';
    const isDisable = next === 'disabled';
    Modal.confirm({
      title: t(isDisable ? 'depts.danger.disableTitle' : 'depts.danger.doneEnable', { name: dept.name }),
      content: isDisable ? t('depts.danger.disableContent') : undefined,
      okText: isDisable ? t('depts.danger.disableOk') : t('common.enable'),
      cancelText: t('common.thinkAgain'),
      okType: isDisable ? 'danger' : 'primary',
      onOk: async () => {
        try {
          await updateDept(dept.id, {
            name: dept.name,
            parent_id: dept.parent_id,
            leader_id: dept.leader_id,
            phone: dept.phone,
            sort: dept.sort,
            status: next,
          });
          Toast.success(
            isDisable
              ? t('depts.danger.doneDisable', { name: dept.name })
              : t('depts.danger.doneEnable', { name: dept.name }),
          );
          invalidateDepts();
        } catch (error) {
          toastAdminError(error, t('common:errors.system.message'));
        }
      },
    });
  };

  const removeDept = (dept: Dept) => {
    Modal.confirm({
      title: t('depts.danger.deleteTitle', { name: dept.name }),
      content: t('depts.danger.deleteContent'),
      okText: t('depts.danger.deleteOk'),
      cancelText: t('common.thinkAgain'),
      okType: 'danger',
      onOk: async () => {
        try {
          await deleteDept(dept.id);
          Toast.success(t('depts.danger.doneDelete', { name: dept.name }));
          invalidateDepts();
        } catch (error) {
          if (isAdminApiError(error) && error.code === 'DEPT_NOT_EMPTY') {
            Modal.info({
              title: t('depts.danger.deleteBlockedTitle', { name: dept.name }),
              content: t('depts.danger.deleteBlockedContent'),
              okText: t('common.confirm'),
            });
            return;
          }
          toastAdminError(error, t('common:errors.system.message'));
        }
      },
    });
  };

  const columns: ColumnProps<Dept>[] = [
    {
      title: t('depts.table.name'),
      render: (_: unknown, record: Dept) => (
        <div
          className="flex items-center gap-1 rounded-sm px-1"
          style={
            dropTarget === record.id
              ? { background: 'var(--semi-color-primary-light-default)' }
              : undefined
          }
          onDragOver={(e) => {
            if (canDropOn(record.id)) {
              e.preventDefault();
              e.dataTransfer.dropEffect = 'move';
              setDropTarget(record.id);
            }
          }}
          onDragLeave={() => setDropTarget((cur) => (cur === record.id ? null : cur))}
          onDrop={(e) => {
            e.preventDefault();
            const id = e.dataTransfer.getData('text/plain') || dragId;
            setDropTarget(null);
            if (id) {
              void moveDept(id, record.id);
            }
          }}
        >
          {canUpdate ? (
            <span
              draggable
              onDragStart={(e) => {
                e.dataTransfer.setData('text/plain', record.id);
                e.dataTransfer.effectAllowed = 'move';
                setDragId(record.id);
              }}
              onDragEnd={() => {
                setDragId(null);
                setDropTarget(null);
                setDropTop(false);
              }}
              className="cursor-grab select-none px-1 text-[var(--semi-color-text-2)]"
              title={t('depts.table.dragHint')}
            >
              ⋮⋮
            </span>
          ) : null}
          {canUpdate ? (
            <Button
              type="tertiary"
              size="small"
              theme="borderless"
              onClick={() => setSheetState({ open: true, mode: 'edit', dept: record })}
            >
              <Typography.Text strong ellipsis={{ showTooltip: true }}>
                {record.name}
              </Typography.Text>
            </Button>
          ) : (
            <Typography.Text strong ellipsis={{ showTooltip: true }}>
              {record.name}
            </Typography.Text>
          )}
        </div>
      ),
    },
    {
      title: t('depts.table.leader'),
      width: 160,
      render: (_: unknown, record: Dept) => {
        if (!record.leader_id) {
          return <Typography.Text type="tertiary">{t('common.noLeader')}</Typography.Text>;
        }
        if (record.leader_status === 'disabled') {
          return (
            <Space spacing={4}>
              <Avatar size="extra-small" src={record.leader_avatar ?? undefined}>
                {(record.leader_name ?? '').slice(0, 1)}
              </Avatar>
              <Typography.Text type="warning">{t('common.leaderDisabled')}</Typography.Text>
            </Space>
          );
        }
        return (
          <Space spacing={4}>
            <Avatar size="extra-small" src={record.leader_avatar ?? undefined}>
              {(record.leader_name ?? '').slice(0, 1)}
            </Avatar>
            <Typography.Text ellipsis={{ showTooltip: true }}>{record.leader_name}</Typography.Text>
          </Space>
        );
      },
    },
    {
      title: t('depts.table.userCount'),
      width: 80,
      render: (_: unknown, record: Dept) => (
        <Button
          type="tertiary"
          size="small"
          onClick={() => navigate(`/system/user?dept_id=${encodeURIComponent(record.id)}`)}
        >
          {record.user_count}
        </Button>
      ),
    },
    {
      title: t('depts.table.status'),
      width: 90,
      render: (_: unknown, record: Dept) => <StatusTag status={record.status} />,
    },
    {
      title: t('depts.table.actions'),
      width: 200,
      fixed: 'right',
      render: (_: unknown, record: Dept) => {
        const menuItems = [
          canUpdate
            ? {
                key: 'toggle',
                name: record.status === 'enabled' ? t('common.disable') : t('common.enable'),
                onClick: () => toggleStatus(record),
              }
            : null,
          canDelete
            ? { key: 'delete', name: t('common.delete'), onClick: () => removeDept(record) }
            : null,
        ].filter((i): i is { key: string; name: string; onClick: () => void } => i !== null);
        return (
          <Space spacing={4}>
            {canCreate ? (
              <Button
                type="tertiary"
                size="small"
                onClick={() => setSheetState({ open: true, mode: 'child', dept: record })}
              >
                {t('depts.addChild')}
              </Button>
            ) : null}
            {canUpdate ? (
              <Button
                type="tertiary"
                size="small"
                onClick={() => setSheetState({ open: true, mode: 'edit', dept: record })}
              >
                {t('common.edit')}
              </Button>
            ) : null}
            {menuItems.length > 0 ? (
              <Dropdown
                trigger="click"
                position="bottomRight"
                render={
                  <Dropdown.Menu>
                    {menuItems.map((item) => (
                      <Dropdown.Item key={item.key} onClick={item.onClick}>
                        {item.name}
                      </Dropdown.Item>
                    ))}
                  </Dropdown.Menu>
                }
              >
                <Button type="tertiary" size="small">
                  {t('common.more')}
                </Button>
              </Dropdown>
            ) : null}
          </Space>
        );
      },
    },
  ];

  const isFirstLoading = treeQuery.isPending && !treeQuery.data;
  const hasKeyword = keyword.trim().length > 0;

  return (
    <div className="flex flex-col gap-4">
      <PageHeader
        title={t('depts.title')}
        subtitle={t('depts.subtitle')}
        extra={
          canCreate ? (
            <Button
              theme="solid"
              icon={<IconPlus />}
              onClick={() => setSheetState({ open: true, mode: 'create', dept: null })}
            >
              {t('depts.addDept')}
            </Button>
          ) : undefined
        }
      />

      <div className="flex flex-wrap items-center gap-3">
        <Input
          prefix={<IconSearch />}
          placeholder={t('depts.filters.keywordPlaceholder')}
          value={keywordInput}
          onChange={setKeywordInput}
          showClear
          style={{ width: 260 }}
        />
        {canUpdate ? (
          <>
            <Button type="tertiary" size="small" onClick={() => setManualKeys(allIds)}>
              {t('common.expandAll')}
            </Button>
            <Button
              type="tertiary"
              size="small"
              onClick={() => setManualKeys(defaultExpandedKeys(depts))}
            >
              {t('common.collapseAll')}
            </Button>
          </>
        ) : null}
        {hasKeyword ? (
          <Button
            type="tertiary"
            size="small"
            onClick={() => {
              lastPushed.current = '';
              setKeywordInput('');
              setValues({ keyword: undefined });
            }}
          >
            {t('depts.filters.clear')}
          </Button>
        ) : null}
      </div>

      {canUpdate && dragId ? (
        <div
          className="rounded-md border border-dashed border-[var(--semi-color-primary)] px-3 py-2 text-center"
          style={dropTop ? { background: 'var(--semi-color-primary-light-default)' } : undefined}
          onDragOver={(e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            setDropTop(true);
          }}
          onDragLeave={() => setDropTop(false)}
          onDrop={(e) => {
            e.preventDefault();
            const id = e.dataTransfer.getData('text/plain') || dragId;
            setDropTop(false);
            if (id) {
              void moveDept(id, null);
            }
          }}
        >
          <Typography.Text type="secondary">{t('depts.table.dropToTop')}</Typography.Text>
        </div>
      ) : null}

      {treeQuery.isError ? (
        <OrgErrorState
          title={t('depts.loadFailed.title')}
          description={t('depts.loadFailed.description')}
          error={treeQuery.error}
          onRetry={() => void treeQuery.refetch()}
        />
      ) : isFirstLoading ? (
        <Table<Dept> columns={columns} dataSource={[]} loading rowKey="id" pagination={false} />
      ) : depts.length === 0 ? (
        <Empty
          title={hasKeyword ? t('depts.noResult.title') : t('depts.empty.title')}
          description={hasKeyword ? t('depts.noResult.description') : t('depts.empty.description')}
        >
          {hasKeyword ? (
            <Button
              onClick={() => {
                lastPushed.current = '';
                setKeywordInput('');
                setValues({ keyword: undefined });
              }}
            >
              {t('depts.filters.clear')}
            </Button>
          ) : canCreate ? (
            <Button theme="solid" onClick={() => setSheetState({ open: true, mode: 'create', dept: null })}>
              {t('depts.addDept')}
            </Button>
          ) : null}
        </Empty>
      ) : (
        <Table<Dept>
          columns={columns}
          dataSource={depts}
          rowKey="id"
          size="default"
          loading={treeQuery.isFetching && !isFirstLoading}
          pagination={false}
          childrenRecordName="children"
          expandedRowKeys={expandedKeys}
          onExpand={onExpand}
        />
      )}

      <DeptFormSheet
        visible={sheetState.open}
        mode={sheetState.mode}
        dept={sheetState.dept}
        tree={depts}
        onClose={() => setSheetState((s) => ({ ...s, open: false }))}
        onSaved={invalidateDepts}
      />
    </div>
  );
}
