import type { ReactNode } from 'react';

import { Button, Skeleton, Typography } from '@douyinfe/semi-ui';
import { useTranslation } from 'react-i18next';

export interface ChartCardBodyProps {
  pending: boolean;
  error: unknown;
  onRetry: () => void;
  /** 解析后无数据时渲染空态而不是 children。 */
  empty: boolean;
  /** 骨架占位高度，对齐内容高度避免跳动。 */
  height?: number | string;
  children: ReactNode;
}

/** 卡片内容区的加载/失败/空三态；业务错误文案直接透服务端 message。 */
export function ChartCardBody({
  pending,
  error,
  onRetry,
  empty,
  height = 320,
  children,
}: ChartCardBodyProps) {
  const { t } = useTranslation();
  if (pending) {
    return (
      <div style={{ minHeight: 120 }} aria-busy="true">
        <Skeleton loading placeholder={<Skeleton.Paragraph rows={4} style={{ height }} />}>
          <div style={{ height }} />
        </Skeleton>
      </div>
    );
  }
  if (error) {
    // 契约错误与 fetcher 抛出的普通错误都直接透 message。
    const message = error instanceof Error ? error.message : t('errors.system.message');
    return (
      <div
        className="flex flex-col items-center justify-center gap-2"
        style={{ minHeight: 120 }}
      >
        <Typography.Text type="danger">{message}</Typography.Text>
        <Button size="small" onClick={onRetry}>
          {t('actions.retry')}
        </Button>
      </div>
    );
  }
  if (empty) {
    return (
      <div className="flex items-center justify-center" style={{ minHeight: 120 }}>
        <Typography.Text type="tertiary">{t('empty.title')}</Typography.Text>
      </div>
    );
  }
  return <>{children}</>;
}
