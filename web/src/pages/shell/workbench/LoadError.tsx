import { Button, Typography } from '@douyinfe/semi-ui';
import { useTranslation } from 'react-i18next';

import { isNetworkFailure } from '@/lib/networkFailure';

export interface LoadErrorProps {
  error?: unknown;
  /** 浏览器离线时查询会暂停，这时还没有 error 对象。 */
  paused?: boolean;
  onRetry: () => void;
}

/** 工作台一块内容没加载出来时，说明和重试留在这块里面。 */
export function LoadError({ error, paused, onRetry }: LoadErrorProps) {
  const { t } = useTranslation();
  const { t: tWorkbench } = useTranslation('workbench');
  const offline = paused === true || isNetworkFailure(error);

  return (
    <div className="flex flex-col items-center justify-center gap-2 py-6 text-center">
      <Typography.Text>
        {offline ? t('errors.network.message') : tWorkbench('cards.error')}
      </Typography.Text>
      <Button size="small" onClick={onRetry}>
        {t('actions.retry')}
      </Button>
    </div>
  );
}
