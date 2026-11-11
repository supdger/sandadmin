import { useEffect, useMemo, useState } from 'react';

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
  IconMore,
  IconCopy,
  IconDelete,
  IconEdit,
  IconPlus,
  IconSafe,
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
import { AdminErrorNotice } from '@/components/AdminErrorNotice';
import { isAdminApiError } from '@/lib/adminClient';
import { adminErrorMessage } from '@/lib/adminError';
import { CopyRoleModal } from './CopyRoleModal';
import { RoleFormSheet } from './RoleFormSheet';

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

  const deleteMutation = useMutation({
    mutationFn: (role: Role) => deleteRole(role.id),
    onSuccess: (result, role) => {
      void invalidateRoles();
      const moved = result.moved_to_default;
      Toast.success(
        moved > 0
          ? t('roles.toast.deletedMoved', { name: role.name, count: moved })
          : t('roles.toast.deleted', { name: role.name }),
      );
    },
    onError: (error) => {
      Toast.error(adminErrorMessage(error, t('roles.loadFailed')));
    },
  });

  const confirmDisable = (role: Role) => {
    Modal.confirm({
      title: t('roles.confirm.disableTitle', { name: role.name }),
      content:
        role.user_count > 0
          ? t('roles.confirm.disableBody', { count: role.user_count })
          : t('roles.confirm.disableBodyEmpty'),
      okText: t('roles.statusDisabled'),
      cancelText: t('roles.confirm.thinkAgain'),
      okButtonProps: { type: 'danger' },
      onOk: () => statusMutation.mutate({ role, next: 'disabled' }),
    });
  };

  const confirmDelete = (role: Role) => {
    Modal.confirm({
      title: t('roles.confirm.deleteTitle', { name: role.name }),
      content:
        role.user_count > 0
          ? t('roles.confirm.deleteBodyWithUsers', { count: role.user_count })
          : t('roles.confirm.deleteBodyEmpty'),
      okText: t('roles.delete'),
      cancelText: t('roles.confirm.thinkAgain'),
      okButtonProps: { type: 'danger' },
      onOk: () => deleteMutation.mutate(role),
    });
  };

  const toggleStatus = (role: Role) => {
    if (role.status === 'enabled') {
      confirmDisable(role);
      return;
    }
    statusMutation.mutate({ role, next: 'enabled' });
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
          const showMore =
            (canCreate && !locked) || (canUpdate && !locked) || (canDelete && !locked);
          return (
            <div className="flex items-center gap-1">
              {canSetPermission ? (
                <Button size="small" theme="borderless" onClick={() => openPermission(role)}>
                  {t('roles.setPermission')}
                </Button>
              ) : null}
              {canUpdate && !locked ? (
                <Button
                  size="small"
                  theme="borderless"
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
                  render={
                    <Dropdown.Menu>
                      {canCreate && !locked ? (
                        <Dropdown.Item
                          icon={<IconCopy />}
                          onClick={() => setCopyRoleTarget(role)}
                        >
                          {t('roles.copy')}
                        </Dropdown.Item>
                      ) : null}
                      {canUpdate && !locked ? (
                        <Dropdown.Item
                          icon={<IconSafe />}
                          onClick={() => toggleStatus(role)}
                        >
                          {role.status === 'enabled'
                            ? t('roles.disable')
                            : t('roles.enable')}
                        </Dropdown.Item>
                      ) : null}
                      {canDelete && !locked ? (
                        <Dropdown.Item
                          icon={<IconDelete />}
                          type="danger"
                          onClick={() => confirmDelete(role)}
                        >
                          {t('roles.delete')}
                        </Dropdown.Item>
                      ) : null}
                    </Dropdown.Menu>
                  }
                >
                  <Button
                    size="small"
                    theme="borderless"
                    icon={<IconMore />}
                    aria-label={t('roles.more')}
                  />
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

  if (roles.isError) {
    const forbidden = isAdminApiError(roles.error) && roles.error.httpStatus === 403;
    return (
      <div className="flex flex-col gap-4">
        <PageHeader title={t('roles.title')} subtitle={t('roles.subtitle')} />
        {forbidden ? (
          <ErrorState kind="403" showHome={false} />
        ) : (
          <AdminErrorNotice
            error={roles.error}
            onRetry={() => void roles.refetch()}
            fallback={
              <ErrorState kind="error" onRetry={() => void roles.refetch()} showHome={false} />
            }
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
              prefix={<IconSafe />}
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
