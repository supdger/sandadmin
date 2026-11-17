import { IllustrationConstruction, IllustrationConstructionDark } from '@douyinfe/semi-illustrations';
import { Typography } from '@douyinfe/semi-ui';
import { useTranslation } from 'react-i18next';

import { env } from '@/config/env';
import { useThemeMode } from '@/layouts/themeMode';

export interface LoginLeftViewProps {
  className?: string;
}

/**
 * 登录页左侧品牌区：产品名 + 标语 + 插画。纯 UI 件；
 * 窄屏由调用方加 `hidden lg:flex` 隐藏，不占登录表单宽度。
 */
export function LoginLeftView({ className }: LoginLeftViewProps) {
  const { t } = useTranslation('feMisc');
  const { isDark } = useThemeMode();
  const Illustration = isDark ? IllustrationConstructionDark : IllustrationConstruction;

  return (
    <aside
      className={className}
      style={{
        flex: '1 1 0%',
        display: 'flex',
        flexDirection: 'column',
        justifyContent: 'center',
        padding: '0 8%',
        background:
          'linear-gradient(160deg, var(--semi-color-primary-light-default) 0%, var(--semi-color-bg-0) 70%)',
      }}
    >
      <Illustration style={{ width: 320, height: 200 }} aria-hidden />
      <Typography.Title heading={3} style={{ marginTop: 24 }}>
        {env.appName}
      </Typography.Title>
      <Typography.Title heading={5} type="secondary" style={{ marginTop: 8, fontWeight: 400 }}>
        {t('loginLeft.slogan')}
      </Typography.Title>
      <Typography.Paragraph type="tertiary" style={{ marginTop: 16, maxWidth: 420 }}>
        {t('loginLeft.description')}
      </Typography.Paragraph>
    </aside>
  );
}
