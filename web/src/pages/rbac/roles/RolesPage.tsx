import { useEffect, useMemo, useState, type ReactNode } from 'react';

import {
  Button,
  Dropdown,
  Input,
  Modal,
  Select,
  Tag,
  Toast,
  Typography,
} from '@douyinfe/semi-ui';
import type { ColumnProps } from '@douyinfe/semi-ui/lib/es/table/interface';
import {
  IconCopy,
  IconDelete,
  IconEdit,
  IconPlus,
  IconSafe,
  IconSearch,
} from '@douyinfe/semi-icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import {
  deleteRole,
  listRoles,
  updateRoleStatus,
  type Role,
} from '@/api/v1/roles';
import type { PermScope, UserStatus } from '@/api/v1/types';
import { Empty } from '@/components/Empty';
import { ErrorState } from '@/components/ErrorState';
import { ListPage } from '@/components/ListPage';
import { PageHeader } from '@/components/PageHeader';
import { useCan } from '@/hooks/useCan';
import { useUrlQuery, type UrlQueryFields } from '@/hooks/useUrlQuery';
import { formatRelativeTime } from '@/lib/format';
import { isAdminApiError } from '@/lib/adminClient';
import { adminErrorMessage } from '@/lib/adminError';
import { CopyRoleModal } from './CopyRoleModal';
import { RoleFormSheet } from './RoleFormSheet';
import { confirmAction, confirmDanger } from '@/lib/confirmAction';

const QUERY_FIELDS: UrlQueryFields = {
  keyword: {},
  status: {},
  page: { kind: 'number', defaultValue: 1 },
  page_size: { kind: 'number', defaultValue: 20 },
};

const SCOPE_KEYS: Record<PermScope, string> = {
  all: 'roles.scope.all',
  dept_and_child: 'roles.scope.dept_and_child',
  dept: 'roles.scope.dept',
  self: 'roles.scope.self',
  custom: 'roles.scope.custom',
};

const STATUS_OPTIONS: { value: UserStatus; labelKey: string }[] = [
  { value: 'enabled', labelKey: 'roles.statusEnabled' },
  { value: 'disabled', labelKey: 'roles.statusDisabled' },
];

/** 当前用户不可动的角色：内置或超级管理员。 */
function isLockedRole(role: Role): boolean {
  return role.is_system || role.is_super;
}

export function RolesPage() {
  const { t } = useTranslation('rbac');
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { values, setValues } = useUrlQuery(QUERY_FIELDS);

  const canCreate = useCan('system:role:create');
  const canUpdate = useCan('system:role:update');
  const canDelete = useCan('system:role:delete');
  const canSetPermission = useCan('system:role:permission:index');

  const keyword = typeof values.keyword === 'string' ? values.keyword : '';
  const status =
    values.status === 'enabled' || values.status === 'disabled'
      ? values.status
      : undefined;
  const page = typeof values.page === 'number' ? values.page : 1;
  const pageSize = typeof values.page_size === 'number' ? values.page_size : 20;

  const [keywordInput, setKeywordInput] = useState(keyword);
  const [sheetRole, setSheetRole] = useState<Role | 'create' | null>(null);
  const [copyRoleTarget, setCopyRoleTarget] = useState<Role | null>(null);
  const [reassignRole, setReassignRole] = useState<Role | null>(null);
  const [replacementId, setReplacementId] = useState<string | undefined>();
  // 「更多」下拉受控：点菜单项先收起再弹确认框。
  const [moreOpenId, setMoreOpenId] = useState<string | null>(null);

  // URL 是 keyword 的唯一事实源；外部变化（后退、清空筛选）
  // 在渲染期同步进输入框。
  const [prevKeyword, setPrevKeyword] = useState(keyword);
  if (prevKeyword !== keyword) {
    setPrevKeyword(keyword);
    setKeywordInput(keyword);
  }

  // 搜索框防抖后写进 URL 里的 keyword 筛选。
  useEffect(() => {
    const timer = window.setTimeout(() => {
      const next = keywordInput.trim();
      if (next !== keyword) {
        setValues({ keyword: next || undefined, page: 1 });
      }
    }, 300);
    return () => window.clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [keywordInput]);

  const roles = useQuery({
    queryKey: ['rbac', 'roles', { keyword, status, page, page_size: pageSize }],
    queryFn: () =>
      listRoles({
        keyword: keyword || undefined,
        status,
        page,
        page_size: pageSize,
      }),
  });

  const invalidateRoles = () =>
    queryClient.invalidateQueries({ queryKey: ['rbac', 'roles'] });

  const statusMutation = useMutation({
    mutationFn: ({ role, next }: { role: Role; next: UserStatus }) =>
      updateRoleStatus(role.id, { status: next }),
    onSuccess: (_result, { role, next }) => {
      void invalidateRoles();
      Toast.success(
        t(next === 'enabled' ? 'roles.toast.enabled' : 'roles.toast.disabled', {
          name: role.name,
        }),
      );
    },
    onError: (error) => {
      Toast.error(adminErrorMessage(error, t('roles.loadFailed')));
    },
  });

  const replacementRoles = useQuery({
    queryKey: ['rbac', 'roles', 'replacement'],
    queryFn: () => listRoles({ page: 1, page_size: 100 }),
    enabled: reassignRole !== null,
  });

  const deleteMutation = useMutation({
    mutationFn: ({ role, nextRoleId }: { role: Role; nextRoleId?: string }) =>
      deleteRole(role.id, nextRoleId),
    onSuccess: (result, { role }) => {
      void invalidateRoles();
      setReassignRole(null);
      setReplacementId(undefined);
      const moved = result.moved_count;
      Toast.success(
        moved > 0 && result.replacement_role_name
          ? t('roles.toast.deletedMoved', {
              name: role.name,
              count: moved,
              replacement: result.replacement_role_name,
            })
          : t('roles.toast.deleted', { name: role.name }),
      );
    },
    onError: (error) => {
      Toast.error(adminErrorMessage(error, t('roles.loadFailed')));
    },
  });

  const confirmDisable = (role: Role) => {
    confirmDanger({
      title: t('roles.confirm.disableTitle', { name: role.name }),
      content: t('roles.confirm.disableBody'),
      okText: t('roles.disable'),
      cancelText: t('roles.confirm.thinkAgain'),
      okButtonProps: { type: 'primary' },
      onOk: () => statusMutation.mutate({ role, next: 'disabled' }),
    });
  };

  const confirmEnable = (role: Role) => {
    confirmAction({
      title: t('roles.confirm.enableTitle', { name: role.name }),
      content: t('roles.confirm.enableBody'),
      okText: t('roles.enable'),
      cancelText: t('roles.confirm.thinkAgain'),
      okButtonProps: { type: 'primary' },
      onOk: () => statusMutation.mutate({ role, next: 'enabled' }),
    });
  };

  const confirmDelete = (role: Role) => {
    if (role.user_count > 0) {
      setReplacementId(undefined);
      setReassignRole(role);
      return;
    }
    confirmDanger({
      title: t('roles.confirm.deleteTitle', { name: role.name }),
      content: t('roles.confirm.deleteBodyEmpty'),
      okText: t('roles.delete'),
      cancelText: t('roles.confirm.thinkAgain'),
      okButtonProps: { type: 'danger' },
      onOk: () => deleteMutation.mutate({ role }),
    });
  };

  const toggleStatus = (role: Role) => {
    if (role.status === 'enabled') {
      confirmDisable(role);
      return;
    }
    confirmEnable(role);
  };

  const openPermission = (role: Role) => {
    navigate(`/system/role/${encodeURIComponent(role.id)}/permission`);
  };

  const hasFilters = Boolean(keyword || status);
  const items = roles.data?.items ?? [];

  const emptyNode = hasFilters ? (
    <Empty title={t('roles.emptyFiltered')}>
      <Button
        onClick={() => setValues({ keyword: undefined, status: undefined, page: 1 })}
      >
        {t('roles.clearFilters')}
      </Button>
    </Empty>
  ) : (
    <Empty title={undefined} description={t('roles.emptyHint')}>
      {canCreate ? (
        <Button
          theme="solid"
          icon={<IconPlus />}
          onClick={() => setSheetRole('create')}
        >
          {t('roles.add')}
        </Button>
      ) : null}
    </Empty>
  );

  const columns = useMemo<ColumnProps<Role>[]>(
    () => [
      {
        title: t('roles.colRole'),
        dataIndex: 'name',
        render: (_value, role) => (
          <div className="flex flex-col">
            <div className="flex items-center gap-2">
              <Typography.Text strong>{role.name}</Typography.Text>
              {isLockedRole(role) ? (
                <Tag size="small" color="grey">
                  {t('roles.systemTag')}
                </Tag>
              ) : null}
            </div>
            {role.remark ? (
              <Typography.Text type="secondary" size="small">
                {role.remark}
              </Typography.Text>
            ) : null}
          </div>
        ),
      },
      {
        title: t('roles.colUsers'),
        dataIndex: 'user_count',
        width: 110,
        render: (_value, role) =>
          role.user_count > 0 ? (
            <Button
              theme="borderless"
              size="small"
              type="primary"
              onClick={() =>
                navigate(`/system/user?role_id=${encodeURIComponent(role.id)}`)
              }
            >
              {t('roles.usersCount', { count: role.user_count })}
            </Button>
          ) : (
            <Typography.Text type="secondary" size="small">
              {t('roles.noUsers')}
            </Typography.Text>
          ),
      },
      {
        title: t('roles.colScope'),
        dataIndex: 'data_scope',
        width: 150,
        render: (scope: PermScope) => t(SCOPE_KEYS[scope]),
      },
      {
        title: t('roles.colStatus'),
        dataIndex: 'status',
        width: 100,
        render: (value: UserStatus) =>
          value === 'enabled' ? (
            <Tag size="small" color="green">
              {t('roles.statusEnabled')}
            </Tag>
          ) : (
            <Tag size="small" color="grey">
              {t('roles.statusDisabled')}
            </Tag>
          ),
      },
      {
        title: t('roles.colUpdated'),
        dataIndex: 'update_time',
        width: 130,
        render: (value: string) => (
          <Typography.Text type="secondary" size="small">
            {formatRelativeTime(value)}
          </Typography.Text>
        ),
      },
      {
        title: t('roles.colActions'),
        fixed: 'right',
        width: 220,
        render: (_value, role) => {
          const locked = isLockedRole(role);
          const menuItems: {
            key: string;
            name: string;
            icon?: ReactNode;
            danger?: boolean;
            onClick: () => void;
          }[] = [
            canSetPermission
              ? {
                  key: 'permission',
                  name: t('roles.setPermission'),
                  onClick: () => openPermission(role),
                }
              : null,
            canCreate && !locked
              ? {
                  key: 'copy',
                  name: t('roles.copy'),
                  icon: <IconCopy />,
                  onClick: () => setCopyRoleTarget(role),
                }
              : null,
            canUpdate && !locked
              ? {
                  key: 'toggle',
                  name: role.status === 'enabled' ? t('roles.disable') : t('roles.enable'),
                  icon: <IconSafe />,
                  onClick: () => toggleStatus(role),
                }
              : null,
            canDelete && !locked
              ? {
                  key: 'delete',
                  name: t('roles.delete'),
                  icon: <IconDelete />,
                  danger: true,
                  onClick: () => confirmDelete(role),
                }
              : null,
          ].filter((i): i is NonNullable<typeof i> => i !== null);
          const showMore = menuItems.length > 0;
          return (
            <div className="flex items-center gap-1">
              {canUpdate && !locked ? (
                <Button
                  size="small"
                  theme="borderless"
                  type="primary"
                  icon={<IconEdit />}
                  onClick={() => setSheetRole(role)}
                >
                  {t('roles.edit')}
                </Button>
              ) : null}
              {showMore ? (
                <Dropdown
                  trigger="click"
                  position="bottomRight"
                  visible={moreOpenId === role.id}
                  onVisibleChange={(v) => setMoreOpenId(v ? role.id : null)}
                  render={
                    <Dropdown.Menu>
                      {menuItems.map((item) => (
                        <Dropdown.Item
                          key={item.key}
                          icon={item.icon}
                          type={item.danger ? 'danger' : undefined}
                          onClick={() => {
                            setMoreOpenId(null);
                            item.onClick();
                          }}
                        >
                          {item.name}
                        </Dropdown.Item>
                      ))}
                    </Dropdown.Menu>
                  }
                >
                  <Button size="small" theme="borderless" type="primary">
                    {t('roles.more')}
                  </Button>
                </Dropdown>
              ) : null}
            </div>
          );
        },
      },
    ],
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [t, navigate, canCreate, canUpdate, canDelete, canSetPermission],
  );

  if (roles.isError || roles.isPaused) {
    const forbidden = isAdminApiError(roles.error) && roles.error.httpStatus === 403;
    return (
      <div className="flex flex-col gap-4">
        <PageHeader title={t('roles.title')} subtitle={t('roles.subtitle')} />
        {forbidden ? (
          <ErrorState kind="403" showHome={false} />
        ) : (
          <ErrorState
            kind="error"
            error={roles.error}
            paused={roles.isPaused}
            onRetry={() => void roles.refetch()}
            showHome={false}
          />
        )}
      </div>
    );
  }

  return (
    <>
      <ListPage
        title={t('roles.title')}
        subtitle={t('roles.subtitle')}
        filters={
          <>
            <Input
              prefix={<IconSearch />}
              placeholder={t('roles.searchPlaceholder')}
              value={keywordInput}
              onChange={setKeywordInput}
              showClear
              style={{ width: 220 }}
            />
            <Select
              placeholder={t('roles.statusAll')}
              value={status ?? ''}
              onChange={(value) =>
                setValues({
                  status: value ? String(value) : undefined,
                  page: 1,
                })
              }
              showClear
              style={{ width: 140 }}
            >
              {STATUS_OPTIONS.map((opt) => (
                <Select.Option key={opt.value} value={opt.value}>
                  {t(opt.labelKey)}
                </Select.Option>
              ))}
            </Select>
          </>
        }
        toolbar={
          canCreate ? (
            <Button
              theme="solid"
              icon={<IconPlus />}
              onClick={() => setSheetRole('create')}
            >
              {t('roles.add')}
            </Button>
          ) : undefined
        }
        columns={columns}
        dataSource={items}
        rowKey="id"
        loading={roles.isPending || roles.isFetching}
        empty={emptyNode}
        pagination={
          roles.data && roles.data.total > roles.data.page_size
            ? {
                page,
                pageSize,
                total: roles.data.total,
                onChange: (nextPage, nextSize) =>
                  setValues({ page: nextPage, page_size: nextSize }),
              }
            : undefined
        }
      />
      {sheetRole !== null ? (
        <RoleFormSheet
          role={sheetRole === 'create' ? null : sheetRole}
          onClose={() => setSheetRole(null)}
          onSaved={(saved, { goPermission }) => {
            const creating = sheetRole === 'create';
            setSheetRole(null);
            Toast.success(
              t(creating ? 'roles.toast.added' : 'roles.toast.saved', { name: saved.name }),
            );
            if (goPermission) {
              openPermission(saved);
            }
          }}
        />
      ) : null}
      {reassignRole !== null ? (
        <Modal
          visible
          title={t('roles.confirm.deleteTitle', { name: reassignRole.name })}
          okText={t('roles.confirm.reassignOk')}
          cancelText={t('roles.confirm.thinkAgain')}
          okButtonProps={{ type: 'danger', disabled: !replacementId }}
          onCancel={() => {
            setReassignRole(null);
            setReplacementId(undefined);
          }}
          onOk={() => {
            if (!replacementId) {
              return;
            }
            deleteMutation.mutate({ role: reassignRole, nextRoleId: replacementId });
          }}
        >
          <div className="flex flex-col gap-3">
            <Typography.Paragraph>
              {t('roles.confirm.reassignBody', { count: reassignRole.user_count })}
            </Typography.Paragraph>
            <Select
              style={{ width: '100%' }}
              placeholder={t('roles.confirm.reassignPlaceholder')}
              value={replacementId}
              optionList={(replacementRoles.data?.items ?? [])
                .filter((item) => item.id !== reassignRole.id)
                .slice()
                .sort((left, right) => left.name.localeCompare(right.name, 'zh'))
                .map((item) => ({
                  value: item.id,
                  label: item.is_super
                    ? `${item.name}（${t('roles.confirm.superTag')}）`
                    : item.name,
                }))}
              onChange={(value) => setReplacementId(typeof value === 'string' ? value : undefined)}
            />
            {replacementId &&
            (replacementRoles.data?.items ?? []).find(
              (item) => item.id === replacementId,
            )?.is_super ? (
              <Typography.Text type="warning" size="small">
                {t('roles.confirm.reassignSuperWarning')}
              </Typography.Text>
            ) : null}
          </div>
        </Modal>
      ) : null}
      {copyRoleTarget !== null ? (
        <CopyRoleModal
          role={copyRoleTarget}
          onClose={() => setCopyRoleTarget(null)}
          onCopied={(copied) => {
            setCopyRoleTarget(null);
            void invalidateRoles();
            Toast.success(t('roles.toast.copied', { name: copied.name }));
          }}
        />
      ) : null}
    </>
  );
}
