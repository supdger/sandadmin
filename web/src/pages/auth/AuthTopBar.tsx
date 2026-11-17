import type { ReactNode } from 'react';

import { IconMoon, IconSemiLogo, IconSun } from '@douyinfe/semi-icons';
import { Button, Typography } from '@douyinfe/semi-ui';
import { useTranslation } from 'react-i18next';

import { env } from '@/config/env';
import { useThemeMode } from '@/layouts/themeMode';

export interface AuthTopBarProps {
  /** 右侧追加的动作位（语言切换等）。 */
  extra?: ReactNode;
}

/**
 * 鉴权页顶栏：左 logo + 产品名，右侧主题切换与调用方追加的动作位。
 * 纯 UI 件，不参与登录流程。
 */
export function AuthTopBar({ extra }: AuthTopBarProps) {
  const { t } = useTranslation('feMisc');
  const { isDark, setMode } = useThemeMode();

  return (
    <header className="flex h-14 items-center justify-between px-6">
      <div className="flex items-center gap-2">
        {env.appLogo ? (
          <img src={env.appLogo} alt={env.appName} style={{ height: 28 }} />
        ) : (
          <IconSemiLogo size="large" style={{ color: 'var(--semi-color-primary)' }} />
        )}
        <Typography.Title heading={6} style={{ margin: 0 }}>
          {env.appName}
        </Typography.Title>
      </div>
      <div className="flex items-center gap-1">
        {extra}
        <Button
          theme="borderless"
          icon={isDark ? <IconSun /> : <IconMoon />}
          aria-label={t('authTopBar.toggleTheme')}
          onClick={() => setMode(isDark ? 'light' : 'dark')}
        />
      </div>
    </header>
  );
}
