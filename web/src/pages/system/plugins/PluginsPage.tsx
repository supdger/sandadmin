import { useEffect, useMemo, useRef, useState } from 'react';

import {
  Banner,
  Button,
  Card,
  Checkbox,
  Dropdown,
  Input,
  Modal,
  SideSheet,
  Skeleton,
  Tabs,
  Tag,
  Toast,
  Tooltip,
  Typography,
} from '@douyinfe/semi-ui';
import { IconAlertTriangle, IconMore, IconSearch, IconUpload } from '@douyinfe/semi-icons';
import { IllustrationNoAccess, IllustrationNoResult } from '@douyinfe/semi-illustrations';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import { API_ERROR_CODES } from '@/api/errorCodes';
import type { LifecycleRequest } from '@/api/v1/lifecycle';
import {
  getPlugin,
  getPluginsOverview,
  installPlugin,
  listInstalledPlugins,
  listMarketPlugins,
  uninstallPlugin,
  upgradeAllPlugins,
  upgradePlugin,
  uploadPluginPackage,
  type PluginMarketItem,
} from '@/api/v1/plugins';
import { Empty } from '@/components/Empty';
import { ErrorState } from '@/components/ErrorState';
import { PageHeader } from '@/components/PageHeader';
import { useCan } from '@/hooks/useCan';
import { isLifecycleRunning, useLifecycleRequest } from '@/hooks/useLifecycleRequest';
import { useMe } from '@/hooks/useMe';
import { isAdminApiError } from '@/lib/adminClient';
import { adminErrorMessage } from '@/lib/adminError';
import {
  actionOfKind,
  batchDoneCount,
  batchFailedNames,
  isBusyError,
  isPermissionRevoked,
  showPermissionRevoked,
  targetNameOf,
} from '../lifecycleShared';

const PLUGINS_QUERY_KEY = ['plugins'] as const;

type PluginTab = 'all' | 'installed' | 'upgradeable';

/** 卡片右上角的态标：进行中生命周期态 > 回滚 > 有新版本 > 安装态。 */
function pluginTag(
  item: PluginMarketItem,
  t: (key: string) => string,
  tl: (key: string) => string,
) {
  if (item.task_state === 'queued' || item.task_state === 'running') {
    return (
      <Tag color={item.task_state === 'running' ? 'blue' : 'grey'} size="small">
        {tl(`states.${item.task_state}`)}
      </Tag>
    );
  }
  if (item.rolled_back) {
    return <Tag color="orange" size="small">{t('states.rolledBack')}</Tag>;
  }
  if (item.installed && item.upgrade_available) {
    return <Tag color="blue" size="small">{t('states.upgradeable')}</Tag>;
  }
  return item.installed ? (
    <Tag color="green" size="small">{t('states.installed')}</Tag>
  ) : (
    <Tag color="grey" size="small">{t('states.uninstalled')}</Tag>
  );
}

/** provides.menus 第一条的 url；结构随插件而定，逐一守卫。 */
function firstMenuUrl(provides: Record<string, unknown> | null): string | null {
  if (!provides) return null;
  const menus = provides.menus;
  if (!Array.isArray(menus)) return null;
  for (const entry of menus) {
    if (
      typeof entry === 'object' &&
      entry !== null &&
      typeof (entry as { url?: unknown }).url === 'string' &&
      (entry as { url: string }).url.startsWith('/')
    ) {
      return (entry as { url: string }).url;
    }
  }
  return null;
}

/** provides.menus 的条数与前几项标题（详情抽屉「会多出这些菜单」）。 */
function menuNames(provides: Record<string, unknown> | null): string[] {
  if (!provides) return [];
  const menus = provides.menus;
  if (!Array.isArray(menus)) return [];
  const names: string[] = [];
  for (const entry of menus) {
    if (typeof entry === 'object' && entry !== null && typeof (entry as { title?: unknown }).title === 'string') {
      names.push((entry as { title: string }).title);
    }
  }
  return names;
}

interface Busy {
  /** 当前正在做的申请（自己的或别人的）。 */
  request: LifecycleRequest | null;
  /** 别人在做时只知道插件编码/名字。 */
  doing: string;
  active: boolean;
}

interface UninstallTarget {
  code: string;
  name: string;
}

/** 插件管理页：市场卡片 + 已安装 + 有新版本；操作时全页互斥。 */
export function PluginsPage() {
  const { t } = useTranslation('plugins');
  const { t: tl } = useTranslation('lifecycle');
  const queryClient = useQueryClient();
  const { data: me } = useMe();
  const isSuper = me?.is_super === true;

  const [tab, setTab] = useState<PluginTab>('all');
  const [keyword, setKeyword] = useState('');
  const [detailCode, setDetailCode] = useState<string | null>(null);
  // 详情抽屉里的「更多」下拉受控：点卸载先收起再进确认流程。
  const [detailMoreOpen, setDetailMoreOpen] = useState(false);
  const [uninstallTarget, setUninstallTarget] = useState<UninstallTarget | null>(null);
  const [purge, setPurge] = useState(false);
  const [requestId, setRequestId] = useState<string | null>(null);
  const [blockedTarget, setBlockedTarget] = useState<string | null>(null);
  // 提交时记住动作与对象，终态提示和卸载口径要用；state 而不是 ref，渲染期可读。
  const [pendingAction, setPendingAction] = useState<{
    kind: LifecycleRequest['kind'];
    code: string;
    purge: boolean;
  } | null>(null);
  const handledRequest = useRef<string | null>(null);

  const overview = useQuery({
    queryKey: [...PLUGINS_QUERY_KEY, 'overview'],
    queryFn: getPluginsOverview,
    placeholderData: (previous) => previous,
  });
  const market = useQuery({
    queryKey: [...PLUGINS_QUERY_KEY, 'market'],
    queryFn: listMarketPlugins,
    refetchInterval: (query) =>
      (query.state.data?.items ?? []).some((p) => p.task_state === 'queued' || p.task_state === 'running')
        ? 3_000
        : false,
  });
  const installed = useQuery({
    queryKey: [...PLUGINS_QUERY_KEY, 'installed'],
    queryFn: listInstalledPlugins,
    refetchInterval: (query) =>
      (query.state.data?.items ?? []).some((p) => p.task_state === 'queued' || p.task_state === 'running')
        ? 3_000
        : false,
  });

  const poll = useLifecycleRequest(requestId);
  const ownRequest = poll.request;

  const refreshPlugins = () => {
    void queryClient.invalidateQueries({ queryKey: PLUGINS_QUERY_KEY });
  };

  const nameOfCode = (code: string): string | null =>
    (market.data?.items ?? []).find((p) => p.code === code)?.name ??
    (installed.data?.items ?? []).find((p) => p.code === code)?.name ??
    null;

  // 忙碌判定：自己的申请在跑，或任一插件带着进行中的 task_state。
  const externalBusyItem = (market.data?.items ?? []).find(
    (p) => (p.task_state === 'queued' || p.task_state === 'running') && ownRequest?.target_code !== p.code,
  );
  const busy: Busy = useMemo(() => {
    if (ownRequest && isLifecycleRunning(ownRequest)) {
      return {
        request: ownRequest,
        doing: `「${targetNameOf(ownRequest, nameOfCode, tl)}」正在${actionOfKind(ownRequest.kind, tl)}`,
        active: true,
      };
    }
    if (externalBusyItem) {
      return { request: null, doing: `「${externalBusyItem.name}」`, active: true };
    }
    return { request: null, doing: '', active: false };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [ownRequest, externalBusyItem, market.data, installed.data]);

  // 自己的申请到终态：提示一次 + 刷新列表；失败页签展示回滚说明。
  useEffect(() => {
    if (!ownRequest || isLifecycleRunning(ownRequest)) return;
    if (handledRequest.current === ownRequest.id) return;
    handledRequest.current = ownRequest.id;
    refreshPlugins();
    const action = pendingAction;
    const name = targetNameOf(ownRequest, nameOfCode, tl);
    if (ownRequest.state === 'succeeded') {
      if (ownRequest.kind === 'plugin_upgrade_batch') {
        const done = ownRequest.items?.filter((i) => i.state === 'succeeded').length ?? 0;
        const failed = batchFailedNames(ownRequest).map((c) => nameOfCode(c) ?? c);
        if (failed.length > 0) {
          Toast.warning(
            t('success.batchPartial', {
              done,
              failed: failed.length,
              names: failed.join('、'),
            }),
          );
        } else {
          Toast.success(t('success.batchDone', { count: done }));
        }
      } else if (ownRequest.kind === 'plugin_install') {
        Toast.success(t('success.installed', { name }));
      } else if (ownRequest.kind === 'plugin_uninstall') {
        Toast.success(
          action?.purge || ownRequest.purge_data
            ? t('success.uninstalledPurged', { name })
            : t('success.uninstalled', { name }),
        );
      } else {
        Toast.success(t('success.upgraded', { name, version: ownRequest.to_version ?? '' }));
      }
    } else if (ownRequest.state === 'cancelled') {
      Toast.info(tl('cancelled'));
    }
    // requestId 不清：终态横幅（回滚/未恢复/已取消）要继续显示到下一次操作。
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [ownRequest?.state, ownRequest?.id]);

  const permInstall = useCan('system:plugin:install');
  const permUpgrade = useCan('system:plugin:upgrade');
  const permUninstall = useCan('system:plugin:uninstall');
  const permUpload = useCan('system:plugin:upload');

  const opsEnabled = overview.data?.package_ops_enabled === true;
  const compiled = overview.data?.compiled === true;
  const canOperate = isSuper && opsEnabled && !compiled;
  const canInstall = canOperate && permInstall;
  const canUpgrade = canOperate && permUpgrade;
  const canUninstall = canOperate && permUninstall;
  const canUpload = canOperate && permUpload;

  const handleSubmitError = (error: unknown) => {
    if (isBusyError(error)) {
      Toast.error(tl('busy'));
      refreshPlugins();
      return;
    }
    if (isPermissionRevoked(error)) {
      showPermissionRevoked(tl);
      return;
    }
    if (
      isAdminApiError(error) &&
      (error.code === API_ERROR_CODES.PLUGIN_PACKAGE_INVALID ||
        error.code === API_ERROR_CODES.PLUGIN_SIGNATURE_INVALID)
    ) {
      Toast.error(t('uploadInvalid'));
      return;
    }
    Toast.error(adminErrorMessage(error, t('loadFailed')));
  };

  const doInstall = useMutation({
    mutationFn: (code: string) => installPlugin(code),
    onSuccess: (accepted) => setRequestId(accepted.request_id),
    onError: handleSubmitError,
  });
  const doUpgrade = useMutation({
    mutationFn: (code: string) => upgradePlugin(code),
    onSuccess: (accepted) => setRequestId(accepted.request_id),
    onError: handleSubmitError,
  });
  const doUpgradeAll = useMutation({
    mutationFn: upgradeAllPlugins,
    onSuccess: (accepted) => setRequestId(accepted.request_id),
    onError: handleSubmitError,
  });
  const doUninstall = useMutation({
    mutationFn: ({ code, purge }: { code: string; purge: boolean }) =>
      uninstallPlugin(code, purge),
    onSuccess: (accepted) => setRequestId(accepted.request_id),
    onError: handleSubmitError,
  });
  const doUpload = useMutation({
    mutationFn: (file: File) => uploadPluginPackage(file, 'install'),
    onSuccess: (accepted) => setRequestId(accepted.request_id),
    onError: handleSubmitError,
  });
  const uploadInput = useRef<HTMLInputElement | null>(null);

  const startAction = (kind: LifecycleRequest['kind'], code: string, purge = false) => {
    if (busy.active) {
      setBlockedTarget(nameOfCode(code) ?? code);
      return;
    }
    setPendingAction({ kind, code, purge: purge });
    if (kind === 'plugin_install') doInstall.mutate(code);
    else if (kind === 'plugin_uninstall') doUninstall.mutate({ code, purge });
    else doUpgrade.mutate(code);
  };

  const detail = useQuery({
    queryKey: [...PLUGINS_QUERY_KEY, 'detail', detailCode],
    queryFn: () => getPlugin(detailCode ?? ''),
    enabled: detailCode !== null,
  });
  const detailItem = detail.data ?? null;
  const detailMarket = (market.data?.items ?? []).find((p) => p.code === detailCode) ?? null;

  // 市场接口挂掉时，已安装列表还能用：拿 /plugins/installed 合成只读卡片。
  const items = useMemo<PluginMarketItem[]>(
    () =>
      market.data?.items ??
      (market.isError
        ? (installed.data?.items ?? []).map((p) => ({
            ...p,
            latest_version: p.version,
            installed: true,
            upgrade_available: false,
          }))
        : []),
    [market.data, market.isError, installed.data],
  );
  const marketDown = market.isError && items.length > 0;
  const upgradeableCount = items.filter((p) => p.installed && p.upgrade_available).length;
  const filtered = useMemo(() => {
    let list = items;
    if (tab === 'installed') list = list.filter((p) => p.installed);
    if (tab === 'upgradeable') list = list.filter((p) => p.installed && p.upgrade_available);
    const kw = keyword.trim().toLowerCase();
    if (kw) {
      list = list.filter(
        (p) =>
          p.name.toLowerCase().includes(kw) ||
          (p.description ?? '').toLowerCase().includes(kw),
      );
    }
    return list;
  }, [items, tab, keyword]);

  const isListPending = market.isPending && installed.isPending;
  const isListPaused = market.isPaused || installed.isPaused || overview.isPaused;
  const isListError = (market.isError && installed.isError) || overview.isError;
  const listError = market.error ?? installed.error ?? overview.error;

  /** 主按钮：未安装→安装，可升级→升级，已装→打开。忙碌时禁按。 */
  const cardAction = (item: PluginMarketItem) => {
    if (!item.installed && canInstall) {
      return { label: t('actions.install'), run: () => startAction('plugin_install', item.code), primary: true };
    }
    if (item.installed && item.upgrade_available && canUpgrade) {
      return { label: t('actions.upgrade'), run: () => startAction('plugin_upgrade', item.code), primary: true };
    }
    if (item.installed) {
      const url = firstMenuUrl(item.provides);
      if (url) {
        return { label: t('actions.open'), run: () => { window.location.href = url; }, primary: false };
      }
    }
    return null;
  };

  const card = (item: PluginMarketItem) => {
    const action = cardAction(item);
    const itemBusy = item.task_state === 'queued' || item.task_state === 'running';
    const disabled = busy.active && !itemBusy;
    // 卡片本人在被处理：灰按钮旁写明原因，不只灰不说话。
    const busyReason = itemBusy
      ? ownRequest &&
        isLifecycleRunning(ownRequest) &&
        (ownRequest.target_code === item.code ||
          (ownRequest.items ?? []).some((i) => i.code === item.code))
        ? `${busy.doing}，大约 ${ownRequest.progress}%`
        : t('states.busyCard', { name: item.name })
      : null;
    const button = action ? (
      <Button
        theme={action.primary ? 'solid' : 'light'}
        size="small"
        loading={!itemBusy && pendingAction?.code === item.code && (doInstall.isPending || doUpgrade.isPending || doUninstall.isPending)}
        disabled={disabled || itemBusy}
        onClick={(e) => {
          e.stopPropagation();
          action.run();
        }}
      >
        {action.label}
      </Button>
    ) : null;
    return (
      <Card key={item.code} style={{ width: 280 }} data-testid={`plugin-card-${item.code}`}>
        <div
          className="flex flex-col gap-2"
          style={{ cursor: 'pointer' }}
          onClick={() => setDetailCode(item.code)}
        >
          <div className="flex items-start justify-between gap-2">
            <div className="flex items-center gap-2 min-w-0">
              <Typography.Title heading={6} style={{ margin: 0 }} ellipsis={{ showTooltip: true }}>
                {item.name}
              </Typography.Title>
            </div>
            {pluginTag(item, t, tl)}
          </div>
          <Typography.Text type="tertiary" size="small" ellipsis={{ rows: 2, showTooltip: true }}>
            {item.description ?? ''}
          </Typography.Text>
          <div className="flex items-center justify-between">
            <Typography.Text type="secondary" size="small">
              {item.installed && item.upgrade_available
                ? t('versionArrow', { from: item.version, to: item.latest_version })
                : item.version}
            </Typography.Text>
            <div className="flex items-center gap-2" onClick={(e) => e.stopPropagation()}>
              {busyReason ? (
                <Typography.Text type="tertiary" size="small">
                  {busyReason}
                </Typography.Text>
              ) : null}
              {disabled ? (
                <Tooltip content={tl('busyTooltip', { doing: busy.doing })}>{button}</Tooltip>
              ) : (
                button
              )}
            </div>
          </div>
        </div>
      </Card>
    );
  };


  const ownRequestBanner = ownRequest && isLifecycleRunning(ownRequest) ? (
    <Banner
      type="info"
      fullMode={false}
      closeIcon={null}
      description={
        ownRequest.kind === 'plugin_upgrade_batch'
          ? `${busy.doing}，${t('progress.batchSummary', {
              done: batchDoneCount(ownRequest),
              total: ownRequest.items?.length ?? 0,
            })}`
          : `${busy.doing}，大约 ${ownRequest.progress}%`
      }
    />
  ) : externalBusyItem ? (
    <Banner
      type="info"
      fullMode={false}
      closeIcon={null}
      description={`${busy.doing}还没做完，做完后再试`}
    />
  ) : null;

  const finishedFailure = ownRequest && !isLifecycleRunning(ownRequest) && ownRequest.state === 'failed' ? ownRequest : null;

  // 失败标题带插件名：批量申请取失败项的名字。
  const failedNameOf = (request: LifecycleRequest): string => {
    if (request.kind === 'plugin_upgrade_batch') {
      return batchFailedNames(request)
        .map((code) => nameOfCode(code) ?? code)
        .join('、');
    }
    return targetNameOf(request, nameOfCode, tl);
  };

  return (
    <div className="flex flex-col gap-4">
      <PageHeader
        title={t('title')}
        subtitle={canOperate ? t('subtitle') : t('subtitleReadonly')}
        extra={
          <div className="flex items-center gap-2">
            {canUpgrade && upgradeableCount > 0 ? (
              <Button
                theme="light"
                onClick={() => {
                  setPendingAction({ kind: 'plugin_upgrade_batch', code: '*', purge: false });
                  doUpgradeAll.mutate();
                }}
                loading={doUpgradeAll.isPending}
                disabled={busy.active}
              >
                {t('actions.upgradeAll', { count: upgradeableCount })}
              </Button>
            ) : null}
            {canUpload ? (
              <>
                <Button icon={<IconUpload />} onClick={() => uploadInput.current?.click()} disabled={busy.active}>
                  {t('actions.upload')}
                </Button>
                <input
                  ref={uploadInput}
                  type="file"
                  accept=".zip"
                  hidden
                  onChange={(e) => {
                    const file = e.target.files?.[0];
                    e.target.value = '';
                    if (file) {
                      setPendingAction({ kind: 'plugin_install', code: file.name, purge: false });
                      doUpload.mutate(file);
                    }
                  }}
                />
              </>
            ) : null}
          </div>
        }
      />

      {compiled ? (
        <Banner type="info" fullMode={false} description={t('compiledNotice')} closeIcon={null} />
      ) : null}
      {!compiled && !opsEnabled ? (
        <Banner type="info" fullMode={false} description={t('opsDisabledNotice')} closeIcon={null} />
      ) : null}
      {poll.unreachable ? (
        <Banner type="info" fullMode={false} description={tl('unreachable')} closeIcon={null} />
      ) : null}
      {marketDown ? (
        <Banner type="info" fullMode={false} description={t('marketDown')} closeIcon={null} />
      ) : null}
      {poll.error ? (
        <ErrorState kind="error" error={poll.error} onRetry={poll.retry} showHome={false} />
      ) : null}
      {finishedFailure ? (
        <Banner
          type={finishedFailure.rolled_back ? 'warning' : 'danger'}
          fullMode={false}
          title={
            finishedFailure.rolled_back
              ? t('failed.rolledBackTitle', { name: failedNameOf(finishedFailure) })
              : t('failed.notRecoveredTitle', { name: failedNameOf(finishedFailure) })
          }
          description={
            <div className="flex flex-col gap-1">
              {finishedFailure.rolled_back ? (
                <span>{t('failed.rolledBackDesc')}</span>
              ) : null}
              {finishedFailure.error_message ? (
                <span>{finishedFailure.error_message}</span>
              ) : null}
            </div>
          }
          closeIcon={null}
        />
      ) : null}
      {ownRequest && ownRequest.state === 'cancelled' ? (
        <Banner type="info" fullMode={false} description={tl('cancelled')} closeIcon={null} />
      ) : null}
      {ownRequestBanner}

      <Tabs
        type="line"
        activeKey={tab}
        onChange={(key) => setTab(key as PluginTab)}
        tabBarExtraContent={
          <Input
            prefix={<IconSearch />}
            placeholder={t('searchPlaceholder')}
            value={keyword}
            onChange={setKeyword}
            showClear
            className="w-64"
          />
        }
      >
        <Tabs.TabPane tab={t('tabs.all')} itemKey="all" />
        <Tabs.TabPane
          tab={
            items.filter((p) => p.installed).length > 0
              ? t('tabs.installed', { count: items.filter((p) => p.installed).length })
              : t('tabs.installedEmpty')
          }
          itemKey="installed"
        />
        {upgradeableCount > 0 ? (
          <Tabs.TabPane tab={t('tabs.upgradeable', { count: upgradeableCount })} itemKey="upgradeable" />
        ) : null}
      </Tabs>

      {isListPaused || isListError ? (
        <Card>
          <ErrorState
            kind="error"
            title={t('loadFailed')}
            error={listError}
            paused={isListPaused}
            onRetry={() => refreshPlugins()}
            showHome={false}
          />
        </Card>
      ) : isListPending ? (
        <div className="flex flex-wrap gap-4">
          {[0, 1, 2].map((i) => (
            <Card key={i} style={{ width: 280 }}>
              <Skeleton placeholder={<Skeleton.Paragraph rows={3} />} loading />
            </Card>
          ))}
        </div>
      ) : filtered.length === 0 ? (
        <Card>
          {keyword || tab !== 'installed' ? (
            keyword ? (
              <Empty image={<IllustrationNoResult />} title={t('empty.noResult')} description="">
                <Button size="small" onClick={() => setKeyword('')}>
                  {t('clearSearch')}
                </Button>
              </Empty>
            ) : (
              <Empty image={<IllustrationNoResult />} title={t('empty.noResult')} description="" />
            )
          ) : canInstall ? (
            <Empty title={t('empty.installed')} description={t('empty.installedDesc')}>
              <Button size="small" theme="solid" onClick={() => setTab('all')}>
                {t('empty.goMarket')}
              </Button>
            </Empty>
          ) : (
            <Empty
              image={<IllustrationNoAccess />}
              title={t('empty.installed')}
              description={t('empty.installedReadonlyDesc')}
            />
          )}
        </Card>
      ) : (
        <div className="flex flex-wrap gap-4">{filtered.map(card)}</div>
      )}

      <SideSheet
        visible={detailCode !== null}
        title={t('detail.title')}
        width={420}
        onCancel={() => setDetailCode(null)}
      >
        {detail.isPending ? (
          <Skeleton placeholder={<Skeleton.Paragraph rows={4} />} loading />
        ) : detail.isError ? (
          <ErrorState kind="error" error={detail.error} onRetry={() => void detail.refetch()} showHome={false} />
        ) : detailItem ? (
          <div className="flex flex-col gap-4">
            <div>
              <Typography.Title heading={5} style={{ margin: 0 }}>
                {detailItem.name}
              </Typography.Title>
              <Typography.Text type="secondary" size="small">
                {t('detail.version')} {detailItem.version}
                {detailMarket?.upgrade_available
                  ? `（${t('detail.latestVersion', { version: detailMarket.latest_version })}）`
                  : ''}
              </Typography.Text>
            </div>
            {detailItem.description ? (
              <Typography.Paragraph>{detailItem.description}</Typography.Paragraph>
            ) : null}
            {menuNames(detailItem.provides).length > 0 ? (
              <div>
                <Typography.Text strong>{t('detail.provides')}</Typography.Text>
                <Typography.Text type="secondary">
                  {menuNames(detailItem.provides).join('、')}
                </Typography.Text>
              </div>
            ) : null}
            <div className="flex gap-2">
              {detailMarket?.upgrade_available && canUpgrade ? (
                <Button
                  theme="solid"
                  onClick={() => {
                    setDetailCode(null);
                    startAction('plugin_upgrade', detailItem.code);
                  }}
                  disabled={busy.active}
                >
                  {t('actions.upgrade')}
                </Button>
              ) : null}
              {canUninstall && (detailMarket?.installed ?? detailItem.state === 'installed') ? (
                <Dropdown
                  trigger="click"
                  visible={detailMoreOpen}
                  onVisibleChange={setDetailMoreOpen}
                  render={
                    <Dropdown.Menu>
                      <Dropdown.Item
                        type="danger"
                        onClick={() => {
                          setDetailMoreOpen(false);
                          setDetailCode(null);
                          setPurge(false);
                          setUninstallTarget({ code: detailItem.code, name: detailItem.name });
                        }}
                      >
                        {t('detail.uninstall')}
                      </Dropdown.Item>
                    </Dropdown.Menu>
                  }
                >
                  <Button icon={<IconMore />} aria-label={t('detail.more')} />
                </Dropdown>
              ) : null}
            </div>
          </div>
        ) : null}
      </SideSheet>

      <Modal
        visible={uninstallTarget !== null}
        title={
          <span className="flex items-center gap-2">
            <IconAlertTriangle style={{ color: 'var(--semi-color-danger)' }} />
            {t('uninstall.title', { name: uninstallTarget?.name ?? '' })}
          </span>
        }
        okText={purge ? t('uninstall.confirmPurge') : t('uninstall.confirm')}
        cancelText={t('uninstall.cancel')}
        okType="danger"
        confirmLoading={doUninstall.isPending}
        onOk={() => {
          if (uninstallTarget) {
            setPendingAction({ kind: 'plugin_uninstall', code: uninstallTarget.code, purge });
            doUninstall.mutate(
              { code: uninstallTarget.code, purge: purge },
              { onSuccess: (accepted) => { setRequestId(accepted.request_id); setUninstallTarget(null); } },
            );
          }
        }}
        onCancel={() => setUninstallTarget(null)}
      >
        <div className="flex flex-col gap-3">
          <Typography.Paragraph style={{ margin: 0 }}>
            {t('uninstall.content', { name: uninstallTarget?.name ?? '' })}
          </Typography.Paragraph>
          <Checkbox checked={purge} onChange={(e) => setPurge(e.checked === true)}>
            {t('uninstall.purge')}
          </Checkbox>
        </div>
      </Modal>

      <Modal
        visible={blockedTarget !== null}
        title={t('uninstall.blockedTitle', { name: blockedTarget ?? '' })}
        okText={t('uninstall.blockedOk')}
        hasCancel={false}
        onOk={() => setBlockedTarget(null)}
        onCancel={() => setBlockedTarget(null)}
      >
        <Typography.Paragraph style={{ margin: 0 }}>
          {tl('busyTooltip', { doing: busy.doing })}
        </Typography.Paragraph>
      </Modal>
    </div>
  );
}
