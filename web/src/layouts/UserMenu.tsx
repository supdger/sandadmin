import { useState } from 'react';

import {
  IconChevronDown,
  IconExit,
  IconLock,
  IconSetting,
  IconUser,
} from '@douyinfe/semi-icons';
import { Avatar, Dropdown, Spin, Toast, Typography } from '@douyinfe/semi-ui';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import { logout } from '@/api/auth';
import { PasswordChangeModal } from '@/components/PasswordChangeModal';
import { useMe } from '@/hooks/useMe';
import { apiErrorMessage } from '@/lib/apiClient';

/** 头像下拉：个人设置、改密、偏好、退出。改密弹窗状态收在这里，不进 shell。 */
export function UserMenu() {
  const { t } = useTranslation('shell');
  const navigate = useNavigate();
  const me = useMe();
  const [passwordModalOpen, setPasswordModalOpen] = useState(false);

  const onLogout = async () => {
    try {
      await logout();
    } catch (e) {
      // The token is cleared locally either way; still surface the backend's
      // reason instead of failing silently.
      Toast.error(apiErrorMessage(e, t('common:errors.system.message')));
    } finally {
      navigate('/login', { replace: true });
    }
  };

  if (me.isPending) {
    return <Spin size="small" />;
  }
  if (!me.data) {
    return null;
  }

  return (
    <>
      <Dropdown
        trigger="click"
        position="bottomRight"
        render={
          <Dropdown.Menu>
            <Dropdown.Item icon={<IconUser />} onClick={() => navigate('/profile')}>
              {t('profile:title')}
            </Dropdown.Item>
            <Dropdown.Item
              icon={<IconLock />}
              onClick={() => setPasswordModalOpen(true)}
            >
              {t('profile:security.changePassword')}
            </Dropdown.Item>
            <Dropdown.Divider />
            <Dropdown.Item
              icon={<IconSetting />}
              onClick={() => navigate('/preferences')}
            >
              {t('header.preferences')}
            </Dropdown.Item>
            <Dropdown.Divider />
            <Dropdown.Item type="danger" icon={<IconExit />} onClick={() => void onLogout()}>
              {t('header.logout')}
            </Dropdown.Item>
          </Dropdown.Menu>
        }
      >
        <div
          className="flex items-center gap-2 px-2 py-1"
          style={{ cursor: 'pointer' }}
          role="button"
          tabIndex={0}
        >
          <Avatar size="small" src={me.data.avatar ?? undefined} alt={me.data.nickname}>
            {me.data.nickname?.slice(0, 1)}
          </Avatar>
          <Typography.Text>{me.data.nickname}</Typography.Text>
          <IconChevronDown size="small" />
        </div>
      </Dropdown>
      <PasswordChangeModal
        visible={passwordModalOpen}
        onClose={() => setPasswordModalOpen(false)}
      />
    </>
  );
}
