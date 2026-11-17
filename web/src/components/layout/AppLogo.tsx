import { Typography } from '@douyinfe/semi-ui';

import { SvgIcon } from '@/components/SvgIcon';
import { env } from '@/config/env';

export interface AppLogoProps {
  /** 折叠态只显图标。 */
  collapsed?: boolean;
}

/** 系统 Logo：配置了 VITE_APP_LOGO 用图片，否则走图标注册表的 layers。 */
export function AppLogo({ collapsed = false }: AppLogoProps) {
  return (
    <div className="flex h-14 shrink-0 items-center gap-2 px-4">
      {env.appLogo ? (
        <img src={env.appLogo} alt={env.appName} className="h-7 w-7 shrink-0" />
      ) : (
        <SvgIcon name="layers" size="large" />
      )}
      {collapsed ? null : (
        <Typography.Title heading={6} ellipsis={{ showTooltip: true }} style={{ margin: 0 }}>
          {env.appName}
        </Typography.Title>
      )}
    </div>
  );
}
