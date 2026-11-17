import { useCallback } from 'react';
import type { RefObject } from 'react';

import { IconArrowUp } from '@douyinfe/semi-icons';
import { BackTop, IconButton, Tooltip } from '@douyinfe/semi-ui';
import { useTranslation } from 'react-i18next';

export interface BackToTopProps {
  /** 滚动容器；缺省监听 window。 */
  target?: RefObject<HTMLElement | null> | HTMLElement | null;
  /** 滚动超过该距离（px）才显示，默认 200。 */
  threshold?: number;
}

/**
 * 回到顶部。监听指定滚动容器（默认 window），超过阈值后显示
 * 右下角浮动按钮，点击平滑滚回顶部。
 */
export function BackToTop({ target, threshold = 200 }: BackToTopProps) {
  const { t } = useTranslation('feLayout');

  const resolve = useCallback((): HTMLElement | Window => {
    const el = target && 'current' in target ? target.current : target;
    return el ?? window;
  }, [target]);

  return (
    <BackTop target={resolve} visibilityHeight={threshold} style={{ right: 24, bottom: 24 }}>
      <Tooltip content={t('backToTop.label')} position="left">
        <IconButton
          icon={<IconArrowUp />}
          theme="solid"
          type="primary"
          aria-label={t('backToTop.label')}
        />
      </Tooltip>
    </BackTop>
  );
}
