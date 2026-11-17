import type { CSSProperties, ReactNode } from 'react';

import { Card, Timeline, Typography } from '@douyinfe/semi-ui';
import { useTranslation } from 'react-i18next';

export interface TimelineListEntry {
  key: string;
  /** 轴旁的时间文案（`*_time` 文本原样传入）。 */
  time?: ReactNode;
  title: ReactNode;
  description?: ReactNode;
  /** 圆点语义色，对应 Semi Timeline.Item 的 type。 */
  tone?: 'default' | 'ongoing' | 'success' | 'warning' | 'error';
}

export interface TimelineListCardProps {
  title: ReactNode;
  entries: TimelineListEntry[];
  /** 头部右侧操作位。 */
  extra?: ReactNode;
  className?: string;
  style?: CSSProperties;
}

export function TimelineListCard({
  title,
  entries,
  extra,
  className,
  style,
}: TimelineListCardProps) {
  const { t } = useTranslation();
  return (
    <Card title={title} headerExtraContent={extra} className={className} style={style}>
      {entries.length === 0 ? (
        <div className="flex items-center justify-center py-6">
          <Typography.Text type="tertiary">{t('empty.title')}</Typography.Text>
        </div>
      ) : (
        <Timeline mode="left">
          {entries.map((entry) => (
            <Timeline.Item key={entry.key} time={entry.time} type={entry.tone ?? 'default'}>
              <div className="flex flex-col">
                <Typography.Text>{entry.title}</Typography.Text>
                {entry.description ? (
                  <Typography.Text type="tertiary" size="small">
                    {entry.description}
                  </Typography.Text>
                ) : null}
              </div>
            </Timeline.Item>
          ))}
        </Timeline>
      )}
    </Card>
  );
}
