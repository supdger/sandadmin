import { useEffect, useMemo, useState } from 'react';

import {
  Button,
  Card,
  Modal,
  Radio,
  Spin,
  Toast,
  TreeSelect,
  Typography,
} from '@douyinfe/semi-ui';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { useNavigate, useParams } from 'react-router';

import { getDeptTree, type Dept } from '@/api/v1/depts';
import {
  getPermissionTree,
  type PermissionAction,
} from '@/api/v1/permissions';
import {
  getRole,
  getRolePermissions,
  saveRolePermissions,
} from '@/api/v1/roles';
import type { PermScope, Id } from '@/api/v1/types';
import { Empty } from '@/components/Empty';
import { ErrorState } from '@/components/ErrorState';
import { PageHeader } from '@/components/PageHeader';
import { useCan } from '@/hooks/useCan';
import { useMe } from '@/hooks/useMe';
import { AdminErrorNotice } from '@/components/AdminErrorNotice';
import { isAdminApiError } from '@/lib/adminClient';
import { adminErrorMessage } from '@/lib/adminError';
import {
  toggleGroupCodes,
  togglePermissionCode,
} from '@/pages/rbac/lib/permissionMatrix';
import { PermissionMatrix } from './PermissionMatrix';

const SCOPE_ORDER: PermScope[] = [
  'all',
  'dept_and_child',
  'dept',
  'self',
  'custom',
];

interface DeptOption {
  key: string;
  value: string;
  label: string;
  disabled?: boolean;
  children?: DeptOption[];
}

function toDeptOptions(depts: Dept[]): DeptOption[] {
  return depts.map((dept) => ({
    key: dept.id,
    value: dept.id,
    label: dept.name,
    disabled: dept.status !== 'enabled',
    children: dept.children?.length ? toDeptOptions(dept.children) : undefined,
  }));
}

function sameSet(a: ReadonlySet<string>, b: readonly string[]): boolean {
  if (a.size !== b.length) return false;
  return b.every((code) => a.has(code));
}

function sameIds(a: readonly Id[], b: readonly Id[]): boolean {
  if (a.length !== b.length) return false;
  const set = new Set(a);
  return b.every((id) => set.has(id));
}

/**
 * 单个角色的独立权限编辑器。加载已保存的权限集和完整权限树，
 * 管理员勾选动作并选数据范围，然后连同乐观锁 `base_version`
 * 一起 PUT 回去。
 */
export function RolePermissionPage() {
  const { t } = useTranslation('rbac');
  const { roleId = '' } = useParams<{ roleId: string }>();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const canUpdate = useCan('system:role:permission:update');
  const me = useMe();
  const granted: string[] | '*' = me.data?.permissions.includes('*')
    ? '*'
    : (me.data?.permissions ?? []);

  const role = useQuery({
    queryKey: ['rbac', 'role', roleId],
    queryFn: () => getRole(roleId),
    enabled: Boolean(roleId),
  });
  const permissions = useQuery({
    queryKey: ['rbac', 'role-permissions', roleId],
    queryFn: () => getRolePermissions(roleId),
    enabled: Boolean(roleId),
  });
  const tree = useQuery({
    queryKey: ['rbac', 'permission-tree'],
    queryFn: getPermissionTree,
    staleTime: 60_000,
  });
  const depts = useQuery({
    queryKey: ['rbac', 'dept-options'],
    queryFn: () => getDeptTree(),
    staleTime: 60_000,
  });

  const [permScope, setPermScope] = useState<PermScope>('self');
  const [deptIds, setDeptIds] = useState<Id[]>([]);
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [baseVersion, setBaseVersion] = useState(1);
  const [seedStamp, setSeedStamp] = useState('');
  // stale-version 路径递增它，强制按新数据重新播种。
  const [resetSeed, setResetSeed] = useState(0);

  // 权限载荷（重新）加载时播种本地状态。stamp 保证 base_version
  // 未变的后台 refetch 不会冲掉正在编辑的内容。
  const wantStamp = permissions.data
    ? `${roleId}:${permissions.data.base_version}:${resetSeed}`
    : '';
  if (permissions.data && wantStamp !== seedStamp) {
    setSeedStamp(wantStamp);
    setPermScope(permissions.data.data_scope);
    setDeptIds(permissions.data.dept_ids);
    setSelected(new Set(permissions.data.permission_codes));
    setBaseVersion(permissions.data.base_version);
  }

  const dirty = useMemo(() => {
    const data = permissions.data;
    if (!data) return false;
    return (
      permScope !== data.data_scope ||
      !sameIds(deptIds, data.dept_ids) ||
      !sameSet(selected, data.permission_codes)
    );
  }, [permissions.data, permScope, deptIds, selected]);

  // 有未保存改动时离开页面前提醒。
  useEffect(() => {
    if (!dirty) return;
    const handler = (event: BeforeUnloadEvent) => {
      event.preventDefault();
    };
    window.addEventListener('beforeunload', handler);
    return () => window.removeEventListener('beforeunload', handler);
  }, [dirty]);

  const readonly = Boolean(permissions.data?.readonly) || !canUpdate;
  const isSuper = Boolean(role.data?.is_super);

  const refreshPermissions = () => {
    void queryClient.invalidateQueries({
      queryKey: ['rbac', 'role-permissions', roleId],
    });
    void queryClient.invalidateQueries({ queryKey: ['rbac', 'roles'] });
  };

  const save = useMutation({
    mutationFn: () =>
      saveRolePermissions(roleId, {
        data_scope: permScope,
        dept_ids: permScope === 'custom' ? deptIds : [],
        permission_codes: Array.from(selected),
        base_version: baseVersion,
      }),
    onSuccess: (result) => {
      Toast.success(
        t('permission.toastSaved', { count: result.affected_users }),
      );
      refreshPermissions();
    },
    onError: (error) => {
      if (isAdminApiError(error) && error.code === 'STALE_VERSION') {
        Toast.warning(t('permission.staleVersion'));
        setResetSeed((count) => count + 1);
        void permissions.refetch();
        return;
      }
      Toast.error(adminErrorMessage(error, t('permission.loadFailed')));
    },
  });

  const onToggleCode = (action: PermissionAction, siblings: PermissionAction[]) => {
    setSelected((prev) => togglePermissionCode(prev, action, siblings));
  };

  const onToggleGroup = (codes: string[], check: boolean) => {
    setSelected((prev) => toggleGroupCodes(prev, codes, check));
  };

  const requestCancel = () => {
    if (!dirty) {
      navigate('/system/role');
      return;
    }
    Modal.confirm({
      title: t('permission.discardTitle'),
      okText: t('permission.discardOk'),
      cancelText: t('permission.discardCancel'),
      okButtonProps: { type: 'danger' },
      onOk: () => navigate('/system/role'),
    });
  };

  const deptOptions = useMemo(
    () => (depts.data ? toDeptOptions(depts.data.items) : []),
    [depts.data],
  );

  const customInvalid = permScope === 'custom' && deptIds.length === 0;

  if (role.isError || permissions.isError) {
    const failed = role.isError ? role : permissions;
    return (
      <div className="flex flex-col gap-4">
        <PageHeader title={t('permission.breadcrumbSet')} />
        <AdminErrorNotice
          error={failed.error}
          onRetry={() => {
            void role.refetch();
            void permissions.refetch();
          }}
          fallback={<ErrorState kind="error" onRetry={() => void permissions.refetch()} showHome />}
        />
      </div>
    );
  }

  const roleName = role.data?.name ?? '';
  const userCount = permissions.data?.user_count ?? role.data?.user_count ?? 0;

  return (
    <div className="flex flex-col gap-4">
      <PageHeader
        title={roleName || t('permission.breadcrumbSet')}
        subtitle={
          userCount > 0
            ? t('permission.subtitleWithUsers', { count: userCount })
            : undefined
        }
        extra={
          <Button theme="light" onClick={requestCancel}>
            {t('permission.cancel')}
          </Button>
        }
      />

      {readonly ? (
        <Typography.Text type="warning">
          {isSuper
            ? t('permission.superReadonlyBanner')
            : t('permission.readonlyBanner')}
        </Typography.Text>
      ) : null}

      {permissions.isPending || role.isPending || tree.isPending ? (
        <div className="flex justify-center py-16">
          <Spin />
        </div>
      ) : permissions.data && tree.data ? (
        <>
          <Card
            title={
              <div className="flex flex-col">
                <Typography.Text strong>
                  {t('permission.scopeCard')}
                </Typography.Text>
                <Typography.Text type="secondary" size="small">
                  {t('permission.scopeHint')}
                </Typography.Text>
              </div>
            }
          >
            <Radio.Group
              type="button"
              value={permScope}
              disabled={readonly}
              onChange={(e) => setPermScope(e.target.value as PermScope)}
            >
              {SCOPE_ORDER.map((scope) => (
                <Radio key={scope} value={scope}>
                  <div className="flex flex-col">
                    <Typography.Text>
                      {t(`permission.scopeOptions.${scope}.label`)}
                    </Typography.Text>
                    <Typography.Text type="secondary" size="small">
                      {t(`permission.scopeOptions.${scope}.desc`)}
                    </Typography.Text>
                  </div>
                </Radio>
              ))}
            </Radio.Group>
            {permScope === 'custom' ? (
              <div className="mt-3 max-w-md">
                <Typography.Text strong size="small">
                  {t('permission.deptSelect')}
                </Typography.Text>
                <TreeSelect
                  className="mt-1 w-full"
                  multiple
                  showClear
                  treeData={deptOptions}
                  value={deptIds}
                  placeholder={t('permission.deptSelectPlaceholder')}
                  disabled={readonly}
                  filterTreeNode
                  onChange={(value) =>
                    setDeptIds(
                      (Array.isArray(value) ? value : value ? [value] : []).map(
                        String,
                      ),
                    )
                  }
                />
                {customInvalid ? (
                  <Typography.Text type="danger" size="small">
                    {t('permission.deptRequired')}
                  </Typography.Text>
                ) : null}
              </div>
            ) : null}
          </Card>

          <Card title={t('permission.functionCard')}>
            <PermissionMatrix
              groups={tree.data.items}
              selected={selected}
              granted={granted}
              disabled={readonly}
              onToggleCode={onToggleCode}
              onToggleGroup={onToggleGroup}
            />
          </Card>

          <div className="sticky bottom-0 flex items-center justify-end gap-3 rounded-lg border border-[var(--semi-color-border)] bg-[var(--semi-color-bg-1)] px-4 py-3">
            <Typography.Text type="secondary" size="small">
              {t('permission.selectedCount', { count: selected.size })}
            </Typography.Text>
            <Button theme="light" onClick={requestCancel}>
              {t('permission.cancel')}
            </Button>
            <Button
              theme="solid"
              disabled={readonly || customInvalid || !dirty}
              loading={save.isPending}
              onClick={() => save.mutate()}
            >
              {save.isPending ? t('permission.saving') : t('permission.save')}
            </Button>
          </div>
        </>
      ) : (
        <Empty />
      )}
    </div>
  );
}
