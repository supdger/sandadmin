import { useRef, useState } from 'react';

import {
  Banner,
  Button,
  Card,
  List,
  Progress,
  Skeleton,
  Steps,
  Tag,
  Toast,
  Typography,
} from '@douyinfe/semi-ui';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import { API_ERROR_CODES } from '@/api/errorCodes';
import type { LifecycleRequest } from '@/api/v1/lifecycle';
import {
  checkUpdate,
  getUpdateStatus,
  startUpdate,
  uploadUpdatePackage,
} from '@/api/v1/systemUpdate';
import { AdminCopyButton } from '@/components/AdminErrorNotice';
import { ErrorState } from '@/components/ErrorState';
import { PageHeader } from '@/components/PageHeader';
import { useCan } from '@/hooks/useCan';
import { useLifecycleRequest, isLifecycleRunning } from '@/hooks/useLifecycleRequest';
import { useMe } from '@/hooks/useMe';
import { AdminApiError, isAdminApiError } from '@/lib/adminClient';
import { adminErrorMessage } from '@/lib/adminError';
import { isBusyError, isPermissionRevoked, showPermissionRevoked } from '../lifecycleShared';

/** 升级卡片里的时间用「M月D日 HH:mm」，不走列表页的 YYYY-MM-DD 格式。 */
function formatShortTime(iso: string): string {
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) {
    return '';
  }
  const pad = (n: number) => (n < 10 ? `0${n}` : String(n));
  return `${date.getMonth() + 1}月${date.getDate()}日 ${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/** 已恢复的描述按「有没有原版本号 / 有没有失败时间」挑白话模板。 */
function rolledBackDescKey(request: LifecycleRequest): string {
  const hasVersion = Boolean(request.from_version);
  const hasTime = Boolean(request.finish_time);
  if (hasVersion && hasTime) return 'rolledBackDescAt';
  if (hasVersion) return 'rolledBackDesc';
  if (hasTime) return 'rolledBackDescAtNoVersion';
  return 'rolledBackDescNoVersion';
}

/** 系统升级固定五阶段，顺序即展示顺序（stage_key → 文案）。 */
const STAGE_KEYS = ['check', 'backup', 'download', 'switch', 'verify'] as const;

function stageIndex(request: LifecycleRequest | null): number {
  const index = STAGE_KEYS.indexOf(request?.stage_key ?? 'check');
  return index < 0 ? 0 : index;
}

/** 升级进度视图：五步步骤条 + 阶段白话 + 进度条；无取消按钮。 */
function UpgradeStepsView({ request }: { request: LifecycleRequest }) {
  const { t } = useTranslation('lifecycle');
  const { t: tu } = useTranslation('systemUpdate');
  const current = stageIndex(request);
  return (
    <div className="flex flex-col gap-4" data-testid="upgrade-progress">
      <div className="flex items-center gap-2">
        <Tag color={request.state === 'queued' ? 'grey' : 'blue'} size="small">
          {t(`states.${request.state}`)}
        </Tag>
        <Typography.Text type="secondary" size="small">
          {request.stage ?? t('stages.defaultStage')}
        </Typography.Text>
      </div>
      <Steps current={current} direction="vertical" size="small">
        {STAGE_KEYS.map((key, index) => (
          <Steps.Step
            key={key}
            title={tu(
              index < current
                ? `upgradeStagesDone.${key}`
                : index === current
                  ? `upgradeStagesRunning.${key}`
                  : `upgradeStages.${key}`,
            )}
          />
        ))}
      </Steps>
      <Progress
        // 还在跑就不显示 100%：进度到顶会让人误以为已升级完。
        percent={isLifecycleRunning(request) ? Math.min(request.progress, 90) : request.progress}
        showInfo
        motion={false}
        format={(percent) => `${percent}%`}
        aria-label="upgrade-progress"
      />
    </div>
  );
}

/** 结果展示：成功 / 已恢复 / 未能恢复 / 已取消。 */
function UpgradeResult({
  request,
  canRetry,
  onAgain,
}: {
  request: LifecycleRequest;
  /** 网页不能升级时不给「再试一次」，失败描述也不提再试。 */
  canRetry: boolean;
  onAgain: () => void;
}) {
  const { t } = useTranslation('systemUpdate');
  const { t: tl } = useTranslation('lifecycle');

  if (request.state === 'cancelled') {
    return <Banner type="info" fullMode={false} description={tl('cancelled')} closeIcon={null} />;
  }
  if (request.state === 'succeeded') {
    return (
      <div className="flex flex-col gap-3">
        <Banner
          type="success"
          fullMode={false}
          description={t('success', { version: request.to_version ?? '' })}
          closeIcon={null}
        />
        <Typography.Text type="secondary">{t('successRefresh')}</Typography.Text>
        <div>
          <Button theme="solid" type="primary" onClick={() => window.location.reload()}>
            {t('refreshPage')}
          </Button>
        </div>
      </div>
    );
  }
  if (request.state === 'failed') {
    const problem = new AdminApiError({
      code: request.error_code ?? 'INTERNAL_ERROR',
      message: request.error_message ?? request.id,
      httpStatus: 0,
      method: 'GET',
      url: `/lifecycle-requests/${request.id}`,
      system: true,
    });
    if (request.rolled_back) {
      return (
        <div className="flex flex-col gap-3">
          <Banner
            type="warning"
            fullMode={false}
            title={t('rolledBackTitle')}
            description={
              <div className="flex flex-col gap-1">
                <span>
                  {t(rolledBackDescKey(request), {
                    version: request.from_version ?? '',
                    time: request.finish_time
                      ? formatShortTime(request.finish_time)
                      : '',
                  })}
                </span>
                {request.error_message ? (
                  <span>{t('failureReason', { reason: request.error_message })}</span>
                ) : null}
              </div>
            }
            closeIcon={null}
          />
          <div className="flex items-center gap-2">
            {canRetry ? (
              <Button theme="solid" type="primary" onClick={onAgain}>
                {t('upgradeAgain')}
              </Button>
            ) : null}
            <AdminCopyButton error={problem} />
          </div>
        </div>
      );
    }
    return (
      <div className="flex flex-col gap-3" data-testid="upgrade-failed">
        <Banner
          type="danger"
          fullMode={false}
          title={t('notRecoveredTitle')}
          description={
            <div className="flex flex-col gap-1">
              <span>{t(canRetry ? 'notRecoveredDesc' : 'notRecoveredDescReadonly')}</span>
              {request.error_message ? (
                <span>{t('failureReason', { reason: request.error_message })}</span>
              ) : null}
            </div>
          }
          closeIcon={null}
        />
        <div className="flex flex-wrap items-center gap-2">
          {canRetry ? (
            <Button theme="solid" type="primary" onClick={onAgain}>
              {t('upgradeAgain')}
            </Button>
          ) : null}
          <Button
            onClick={() =>
              Toast.success(t('restoredHint', { version: request.from_version ?? '' }))
            }
          >
            {t('restoreVersion')}
          </Button>
          <AdminCopyButton error={problem} />
        </div>
      </div>
    );
  }
  return null;
}

/** 系统更新页：版本卡 + 五步升级进度；编译版与关闭态只读说明。 */
export function SystemUpdatePage() {
  const { t } = useTranslation('systemUpdate');
  const { t: tl } = useTranslation('lifecycle');
  const queryClient = useQueryClient();
  const { data: me } = useMe();
  const isSuper = me?.is_super === true;
  const permStart = useCan('system:update:start');
  const permUpload = useCan('system:update:upload');
  const permCheck = useCan('system:update:check');
  const canStart = isSuper && permStart;
  const canUpload = isSuper && permUpload;
  const canCheck = isSuper && permCheck;

  const statusQuery = useQuery({
    queryKey: ['system', 'update', 'status'],
    queryFn: getUpdateStatus,
  });
  const status = statusQuery.data;
  // 与提示条同一个判定：拿不到状态时先保留原副标题，拿到后按能力换。
  const pageSubtitle =
    status && !(status.package_ops_enabled === true && status.compiled !== true)
      ? t('subtitleReadonly')
      : t('subtitle');

  const [requestId, setRequestId] = useState<string | null>(null);
  const running = status?.running_request ?? null;
  const failed = status?.last_failed_request ?? null;
  const activeId = requestId ?? (running && isLifecycleRunning(running) ? running.id : null);
  const poll = useLifecycleRequest(activeId, { seed: running });
  const request = poll.request ?? running ?? (requestId ? null : failed);
  const uploadInput = useRef<HTMLInputElement | null>(null);

  const refreshStatus = () =>
    queryClient.invalidateQueries({ queryKey: ['system', 'update', 'status'] });

  const handleSubmitError = (error: unknown) => {
    if (isBusyError(error)) {
      Toast.error(tl('busy'));
      void refreshStatus();
      return;
    }
    if (isPermissionRevoked(error)) {
      showPermissionRevoked(tl);
      return;
    }
    if (
      isAdminApiError(error) &&
      (error.code === API_ERROR_CODES.UPDATE_SIGNATURE_INVALID ||
        error.code === API_ERROR_CODES.UPDATE_PACKAGE_INVALID ||
        error.code === API_ERROR_CODES.PLUGIN_SIGNATURE_INVALID ||
        error.code === API_ERROR_CODES.PLUGIN_PACKAGE_INVALID)
    ) {
      Toast.error(t('uploadInvalid'));
      return;
    }
    Toast.error(adminErrorMessage(error, t('loadFailed')));
  };

  const start = useMutation({
    mutationFn: () => startUpdate({ target_version: status?.latest_version ?? undefined }),
    onSuccess: (accepted) => setRequestId(accepted.request_id),
    onError: handleSubmitError,
  });

  const check = useMutation({
    mutationFn: checkUpdate,
    onSuccess: () => void refreshStatus(),
    onError: handleSubmitError,
  });

  const upload = useMutation({
    mutationFn: (file: File) => uploadUpdatePackage(file),
    onSuccess: (accepted) => setRequestId(accepted.request_id),
    onError: handleSubmitError,
  });

  if ((statusQuery.isPaused || statusQuery.isError) && !status) {
    return (
      <div className="flex flex-col gap-4">
        <PageHeader title={t('title')} subtitle={pageSubtitle} />
        <Card style={{ maxWidth: 720 }}>
          <ErrorState
            kind="error"
            title={t('loadFailed')}
            error={statusQuery.error}
            paused={statusQuery.isPaused}
            onRetry={() => void statusQuery.refetch()}
            showHome={false}
          />
        </Card>
      </div>
    );
  }

  if (statusQuery.isPending) {
    return (
      <div className="flex flex-col gap-4">
        <PageHeader title={t('title')} subtitle={pageSubtitle} />
        <Card style={{ maxWidth: 720 }}>
          <Skeleton placeholder={<Skeleton.Paragraph rows={6} />} loading />
        </Card>
      </div>
    );
  }

  const opsEnabled = status?.package_ops_enabled === true;
  const compiled = status?.compiled === true;
  const showProgress = request !== null && isLifecycleRunning(request);
  const showResult = request !== null && !isLifecycleRunning(request);

  return (
    <div className="flex flex-col gap-4">
      <PageHeader title={t('title')} subtitle={pageSubtitle} />

      {compiled ? (
        <Banner type="info" fullMode={false} description={t('compiledNotice')} closeIcon={null} />
      ) : null}
      {!compiled && !opsEnabled ? (
        <Banner type="info" fullMode={false} description={t('opsDisabledNotice')} closeIcon={null} />
      ) : null}

      <Card style={{ maxWidth: 720 }}>
        {poll.unreachable ? (
          <Banner type="info" fullMode={false} description={tl('unreachable')} closeIcon={null} />
        ) : null}
        {poll.switching ? (
          <Banner type="info" fullMode={false} description={tl('switching')} closeIcon={null} />
        ) : null}
        {poll.error ? (
          <ErrorState
            kind="error"
            title={t('loadFailed')}
            error={poll.error}
            onRetry={poll.retry}
            showHome={false}
          />
        ) : showProgress && request ? (
          <UpgradeStepsView request={request} />
        ) : showResult && request ? (
          <div className="flex flex-col gap-4">
            {/* 失败（含已恢复）卡片也挂版本标题，与版本卡分支对齐。 */}
            {request.state === 'failed' ? (
              <Typography.Title heading={5} style={{ margin: 0 }}>
                {t('currentVersion', { version: status?.current_version ?? '' })}
              </Typography.Title>
            ) : null}
            <UpgradeResult
              request={request}
              canRetry={opsEnabled && !compiled}
              onAgain={() => {
                setRequestId(null);
                start.mutate();
              }}
            />
          </div>
        ) : (
          <div className="flex flex-col gap-4">
            <Typography.Title heading={5} style={{ margin: 0 }}>
              {t('currentVersion', { version: status?.current_version ?? '' })}
            </Typography.Title>
            {status?.update_available && status.latest_version ? (
              <Typography.Text>
                {opsEnabled && !compiled
                  ? t('canUpgradeTo', { version: status.latest_version })
                  : t('newVersionReadonly', { version: status.latest_version })}
              </Typography.Text>
            ) : (
              <div className="flex flex-col gap-1">
                <Tag color="green">{t('latestTag', { version: status?.current_version ?? '' })}</Tag>
                <Typography.Text type="secondary" size="small">
                  {t('latestTip')}
                </Typography.Text>
              </div>
            )}

            {(status?.changes.length ?? 0) > 0 ? (
              <div>
                <Typography.Text strong>{t('changesTitle')}</Typography.Text>
                <List
                  size="small"
                  dataSource={status?.changes ?? []}
                  renderItem={(item) => <List.Item>{item}</List.Item>}
                />
              </div>
            ) : null}

            {(status?.bundled_plugins.length ?? 0) > 0 ? (
              <div>
                <Typography.Text strong>{t('bundledTitle')}</Typography.Text>
                <Typography.Text type="secondary">
                  {(status?.bundled_plugins ?? []).map((p) => p.name).join('、')}
                </Typography.Text>
              </div>
            ) : null}

            {opsEnabled && !compiled ? (
              <div className="flex items-center gap-3">
                {status?.update_available && status.latest_version && canStart ? (
                  <Button
                    theme="solid"
                    type="primary"
                    loading={start.isPending}
                    onClick={() => start.mutate()}
                  >
                    {t('upgradeTo', { version: status.latest_version })}
                  </Button>
                ) : null}
                {canUpload ? (
                  <>
                    <Button onClick={() => uploadInput.current?.click()}>
                      {t('uploadPackage')}
                    </Button>
                    <input
                      ref={uploadInput}
                      type="file"
                      accept=".zip"
                      hidden
                      onChange={(e) => {
                        const file = e.target.files?.[0];
                        e.target.value = '';
                        if (file) upload.mutate(file);
                      }}
                    />
                  </>
                ) : null}
                {canCheck && !status?.update_available ? (
                  <Button
                    theme="borderless"
                    loading={check.isPending}
                    onClick={() => check.mutate()}
                  >
                    {t('checkAgain')}
                  </Button>
                ) : null}
              </div>
            ) : null}
          </div>
        )}
      </Card>
    </div>
  );
}
