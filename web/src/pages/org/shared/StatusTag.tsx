import { Tag } from '@douyinfe/semi-ui';
import { useTranslation } from 'react-i18next';

import type { UserStatus } from '@/api/v1/types';

/** 启用/停用状态 Tag：颜色 + 文字双通道，不只靠颜色区分。 */
export function StatusTag({ status }: { status: UserStatus }) {
  const { t } = useTranslation('org');
  if (status === 'enabled') {
    return (
      <Tag color="green" shape="circle">
        {t('status.enabled')}
      </Tag>
    );
  }
  return (
    <Tag color="grey" shape="circle">
      {t('status.disabled')}
    </Tag>
  );
}
