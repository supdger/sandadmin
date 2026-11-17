import type { ReactNode } from 'react';

import { IconMenu, IconMoon, IconSetting, IconSun } from '@douyinfe/semi-icons';
import { IconButton, Layout } from '@douyinfe/semi-ui';
import { useTranslation } from 'react-i18next';

import { NotificationBell } from '@/layouts/NotificationBell';
import { useThemeMode } from '@/layouts/themeMode';
import { UserMenu } from '@/layouts/UserMenu';
import { setSidebarCollapsed, useLayoutSettings } from '@/stores/layout';
import { openSettings } from './globalUi';
import { RouteBreadcrumb } from './RouteBreadcrumb';

export interface HeaderBarProps {
  /** 左侧折叠触发器；顶部/混合布局没有侧栏折叠，传 false。 */
  collapseTrigger?: boolean;
  /** 传 true 时不渲染面包屑（顶部/混合布局把横排菜单放在同一行）。 */
  hideBreadcrumb?: boolean;
  /** 左侧附加区：顶部/混合布局用来放 Logo 和横排菜单。 */
  leading?: ReactNode;
  /** 右侧操作位；缺省是通知铃、明暗切换、设置入口、用户菜单。 */
  actions?: ReactNode;
}

/**
 * 顶栏：折叠触发、面包屑、右侧操作位三段。右侧默认操作沿用壳层
 * 既有组件（通知、主题、用户菜单），设置齿轮打开设置面板。
 */
export function HeaderBar({
  collapseTrigger = true,
  hideBreadcrumb = false,
  leading,
  actions,
}: HeaderBarProps) {
  const { t } = useTranslation('feLayout');
  const { isDark, setMode } = useThemeMode();
  const collapsed = useLayoutSettings().sidebarCollapsed;

  const handleToggleTheme = () => {
    setMode(isDark ? 'light' : 'dark');
  };

  const defaultActions = (
    <>
      <NotificationBell />
      <IconButton
        theme="borderless"
        icon={isDark ? <IconSun /> : <IconMoon />}
        aria-label={isDark ? t('header.themeToLight') : t('header.themeToDark')}
        onClick={handleToggleTheme}
      />
      <IconButton
        theme="borderless"
        icon={<IconSetting />}
        aria-label={t('header.settings')}
        onClick={openSettings}
      />
      <UserMenu />
    </>
  );

  return (
    <Layout.Header
      className="flex items-center gap-3 px-4"
      style={{
        backgroundColor: 'var(--semi-color-bg-1)',
        borderBottom: '1px solid var(--semi-color-border)',
      }}
    >
      {collapseTrigger ? (
        <IconButton
          theme="borderless"
          icon={<IconMenu />}
          aria-label={collapsed ? t('collapse.unfold') : t('collapse.fold')}
          onClick={() => setSidebarCollapsed(!collapsed)}
        />
      ) : null}
      {leading}
      {hideBreadcrumb ? null : <RouteBreadcrumb />}
      <div className="ml-auto flex items-center gap-1">{actions ?? defaultActions}</div>
    </Layout.Header>
  );
}
