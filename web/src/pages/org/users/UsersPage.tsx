import { useEffect, useMemo, useRef, useState } from 'react';

import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import {
  Avatar,
  Button,
  Dropdown,
  Input,
  Modal,
  Notification,
  Pagination,
  Select,
  Space,
  Table,
  Tag,
  Toast,
  Tooltip,
  Tree,
  TreeSelect,
  Typography,
} from '@douyinfe/semi-ui';
import { IconDownload, IconPlus, IconSearch } from '@douyinfe/semi-icons';
import type { ColumnProps } from '@douyinfe/semi-ui/lib/es/table/interface';

import { PageHeader } from '@/components/PageHeader';
import { Empty } from '@/components/Empty';
import { useCan } from '@/hooks/useCan';
import { useUrlQuery, type UrlQueryFields } from '@/hooks/useUrlQuery';
import { downloadTask, getTask } from '@/api/v1/tasks';
import {
  deleteUser,
  exportUsers,
  getUserDisableCheck,
  listUsers,
  updateUserStatus,
  type DisableCheckItem,
  type User,
} from '@/api/v1/users';
import type { UserStatus } from '@/api/v1/types';
import { formatRelativeTime, maskPhone } from '@/lib/format';
import { isAdminSystemError } from '@/lib/adminClient';
import { StatusTag } from '../shared/StatusTag';
import { OrgErrorState } from '../shared/OrgErrorState';
import { adminErrorMessage } from '@/lib/adminError';
import { AdminCopyButton } from '@/components/AdminErrorNotice';
import { saveDownloadedFile } from '../shared/download';
import {
  useOrgMe,
  useRoleOptions,
  useEnabledPostOptions,
  useUserDeptTree,
} from '../shared/useOrgOptions';
import { useViewportWidth } from '../shared/useViewportWidth';
import type { DeptTreeOption } from '../shared/deptTree';
import { UserFormSheet } from './UserFormSheet';
import { ResetPasswordModal } from './ResetPasswordModal';
import { TransferConfirmModal } from './TransferConfirmModal';

const URL_FIELDS: UrlQueryFields = {
  keyword: {},
  status: {},
  dept_id: {},
  role_id: {},
  post_id: {},
  page: { kind: 'number', defaultValue: 1 },
  page_size: { kind: 'number', defaultValue: 20 },
};

const EXPORT_POLL_MS = 1500;
const EXPORT_MAX_POLLS = 60;

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

interface DeptPanelNode {
  key: string;
  value: string;
  label: string;
  children?: DeptPanelNode[];
}

function toPanelNodes(nodes: DeptTreeOption[]): DeptPanelNode[] {
  return nodes.map((n) => ({
    key: n.value,
    value: n.value,
    label: n.label,
    children: n.children?.length ? toPanelNodes(n.children) : undefined,
  }));
}

/** 关键词过滤：保留命中节点和它的祖先路径（命中节点的子级仍按关键词过滤）。 */
function filterPanelNodes(nodes: DeptPanelNode[], keyword: string): DeptPanelNode[] {
  if (!keyword) {
    return nodes;
  }
  const result: DeptPanelNode[] = [];
  for (const node of nodes) {
    const children = node.children?.length ? filterPanelNodes(node.children, keyword) : undefined;
    if (node.label.includes(keyword) || (children && children.length > 0)) {
      result.push({ ...node, children: children?.length ? children : undefined });
    }
  }
  return result;
}

function collectKeys(nodes: DeptPanelNode[]): string[] {
  return nodes.flatMap((n) => [n.key, ...collectKeys(n.children ?? [])]);
}

/** 默认展开前两层：根节点 + 每个根的直接子级。 */
function firstTwoLevelKeys(nodes: DeptPanelNode[]): string[] {
  const keys: string[] = [];
  for (const node of nodes) {
    keys.push(node.key);
    for (const child of node.children ?? []) {
      keys.push(child.key);
    }
  }
  return keys;
}

export function UsersPage() {
  const { t } = useTranslation('org');
  const queryClient = useQueryClient();
  const viewportWidth = useViewportWidth();

  const canCreate = useCan('system:user:create');
  const canUpdate = useCan('system:user:update');
  const canDelete = useCan('system:user:delete');
  const canReset = useCan('system:user:reset-password');
  const canExport = useCan('system:user:export');

  const { values, setValues } = useUrlQuery(URL_FIELDS);
  const keyword = (values.keyword as string | undefined) ?? '';
  const status = values.status as UserStatus | undefined;
  const deptId = values.dept_id as string | undefined;
  const roleId = values.role_id as string | undefined;
  const postId = values.post_id as string | undefined;
  const page = (values.page as number) ?? 1;
  const pageSize = (values.page_size as number) ?? 20;

  const [keywordInput, setKeywordInput] = useState(keyword);
  const lastPushedKeyword = useRef<string | undefined>(undefined);
  useEffect(() => {
    if (keyword !== lastPushedKeyword.current) {
      lastPushedKeyword.current = keyword;
      setKeywordInput(keyword);
    }
  }, [keyword]);
  useEffect(() => {
    const timer = setTimeout(() => {
      const next = keywordInput.trim();
      if (next !== keyword) {
        lastPushedKeyword.current = next;
        setValues({ keyword: next || undefined, page: 1 });
      }
    }, 300);
    return () => clearTimeout(timer);
  }, [keywordInput, keyword, setValues]);

  const hasFilters = Boolean(keyword || status || deptId || roleId || postId);

  const clearFilters = () => {
    lastPushedKeyword.current = '';
    setKeywordInput('');
    setValues({
      keyword: undefined,
      status: undefined,
      dept_id: undefined,
      role_id: undefined,
      post_id: undefined,
      page: 1,
    });
  };

  const usersQuery = useQuery({
    queryKey: [
      'v1',
      'users',
      'list',
      { keyword, status, dept_id: deptId, role_id: roleId, post_id: postId, page, page_size: pageSize },
    ],
    queryFn: () =>
      listUsers({
        keyword: keyword || undefined,
        status,
        dept_id: deptId,
        role_id: roleId,
        post_id: postId,
        page,
        page_size: pageSize,
      }),
    placeholderData: (prev) => prev,
  });
  const users = usersQuery.data?.items ?? [];
  const total = usersQuery.data?.total ?? 0;

  const rolesQuery = useRoleOptions();
  const postsQuery = useEnabledPostOptions();
  const deptTreeQuery = useUserDeptTree();
  const meQuery = useOrgMe();
  const selfId = meQuery.data?.id;

  const roleNameMap = useMemo(() => {
    const map = new Map<string, string>();
    for (const role of rolesQuery.data?.items ?? []) {
      map.set(role.id, role.name);
    }
    return map;
  }, [rolesQuery.data]);

  const [panelOpen, setPanelOpen] = useState(() => viewportWidth >= 1600);
  const [deptKeyword, setDeptKeyword] = useState('');
  // 手动展开记录；搜索时全部可见节点展开，不写回。
  const [manualKeys, setManualKeys] = useState<string[] | null>(null);

  const allDeptNodes = useMemo<DeptPanelNode[]>(() => {
    const children = toPanelNodes(deptTreeQuery.data?.options ?? []);
    return [
      {
        key: '__all__',
        value: '__all__',
        label: t('users.deptPanel.allDepts'),
        children: children.length ? children : undefined,
      },
    ];
  }, [deptTreeQuery.data, t]);

  const visibleDeptNodes = useMemo(
    () => filterPanelNodes(allDeptNodes, deptKeyword.trim()),
    [allDeptNodes, deptKeyword],
  );

  const deptSearching = deptKeyword.trim().length > 0;
  const expandedKeys = deptSearching
    ? collectKeys(visibleDeptNodes)
    : (manualKeys ?? firstTwoLevelKeys(visibleDeptNodes));

  const selectedDeptKey = deptId ?? '__all__';

  const onDeptSelect = (key: string, selected: boolean) => {
    if (!selected) {
      return;
    }
    setValues({ dept_id: key === '__all__' ? undefined : key, page: 1 });
  };

  const [sheetOpen, setSheetOpen] = useState(false);
  const [sheetMode, setSheetMode] = useState<'create' | 'edit'>('create');
  const [editingUser, setEditingUser] = useState<User | null>(null);
  const [resetTarget, setResetTarget] = useState<User | null>(null);
  const [transferDialog, setTransferDialog] = useState<{
    kind: 'disable' | 'delete';
    user: User;
    items: DisableCheckItem[];
  } | null>(null);
  const [pendingRowId, setPendingRowId] = useState<string | null>(null);
  const [selectedKeys, setSelectedKeys] = useState<(string | number)[]>([]);
  const [exporting, setExporting] = useState(false);
  const exportCancelled = useRef(false);
  useEffect(
    () => () => {
      exportCancelled.current = true;
    },
    [],
  );

  const invalidateUsers = () => {
    void queryClient.invalidateQueries({ queryKey: ['v1', 'users'] });
    void queryClient.invalidateQueries({ queryKey: ['v1', 'org', 'user-options'] });
  };

  const toastAdminError = (error: unknown, fallback: string) => {
    if (isAdminSystemError(error)) {
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

  const openCreate = () => {
    setSheetMode('create');
    setEditingUser(null);
    setSheetOpen(true);
  };
  const openEdit = (user: User) => {
    setSheetMode('edit');
    setEditingUser(user);
    setSheetOpen(true);
  };

  const runStatus = async (user: User, next: UserStatus, transferTo?: string) => {
    await updateUserStatus(user.id, {
      status: next,
      ...(transferTo ? { transfer_to_user_id: transferTo } : {}),
    });
  };

  const runDelete = async (user: User, transferTo?: string) => {
    await deleteUser(user.id, transferTo ? { transferToUserId: transferTo } : undefined);
  };

  /** 停用/删除前先拉 disable-check；空清单直接确认，非空走转交弹窗。 */
  const requestDanger = async (user: User, kind: 'disable' | 'delete') => {
    setPendingRowId(user.id);
    try {
      const items = await getUserDisableCheck(user.id);
      const isDisable = kind === 'disable';
      if (items.length === 0) {
        Modal.confirm({
          title: t(isDisable ? 'users.danger.disableTitle' : 'users.danger.deleteTitle', {
            name: user.nickname,
          }),
          content: t(isDisable ? 'users.danger.disableContent' : 'users.danger.deleteContent'),
          okText: t(isDisable ? 'users.danger.disableOk' : 'users.danger.deleteOk'),
          cancelText: t('common.thinkAgain'),
          okType: 'danger',
          onOk: async () => {
            try {
              if (isDisable) {
                await runStatus(user, 'disabled');
                Toast.success(t('users.danger.doneDisable', { name: user.nickname }));
              } else {
                await runDelete(user);
                Toast.success(t('users.danger.doneDelete', { name: user.nickname }));
              }
              invalidateUsers();
            } catch (error) {
              toastAdminError(error, t('common:errors.system.message'));
            }
          },
        });
      } else {
        setTransferDialog({ kind, user, items });
      }
    } catch (error) {
      toastAdminError(error, t('common:errors.system.message'));
    } finally {
      setPendingRowId(null);
    }
  };

  const onEnable = async (user: User) => {
    setPendingRowId(user.id);
    try {
      await runStatus(user, 'enabled');
      Toast.success(t('users.danger.doneEnable', { name: user.nickname }));
      invalidateUsers();
    } catch (error) {
      toastAdminError(error, t('common:errors.system.message'));
    } finally {
      setPendingRowId(null);
    }
  };

  /** 返回是否成功；失败时由调用方留在弹窗里并提示。 */
  const onTransferConfirm = async (transferTo: string | undefined): Promise<boolean> => {
    if (!transferDialog) {
      return false;
    }
    const { kind, user } = transferDialog;
    try {
      if (kind === 'disable') {
        await runStatus(user, 'disabled', transferTo);
        Toast.success(t('users.danger.doneDisable', { name: user.nickname }));
      } else {
        await runDelete(user, transferTo);
        Toast.success(t('users.danger.doneDelete', { name: user.nickname }));
      }
      setTransferDialog(null);
      invalidateUsers();
      return true;
    } catch (error) {
      toastAdminError(error, t('common:errors.system.message'));
      return false;
    }
  };

  const batchRun = async (kind: 'disable' | 'delete') => {
    const targets = users.filter((u) => selectedKeys.includes(u.id));
    const results = await Promise.allSettled(
      targets.map((u) => (kind === 'disable' ? runStatus(u, 'disabled') : runDelete(u))),
    );
    const failures = results
      .map((r, i) => ({ user: targets[i], reason: r.status === 'rejected' ? r.reason : null }))
      .filter((f) => f.reason !== null);
    const okCount = targets.length - failures.length;
    const action =
      kind === 'disable' ? t('users.batch.actionDisable') : t('users.batch.actionRemove');
    if (failures.length === 0) {
      Toast.success(t('users.batch.doneAll', { action, count: okCount }));
    } else {
      Notification.warning({
        title: t('users.batch.donePartial', { ok: okCount, fail: failures.length, action }),
        content: failures
          .map((f) => `${f.user.nickname}：${adminErrorMessage(f.reason, t('common:errors.system.message'))}`)
          .join('\n'),
        duration: 0,
      });
    }
    setSelectedKeys([]);
    invalidateUsers();
  };

  const confirmBatch = (kind: 'disable' | 'delete') => {
    const count = selectedKeys.length;
    const isDisable = kind === 'disable';
    Modal.confirm({
      title: t(isDisable ? 'users.batch.disableTitle' : 'users.batch.removeTitle', { count }),
      content: t(isDisable ? 'users.batch.disableContent' : 'users.batch.removeContent'),
      okText: t(isDisable ? 'users.batch.disableOk' : 'users.batch.removeOk', { count }),
      cancelText: t('common.thinkAgain'),
      okType: 'danger',
      onOk: () => batchRun(kind),
    });
  };

  const runExport = async () => {
    setExporting(true);
    exportCancelled.current = false;
    try {
      const accepted = await exportUsers({
        keyword: keyword || undefined,
        status,
        dept_id: deptId,
        role_id: roleId,
        post_id: postId,
      });
      let finished = false;
      for (let i = 0; i < EXPORT_MAX_POLLS; i += 1) {
        await sleep(EXPORT_POLL_MS);
        if (exportCancelled.current) {
          return;
        }
        const task = await getTask(accepted.task_id);
        if (task.state === 'succeeded') {
          const file = await downloadTask(accepted.task_id);
          saveDownloadedFile(file, 'users.csv');
          Toast.success(t('users.exportDone'));
          finished = true;
          break;
        }
        if (task.state === 'failed' || task.state === 'cancelled') {
          Toast.error(task.error?.message || t('users.exportFailed'));
          finished = true;
          break;
        }
      }
      if (!finished) {
        Toast.error(t('users.exportFailed'));
      }
    } catch (error) {
      toastAdminError(error, t('users.exportFailed'));
    } finally {
      setExporting(false);
    }
  };

  const showLastLogin = viewportWidth >= 1400;

  const columns: ColumnProps<User>[] = [
    {
      title: t('users.table.user'),
      width: 220,
      render: (_: unknown, record: User) => (
        <div className="flex items-center gap-2">
          <Avatar size="small" src={record.avatar ?? undefined}>
            {record.nickname.slice(0, 1)}
          </Avatar>
          <div className="min-w-0">
            {canUpdate ? (
              <Button type="tertiary" size="small" theme="borderless" onClick={() => openEdit(record)}>
                <Typography.Text strong ellipsis={{ showTooltip: true }}>
                  {record.nickname}
                </Typography.Text>
              </Button>
            ) : (
              <Typography.Text strong ellipsis={{ showTooltip: true }}>
                {record.nickname}
              </Typography.Text>
            )}
            <div>
              <Typography.Text type="secondary" size="small">
                {record.username ?? t('users.table.usernameFallback')}
              </Typography.Text>
            </div>
          </div>
        </div>
      ),
    },
    {
      title: t('users.table.phone'),
      width: 140,
      render: (_: unknown, record: User) => (
        <Tooltip content={record.phone}>
          <Typography.Text>{maskPhone(record.phone) || '—'}</Typography.Text>
        </Tooltip>
      ),
    },
    {
      title: t('users.table.dept'),
      width: 140,
      render: (_: unknown, record: User) => (
        <Typography.Text ellipsis={{ showTooltip: true }}>{record.dept_name ?? '—'}</Typography.Text>
      ),
    },
    {
      title: t('users.table.roles'),
      width: 180,
      render: (_: unknown, record: User) => {
        const names = record.role_ids
          .map((id) => roleNameMap.get(id))
          .filter((n): n is string => Boolean(n));
        if (names.length === 0) {
          return <Typography.Text type="secondary">—</Typography.Text>;
        }
        const shown = names.slice(0, 2);
        const overflow = record.role_ids.length - shown.length;
        const tooltip =
          names.join('、') +
          (record.role_ids.length > names.length
            ? `，${t('users.table.otherRoles', { count: record.role_ids.length })}`
            : '');
        return (
          <Space spacing={4} wrap>
            {shown.map((name) => (
              <Tag key={name} size="small">
                {name}
              </Tag>
            ))}
            {overflow > 0 ? (
              <Tooltip content={tooltip}>
                <Typography.Text type="secondary" size="small">
                  {t('users.table.moreRoles', { count: overflow })}
                </Typography.Text>
              </Tooltip>
            ) : null}
          </Space>
        );
      },
    },
    {
      title: t('users.table.status'),
      width: 100,
      render: (_: unknown, record: User) => <StatusTag status={record.status} />,
    },
    ...(showLastLogin
      ? [
          {
            title: t('users.table.lastLogin'),
            width: 120,
            render: (_: unknown, record: User) => (
              <Typography.Text type="secondary">
                {record.last_login_time
                  ? formatRelativeTime(record.last_login_time)
                  : t('users.table.neverLogin')}
              </Typography.Text>
            ),
          } satisfies ColumnProps<User>,
        ]
      : []),
    {
      title: t('users.table.actions'),
      width: 180,
      fixed: 'right',
      render: (_: unknown, record: User) => {
        const isSelf = record.id === selfId;
        const menuItems = [
          canReset
            ? {
                key: 'reset',
                name: t('users.table.resetPassword'),
                onClick: () => setResetTarget(record),
              }
            : null,
          canDelete && !isSelf
            ? {
                key: 'delete',
                name: t('users.table.delete'),
                onClick: () => void requestDanger(record, 'delete'),
              }
            : null,
        ].filter((item): item is { key: string; name: string; onClick: () => void } => item !== null);
        return (
          <Space spacing={4}>
            {canUpdate ? (
              <Button type="tertiary" size="small" onClick={() => openEdit(record)}>
                {t('users.table.edit')}
              </Button>
            ) : null}
            {canUpdate && !isSelf ? (
              record.status === 'enabled' ? (
                <Button
                  type="tertiary"
                  size="small"
                  loading={pendingRowId === record.id}
                  onClick={() => void requestDanger(record, 'disable')}
                >
                  {t('users.table.disable')}
                </Button>
              ) : (
                <Button
                  type="tertiary"
                  size="small"
                  loading={pendingRowId === record.id}
                  onClick={() => void onEnable(record)}
                >
                  {t('users.table.enable')}
                </Button>
              )
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
                  {t('users.table.more')}
                </Button>
              </Dropdown>
            ) : null}
          </Space>
        );
      },
    },
  ];

  const isFirstLoading = usersQuery.isPending && !usersQuery.data;

  return (
    <div className="flex gap-4">
      {panelOpen ? (
        <aside className="flex w-[220px] shrink-0 flex-col gap-2">
          <Input
            prefix={<IconSearch />}
            placeholder={t('users.deptPanel.searchPlaceholder')}
            value={deptKeyword}
            onChange={setDeptKeyword}
            showClear
          />
          <div className="max-h-[560px] overflow-auto rounded-md border border-[var(--semi-color-border)] p-2">
            {deptTreeQuery.isPending ? (
              <Typography.Text type="secondary">{t('common:loading')}</Typography.Text>
            ) : (
              <Tree
                treeData={visibleDeptNodes}
                value={selectedDeptKey}
                expandedKeys={expandedKeys}
                onSelect={onDeptSelect}
                onExpand={(keys) => {
                  if (!deptSearching) {
                    setManualKeys(keys as string[]);
                  }
                }}
                emptyContent={t('users.deptPanel.allDepts')}
              />
            )}
          </div>
        </aside>
      ) : null}

      <div className="flex min-w-0 flex-1 flex-col gap-4">
        <PageHeader
          title={t('users.title')}
          subtitle={t('users.subtitle')}
          extra={
            <Space wrap>
              <Button type="tertiary" size="small" onClick={() => setPanelOpen((v) => !v)}>
                {panelOpen ? t('users.deptPanel.hide') : t('users.deptPanel.show')}
              </Button>
              {canExport ? (
                <Button
                  type="tertiary"
                  icon={<IconDownload />}
                  loading={exporting}
                  onClick={() => void runExport()}
                >
                  {exporting ? t('users.exporting') : t('users.export')}
                </Button>
              ) : null}
              {canCreate ? (
                <Button theme="solid" icon={<IconPlus />} onClick={openCreate}>
                  {t('users.addUser')}
                </Button>
              ) : null}
            </Space>
          }
        />

        <div className="flex flex-wrap items-center gap-3">
          <Input
            prefix={<IconSearch />}
            placeholder={t('users.filters.keywordPlaceholder')}
            value={keywordInput}
            onChange={setKeywordInput}
            showClear
            style={{ width: 260 }}
          />
          <Select
            placeholder={t('users.filters.statusPlaceholder')}
            showClear
            filter
            value={status}
            onChange={(v) => setValues({ status: (v as UserStatus) || undefined, page: 1 })}
            optionList={[
              { value: 'enabled', label: t('status.enabled') },
              { value: 'disabled', label: t('status.disabled') },
            ]}
            style={{ width: 140 }}
          />
          <Select
            placeholder={t('users.filters.rolePlaceholder')}
            showClear
            filter
            value={roleId}
            onChange={(v) => setValues({ role_id: (v as string) || undefined, page: 1 })}
            optionList={(rolesQuery.data?.items ?? []).map((r) => ({
              value: r.id,
              label: r.name,
              disabled: r.status === 'disabled',
            }))}
            style={{ width: 160 }}
          />
          {!panelOpen ? (
            <TreeSelect
              placeholder={t('users.filters.deptPlaceholder')}
              showClear
              filterTreeNode
              value={deptId}
              onChange={(v) => setValues({ dept_id: (v as string) || undefined, page: 1 })}
              treeData={deptTreeQuery.data?.options ?? []}
              style={{ width: 180 }}
            />
          ) : null}
          <Select
            placeholder={t('users.filters.postPlaceholder')}
            showClear
            filter
            value={postId}
            onChange={(v) => setValues({ post_id: (v as string) || undefined, page: 1 })}
            optionList={(postsQuery.data?.items ?? []).map((p) => ({ value: p.id, label: p.name }))}
            style={{ width: 160 }}
          />
          {hasFilters ? (
            <Button type="tertiary" size="small" onClick={clearFilters}>
              {t('users.filters.clear')}
            </Button>
          ) : null}
        </div>

        {selectedKeys.length > 0 ? (
          <div className="flex items-center gap-3 rounded-md border border-[var(--semi-color-border)] bg-[var(--semi-color-fill-0)] px-3 py-2">
            <Typography.Text>{t('users.batch.selected', { count: selectedKeys.length })}</Typography.Text>
            {canUpdate ? (
              <Button type="tertiary" size="small" onClick={() => confirmBatch('disable')}>
                {t('users.batch.disable')}
              </Button>
            ) : null}
            {canDelete ? (
              <Button
                type="tertiary"
                size="small"
                theme="solid"
                onClick={() => confirmBatch('delete')}
              >
                {t('users.batch.remove')}
              </Button>
            ) : null}
            <Button type="tertiary" size="small" onClick={() => setSelectedKeys([])}>
              {t('users.batch.cancel')}
            </Button>
          </div>
        ) : null}

        {usersQuery.isError ? (
          <OrgErrorState
            title={t('users.loadFailed.title')}
            description={t('users.loadFailed.description')}
            error={usersQuery.error}
            onRetry={() => void usersQuery.refetch()}
          />
        ) : isFirstLoading ? (
          <Table<User> columns={columns} dataSource={[]} loading rowKey="id" pagination={false} />
        ) : users.length === 0 ? (
          <Empty
            title={hasFilters ? t('users.noResult.title') : t('users.empty.title')}
            description={hasFilters ? t('users.noResult.description') : t('users.empty.description')}
          >
            {hasFilters ? (
              <Button onClick={clearFilters}>{t('users.filters.clear')}</Button>
            ) : canCreate ? (
              <Button theme="solid" onClick={openCreate}>
                {t('users.addUser')}
              </Button>
            ) : null}
          </Empty>
        ) : (
          <>
            <Table<User>
              columns={columns}
              dataSource={users}
              rowKey="id"
              size="default"
              loading={usersQuery.isFetching && !isFirstLoading}
              pagination={false}
              rowSelection={{
                selectedRowKeys: selectedKeys,
                onChange: (keys) => setSelectedKeys(keys as (string | number)[]),
              }}
            />
            {total > pageSize || page > 1 ? (
              <div className="flex justify-end">
                <Pagination
                  total={total}
                  currentPage={page}
                  pageSize={pageSize}
                  pageSizeOpts={[10, 20, 50, 100]}
                  onChange={(p, ps) => setValues({ page: p, page_size: ps })}
                  showTotal
                  showSizeChanger
                />
              </div>
            ) : null}
          </>
        )}
      </div>

      <UserFormSheet
        // 关闭态 key 需与同页其它弹窗区分，避免兄弟节点 key 重复告警。
        key={sheetOpen ? `${sheetMode}-${editingUser?.id ?? 'new'}` : 'user-sheet-closed'}
        visible={sheetOpen}
        mode={sheetMode}
        user={editingUser}
        defaultDeptId={deptId}
        onClose={() => setSheetOpen(false)}
        onSaved={invalidateUsers}
      />
      <ResetPasswordModal
        key={resetTarget?.id ?? 'reset-password-closed'}
        user={resetTarget}
        onClose={() => setResetTarget(null)}
      />
      <TransferConfirmModal
        key={transferDialog ? `${transferDialog.kind}-${transferDialog.user.id}` : 'transfer-confirm-closed'}
        state={transferDialog}
        onClose={() => setTransferDialog(null)}
        onConfirm={onTransferConfirm}
      />
    </div>
  );
}
