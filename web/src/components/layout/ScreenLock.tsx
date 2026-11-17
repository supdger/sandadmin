import { IconLock } from '@douyinfe/semi-icons';
import { Avatar, Button, Typography } from '@douyinfe/semi-ui';
import { useTranslation } from 'react-i18next';
import { useLocation, useNavigate } from 'react-router';

import { useMe } from '@/hooks/useMe';
import { loginUrl } from '@/routes/paths';
import { clearScreenLock } from './globalUi';

/**
 * 全屏锁屏罩层。锁定态只是 UI 偏好（localStorage）不是安全边界；
 * 解锁清本地锁后跳登录页重认证，由 ?redirect= 带回原页面。
 * TODO(fe-gap): 后端提供 sudo/就地重校验端点后改为就地解锁。
 */
export function ScreenLock() {
  const { t } = useTranslation('feLayout');
  const me = useMe();
  const navigate = useNavigate();
  const location = useLocation();

  const handleUnlock = () => {
    clearScreenLock();
    navigate(loginUrl(location.pathname + location.search), { replace: true });
  };

  return (
    <div
      role="dialog"
      aria-label={t('lock.title')}
      className="fixed inset-0 flex flex-col items-center justify-center gap-4"
      style={{ zIndex: 2000, backgroundColor: 'var(--semi-color-bg-0)' }}
    >
      <Avatar size="large" src={me.data?.avatar ?? undefined}>
        {me.data?.nickname?.slice(0, 1)}
      </Avatar>
      <Typography.Title heading={4} style={{ margin: 0 }}>
        {me.data?.nickname ?? ''}
      </Typography.Title>
      <Typography.Text type="tertiary">
        <IconLock size="small" /> {t('lock.tip')}
      </Typography.Text>
      <Button type="primary" theme="solid" onClick={handleUnlock}>
        {t('lock.unlock')}
      </Button>
    </div>
  );
}
