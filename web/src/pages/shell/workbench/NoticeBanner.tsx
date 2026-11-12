import { useState } from 'react';

import { Button, Card, Tag, Toast, Typography } from '@douyinfe/semi-ui';
import { IconClose } from '@douyinfe/semi-icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import { dismissNotice, getWorkbenchNotices, type DashboardNotice } from '@/api/v1/dashboard';
import { useCan } from '@/hooks/useCan';
import { adminErrorMessage } from '@/lib/adminError';
import { isNetworkFailure } from '@/lib/networkFailure';
import { listOrEmpty } from './listOrEmpty';
import { LoadError } from './LoadError';

const NOTICES_QUERY_KEY = ['dashboard', 'notices'] as const;

function noticeText(t: (key: string, options?: Record<string, unknown>) => string, notice: DashboardNotice): string {
  const params = notice.params ?? {};
  switch (notice.text_key) {
    case 'pluginUpgrade':
      return t('notices.pluginUpgrade', { count: params.count ?? 0 });
    case 'aiProviderMissing':
      return t('notices.aiProviderMissing');
    default:
      return notice.text_key;
  }
}

function actionLabel(t: (key: string) => string, notice: DashboardNotice): string {
  if (!notice.action) {
    return '';
  }
  switch (notice.action.label_key) {
    case 'goUpgrade':
      return t('notices.actions.goUpgrade');
    case 'goConnect':
      return t('notices.actions.goConnect');
    default:
      return notice.action.label_key;
  }
}

/** 升级类提示：文案或动作跳插件/系统更新的都算，无权用户看不到。 */
function isUpgradeNotice(notice: DashboardNotice): boolean {
  if (notice.text_key === 'pluginUpgrade') return true;
  const link = notice.action?.link ?? '';
  return link.startsWith('/system/update') || link.startsWith('/system/plugin');
}

/**
 * Workbench notice strip: only the first (highest priority) notice shows as
 * a banner; 「先不提醒」 dismisses it server-side for 7 days.
 */

export function NoticeBanner() {
  const { t } = useTranslation('workbench');
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  // 升级类提示只对超管或有 system:update 权限的账号展示（前端再挡一层）。
  const canSeeUpgrade = useCan('system:update:index');
  const [dismissedKey, setDismissedKey] = useState<string | null>(null);

  const notices = useQuery({ queryKey: NOTICES_QUERY_KEY, queryFn: getWorkbenchNotices });
  const dismiss = useMutation({
    mutationFn: (noticeKey: string) => dismissNotice(noticeKey),
    onSuccess: (_d, noticeKey) => {
      setDismissedKey(noticeKey);
      void queryClient.invalidateQueries({ queryKey: NOTICES_QUERY_KEY });
    },
    onError: (e) => Toast.error(adminErrorMessage(e, t('notices.dismissFailed'))),
  });

  if (notices.isPaused || isNetworkFailure(notices.error)) {
    return null;
  }
  if (notices.isError) {
    return (
      <Card>
        <LoadError error={notices.error} paused={notices.isPaused} onRetry={() => void notices.refetch()} />
      </Card>
    );
  }
  if (notices.isPending || !notices.data) {
    return null;
  }
  const visible = listOrEmpty(notices.data.items).filter(
    (n) => n.notice_key !== dismissedKey && (canSeeUpgrade || !isUpgradeNotice(n)),
  );
  if (visible.length === 0) {
    return null;
  }
  const first = visible[0];
  const rest = visible.length - 1;
  const action = first.action;

  return (
    <Card
      style={{
        borderColor:
          first.level === 'warning'
            ? 'var(--semi-color-warning)'
            : 'var(--semi-color-border)',
      }}
    >
      <div className="flex flex-wrap items-center gap-3">
        <Tag color={first.level === 'warning' ? 'orange' : 'blue'} size="small">
          {first.level === 'warning' ? '!' : 'i'}
        </Tag>
        <Typography.Text strong={first.level === 'warning'}>
          {noticeText(t, first)}
        </Typography.Text>
        {action ? (
          <Button size="small" onClick={() => navigate(action.link)}>
            {actionLabel(t, first)}
          </Button>
        ) : null}
        {rest > 0 ? (
          <Typography.Text type="tertiary" size="small">
            {t('notices.more', { count: rest })}
          </Typography.Text>
        ) : null}
        <div className="ml-auto flex items-center gap-2">
          <Button
            size="small"
            theme="borderless"
            icon={<IconClose />}
            aria-label={t('notices.dismissTooltip')}
            onClick={() => dismiss.mutate(first.notice_key)}
          />
        </div>
      </div>
    </Card>
  );
}
