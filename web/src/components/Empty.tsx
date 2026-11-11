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
  children?: ReactNode;
}

export function Empty({ title, description, children }: EmptyProps) {
  const { t } = useTranslation();
  return (
    <SemiEmpty
      image={<IllustrationNoContent />}
      darkModeImage={<IllustrationNoContentDark />}
      title={title ?? t('empty.title')}
      description={description ?? t('empty.description')}
    >
      {children}
    </SemiEmpty>
  );
}
