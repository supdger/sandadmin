import type { ReactNode } from 'react';

import { Empty as SemiEmpty } from '@douyinfe/semi-ui';
import {
  IllustrationNoContent,
  IllustrationNoContentDark,
} from '@douyinfe/semi-illustrations';
import { useTranslation } from 'react-i18next';

export interface EmptyProps {
  title?: ReactNode;
  description?: ReactNode;
  /** 不传时用默认的「没有内容」插画；明暗模式共用同一张自定义图。 */
  image?: ReactNode;
  children?: ReactNode;
}

export function Empty({ title, description, image, children }: EmptyProps) {
  const { t } = useTranslation();
  return (
    <SemiEmpty
      image={image ?? <IllustrationNoContent />}
      darkModeImage={image ?? <IllustrationNoContentDark />}
      title={title ?? t('empty.title')}
      description={description ?? t('empty.description')}
    >
      {children}
    </SemiEmpty>
  );
}
