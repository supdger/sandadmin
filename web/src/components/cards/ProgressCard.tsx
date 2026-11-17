import type { CSSProperties, ReactNode } from 'react';

import { Card, Progress, Typography } from '@douyinfe/semi-ui';

export interface ProgressCardProps {
  title: ReactNode;
  /** 0–100，越界收敛到端点。 */
  percent: number;
  description?: ReactNode;
  /** 进度条颜色，默认主题主色。 */
  color?: string;
  /** 百分比文字；默认显示。 */
  showInfo?: boolean;
  /** 头部右侧操作位。 */
  extra?: ReactNode;
  className?: string;
  style?: CSSProperties;
}

export function ProgressCard({
  title,
  percent,
  description,
  color,
  showInfo = true,
  extra,
  className,
  style,
}: ProgressCardProps) {
  const clamped = Math.min(100, Math.max(0, percent));
  return (
    <Card title={title} headerExtraContent={extra} className={className} style={style}>
      <div className="flex flex-col gap-2">
        <Progress
          percent={clamped}
          stroke={color}
          showInfo={showInfo}
          strokeLinecap="round"
          aria-label={typeof title === 'string' ? title : undefined}
        />
        {description ? (
          <Typography.Text type="tertiary" size="small">
            {description}
          </Typography.Text>
        ) : null}
      </div>
    </Card>
  );
}
