import { useTranslation } from 'react-i18next';

import { useGlobalLoading } from '@/lib/loading';

/**
 * 全局请求加载条：有在途 admin 请求时挂在壳层顶部的细条。
 * 计数来自 request 层事件（见 lib/loading），组件只负责呈现。
 */
export function GlobalLoading() {
  const { t } = useTranslation('feCommon');
  const active = useGlobalLoading();

  if (!active) {
    return null;
  }
  return (
    <div
      role="progressbar"
      aria-label={t('loading.text')}
      className="pointer-events-none fixed left-0 right-0 top-0 z-[1000] h-0.5 animate-pulse bg-[var(--semi-color-primary)]"
    />
  );
}
