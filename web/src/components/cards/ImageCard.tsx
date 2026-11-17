import type { CSSProperties, ReactNode } from 'react';

import { Card, Typography } from '@douyinfe/semi-ui';

export interface ImageCardProps {
  /** 封面图地址。 */
  cover: string;
  /** 封面 alt；纯装饰封面传空串即可。 */
  coverAlt?: string;
  /** 封面展示高度 px，默认 160。 */
  coverHeight?: number;
  title: ReactNode;
  description?: ReactNode;
  /** 头部右侧操作位。 */
  extra?: ReactNode;
  /** 底部操作位（Semi Card actions）。 */
  actions?: ReactNode[];
  className?: string;
  style?: CSSProperties;
}

export function ImageCard({
  cover,
  coverAlt = '',
  coverHeight = 160,
  title,
  description,
  extra,
  actions,
  className,
  style,
}: ImageCardProps) {
  return (
    <Card
      className={className}
      style={style}
      cover={
        <img
          src={cover}
          alt={coverAlt}
          style={{ width: '100%', height: coverHeight, objectFit: 'cover' }}
        />
      }
      title={title}
      headerExtraContent={extra}
      actions={actions}
    >
      {description ? (
        <Typography.Text type="secondary" size="small">
          {description}
        </Typography.Text>
      ) : null}
    </Card>
  );
}
