import type { MouseEventHandler, ReactNode } from 'react';

import { Button, Tooltip } from '@douyinfe/semi-ui';
import type { IconSize } from '@douyinfe/semi-icons/lib/es/components/Icon';

import { SvgIcon } from '@/components/SvgIcon';
import { useCan } from '@/hooks/useCan';

export interface IconButtonProps {
  /** 图标注册表里的名字（lib/icons）；未注册名渲染兜底图标。 */
  icon?: string;
  /** 非注册表图标的自定义节点；与 icon 二选一，iconNode 优先。 */
  iconNode?: ReactNode;
  /** 悬停提示；也是 aria-label 的缺省来源。 */
  tooltip?: ReactNode;
  size?: 'small' | 'default' | 'large';
  shape?: 'circle' | 'square';
  /** 权限码不满足时整个按钮不渲染（显隐语义，不做禁用）。 */
  permission?: string | string[];
  danger?: boolean;
  disabled?: boolean;
  label?: string;
  onClick?: MouseEventHandler<HTMLButtonElement>;
  className?: string;
}

const ICON_SIZES: Record<NonNullable<IconButtonProps['size']>, IconSize> = {
  small: 'small',
  default: 'default',
  large: 'large',
};

/** 图标按钮：表格行内、卡片角标等只有一个图标的操作位。 */
export function IconButton({
  icon,
  iconNode,
  tooltip,
  size = 'default',
  shape = 'square',
  permission,
  danger = false,
  disabled = false,
  label,
  onClick,
  className,
}: IconButtonProps) {
  const allowed = useCan(permission ?? []);
  if (!allowed) {
    return null;
  }

  const node = iconNode ?? <SvgIcon name={icon} size={ICON_SIZES[size]} />;
  const button = (
    <Button
      icon={node}
      theme="borderless"
      type={danger ? 'danger' : 'tertiary'}
      size={size}
      disabled={disabled}
      aria-label={typeof tooltip === 'string' ? tooltip : label}
      onClick={onClick}
      className={[shape === 'circle' ? 'rounded-full' : '', className]
        .filter(Boolean)
        .join(' ')}
    />
  );

  return tooltip ? <Tooltip content={tooltip}>{button}</Tooltip> : button;
}
