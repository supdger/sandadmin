import type { CSSProperties, ReactNode } from 'react';

import { IconArrowDown, IconArrowUp } from '@douyinfe/semi-icons';
import { Card, Typography } from '@douyinfe/semi-ui';
import { useTranslation } from 'react-i18next';

import { CountTo } from '@/components/common/CountTo';
import { SvgIcon } from '@/components/SvgIcon';

export interface StatsCardProps {
  title: ReactNode;
  value: number;
  unit?: string;
  /** SvgIcon 注册表里的图标名。 */
  icon?: string;
  /** 图标底块主色（CSS 颜色值），默认主题主色。 */
  color?: string;
  /** 环比百分比：正数升、负数降；不传不渲染环比行。 */
  changePercent?: number;
  /** 环比说明文案，默认「较上期」。 */
  changeLabel?: ReactNode;
  description?: ReactNode;
  decimals?: number;
  prefix?: string;
  suffix?: string;
  /** 数字滚动动效，默认开。 */
  animated?: boolean;
  /** 标题行右侧操作位。 */
  extra?: ReactNode;
  className?: string;
  style?: CSSProperties;
}

export function StatsCard({
  title,
  value,
  unit,
  icon,
  color,
  changePercent,
  changeLabel,
  description,
  decimals = 0,
  prefix,
  suffix,
  animated = true,
  extra,
  className,
  style,
}: StatsCardProps) {
  const { t } = useTranslation('feChart');
  const rising = (changePercent ?? 0) >= 0;
  return (
    <Card className={className} style={style}>
      <div className="flex items-start gap-3">
        {icon ? (
          <div
            className="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg"
            style={{ backgroundColor: color ?? 'var(--semi-color-primary)' }}
            aria-hidden="true"
          >
            <SvgIcon name={icon} size="large" style={{ color: 'var(--semi-color-white)' }} />
          </div>
        ) : null}
        <div className="flex min-w-0 flex-1 flex-col gap-1">
          <div className="flex items-start justify-between gap-2">
            <Typography.Text type="secondary" size="small">
              {title}
            </Typography.Text>
            {extra}
          </div>
          <Typography.Title heading={3} style={{ margin: 0 }}>
            <CountTo
              value={value}
              duration={animated ? 1200 : 0}
              decimals={decimals}
              prefix={prefix}
              suffix={suffix}
            />
            {unit ? (
              <Typography.Text type="secondary" size="small" className="ml-1">
                {unit}
              </Typography.Text>
            ) : null}
          </Typography.Title>
          {changePercent !== undefined ? (
            <Typography.Text
              size="small"
              style={{
                color: rising
                  ? 'var(--semi-color-success)'
                  : 'var(--semi-color-danger)',
              }}
            >
              {rising ? (
                <IconArrowUp size="small" aria-label={t('stats.up')} />
              ) : (
                <IconArrowDown size="small" aria-label={t('stats.down')} />
              )}{' '}
              {Math.abs(changePercent)}% {changeLabel ?? t('stats.change')}
            </Typography.Text>
          ) : null}
          {description ? (
            <Typography.Text type="tertiary" size="small">
              {description}
            </Typography.Text>
          ) : null}
        </div>
      </div>
    </Card>
  );
}
