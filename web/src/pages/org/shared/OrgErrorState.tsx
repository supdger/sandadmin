import type { ReactNode } from 'react';

import { Button, Empty as SemiEmpty } from '@douyinfe/semi-ui';
import {
  IllustrationFailure,
  IllustrationFailureDark,
} from '@douyinfe/semi-illustrations';
import { useTranslation } from 'react-i18next';

import { isAdminSystemError, type AdminApiError } from '@/lib/adminClient';
import { AdminCopyButton } from '@/components/AdminErrorNotice';

export interface OrgErrorStateProps {
  title: string;
  description: string;
  /** admin-v1 抓到的错误；系统错误会多给「复制问题信息」。 */
  error?: unknown;
  onRetry?: () => void;
  /** 额外动作（如「清空筛选条件」）。 */
  extra?: ReactNode;
}

/** 列表接口失败的统一空态：插画 + 重试 +（系统错误时）复制问题信息。 */
export function OrgErrorState({ title, description, error, onRetry, extra }: OrgErrorStateProps) {
  const { t } = useTranslation();
  const systemError: AdminApiError | null = isAdminSystemError(error) ? error : null;

  return (
    <div className="flex items-center justify-center py-16">
      <SemiEmpty
        image={<IllustrationFailure />}
        darkModeImage={<IllustrationFailureDark />}
        title={title}
        description={description}
      >
        <div className="flex items-center justify-center gap-2">
          {systemError ? <AdminCopyButton error={systemError} /> : null}
          {onRetry ? (
            <Button theme="solid" onClick={onRetry}>
              {t('actions.retry')}
            </Button>
          ) : null}
          {extra}
        </div>
      </SemiEmpty>
    </div>
  );
}
