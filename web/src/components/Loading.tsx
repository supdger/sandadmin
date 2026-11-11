import { Spin } from '@douyinfe/semi-ui';
import { useTranslation } from 'react-i18next';

export interface LoadingProps {
  /** Fill the viewport — used by route guards and first paint. */
  fullPage?: boolean;
  tip?: string;
}

export function Loading({ fullPage, tip }: LoadingProps) {
  const { t } = useTranslation();
  return (
    <div
      className={
        fullPage
          ? 'flex min-h-screen items-center justify-center'
          : 'flex items-center justify-center py-10'
      }
    >
      <Spin size="large" tip={tip ?? t('loading')} />
    </div>
  );
}
