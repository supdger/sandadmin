import type { CSSProperties, ReactNode } from 'react';

import { Button, Card, Typography } from '@douyinfe/semi-ui';
import { useTranslation } from 'react-i18next';

export interface DataListRow {
  key: string;
  title: ReactNode;
  description?: ReactNode;
  /** 行右侧的值或标签。 */
  extra?: ReactNode;
}

export interface DataListCardProps {
  title: ReactNode;
  rows: DataListRow[];
  /** 头部右侧操作位。 */
  extra?: ReactNode;
  /** 底部「查看全部」入口文案，默认「查看全部」；配合 onMore 生效。 */
  moreText?: ReactNode;
  /** 给了才渲染底部链接。 */
  onMore?: () => void;
  /** 展示行数上限，截断部分交给「更多」入口承接。 */
  maxRows?: number;
  className?: string;
  style?: CSSProperties;
}

export function DataListCard({
  title,
  rows,
  extra,
  moreText,
  onMore,
  maxRows,
  className,
  style,
}: DataListCardProps) {
  const { t } = useTranslation('feChart');
  const shown = maxRows !== undefined ? rows.slice(0, maxRows) : rows;
  return (
    <Card title={title} headerExtraContent={extra} className={className} style={style}>
      {shown.length === 0 ? (
        <div className="flex items-center justify-center py-6">
          <Typography.Text type="tertiary">{t('empty.title', { ns: 'common' })}</Typography.Text>
        </div>
      ) : (
        <div>
          {shown.map((row, index) => (
            <div
              key={row.key}
              className="flex items-center justify-between gap-3 py-2"
              style={
                index < shown.length - 1
                  ? { borderBottom: '1px solid var(--semi-color-border)' }
                  : undefined
              }
            >
              <div className="flex min-w-0 flex-col">
                <Typography.Text ellipsis={{ showTooltip: true }}>
                  {row.title}
                </Typography.Text>
                {row.description ? (
                  <Typography.Text type="tertiary" size="small">
                    {row.description}
                  </Typography.Text>
                ) : null}
              </div>
              {row.extra ? <div className="shrink-0">{row.extra}</div> : null}
            </div>
          ))}
        </div>
      )}
      {onMore ? (
        <div className="pt-2 text-center">
          <Button theme="borderless" size="small" onClick={onMore}>
            {moreText ?? t('more')}
          </Button>
        </div>
      ) : null}
    </Card>
  );
}
