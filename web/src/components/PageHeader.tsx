import type { ReactNode } from 'react';

import { Typography } from '@douyinfe/semi-ui';

export interface PageHeaderProps {
  title: ReactNode;
  subtitle?: ReactNode;
  extra?: ReactNode;
}

export function PageHeader({ title, subtitle, extra }: PageHeaderProps) {
  return (
    <div className="flex flex-wrap items-start justify-between gap-3">
      <div className="min-w-0">
        <Typography.Title heading={4} style={{ margin: 0 }}>
          {title}
        </Typography.Title>
        {subtitle ? (
          <Typography.Text type="secondary">{subtitle}</Typography.Text>
        ) : null}
      </div>
      {extra ? <div className="flex items-center gap-2">{extra}</div> : null}
    </div>
  );
}
