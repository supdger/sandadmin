import { createElement } from 'react';
import type { CSSProperties } from 'react';

import type { IconSize } from '@douyinfe/semi-icons/lib/es/components/Icon';

import { FALLBACK_ICON, getIcon } from '@/lib/icons';

export interface SvgIconProps {
  /** 注册表里的图标名；空名或未注册名渲染兜底图标。 */
  name: string | null | undefined;
  size?: IconSize;
  spin?: boolean;
  className?: string;
  style?: CSSProperties;
}

/**
 * 按名字渲染注册表图标的统一入口。装饰性图标不参与无障碍树，
 * 需要语义的场合由外层元素提供 aria-label。
 */
export function SvgIcon({ name, size = 'default', spin, className, style }: SvgIconProps) {
  const icon = getIcon(name) ?? getIcon(FALLBACK_ICON);
  if (!icon) {
    return null;
  }
  // createElement 而不是 JSX：查表得来的组件在渲染期解析，
  // eslint 的 static-components 规则会把 JSX 写法误报成
  // 「渲染期新建组件」。
  return createElement(icon, { size, spin, className, style, 'aria-hidden': true });
}
