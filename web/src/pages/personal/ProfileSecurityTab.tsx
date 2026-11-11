import { useState } from 'react';

import { Button, Card, Descriptions, Skeleton, Typography } from '@douyinfe/semi-ui';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import { PasswordChangeModal } from '@/components/PasswordChangeModal';
import { getMyProfile } from '@/api/profile';
import { ErrorState } from '@/components/ErrorState';
import { formatDate } from './relativeTime';

const PROFILE_QUERY_KEY = ['me', 'profile'] as const;

/** 账号安全 tab: password last-changed info + change-password dialog. */
export function ProfileSecurityTab() {
  const { t } = useTranslation('profile');
  const profile = useQuery({ queryKey: PROFILE_QUERY_KEY, queryFn: getMyProfile });
  const [modalOpen, setModalOpen] = useState(false);

  if (profile.isPending) {
    return (
      <Card style={{ maxWidth: 560 }}>
        <Skeleton loading placeholder={<Skeleton.Paragraph rows={2} />}>
          <div />
        </Skeleton>
      </Card>
    );
  }
  if (profile.isError || !profile.data) {
    return <ErrorState kind="error" error={profile.error} onRetry={() => void profile.refetch()} />;
  }

  return (
    <Card style={{ maxWidth: 560 }}>
      <div className="flex items-center justify-between gap-4">
        <Descriptions
          size="small"
          data={[
            {
              key: t('security.passwordLabel'),
              value: profile.data.password_changed_time
                ? t('security.passwordValue', { time: formatDate(profile.data.password_changed_time) })
                : '—',
            },
          ]}
        />
        <Button type="primary" theme="solid" onClick={() => setModalOpen(true)}>
          {t('security.changePassword')}
        </Button>
      </div>
      <Typography.Text type="tertiary" size="small">
        {t('basic.contactAdmin')}
      </Typography.Text>
      <PasswordChangeModal visible={modalOpen} onClose={() => setModalOpen(false)} />
    </Card>
  );
}
