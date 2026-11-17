import { Typography } from '@douyinfe/semi-ui';

import type { LayoutMode } from '@/stores/layout';

export interface LayoutPreviewProps {
  mode: LayoutMode;
  /** 当前选中的布局加高亮描边。 */
  active?: boolean;
  label: string;
  onSelect?: () => void;
}

/**
 * 设置面板里的布局缩略预览：纯静态 SVG 示意三种布局形态的
 * 区域结构（侧栏/顶栏/内容），不渲染真实组件。
 */
export function LayoutPreview({ mode, active = false, label, onSelect }: LayoutPreviewProps) {
  const primary = 'var(--semi-color-primary)';
  const faint = 'var(--semi-color-fill-0)';
  const mid = 'var(--semi-color-fill-1)';

  return (
    <button
      type="button"
      onClick={onSelect}
      aria-pressed={active}
      className="flex w-24 flex-col items-center gap-1 rounded p-2"
      style={{
        cursor: 'pointer',
        border: `1px solid ${active ? primary : 'var(--semi-color-border)'}`,
        backgroundColor: 'var(--semi-color-bg-1)',
      }}
    >
      <svg width="72" height="48" viewBox="0 0 72 48" aria-hidden>
        {mode === 'side' ? (
          <>
            <rect x="0" y="0" width="18" height="48" rx="1" fill={primary} opacity="0.85" />
            <rect x="21" y="0" width="51" height="8" rx="1" fill={mid} />
            <rect x="21" y="11" width="51" height="37" rx="1" fill={faint} />
          </>
        ) : null}
        {mode === 'top' ? (
          <>
            <rect x="0" y="0" width="72" height="10" rx="1" fill={primary} opacity="0.85" />
            <rect x="0" y="13" width="72" height="35" rx="1" fill={faint} />
          </>
        ) : null}
        {mode === 'mix' ? (
          <>
            <rect x="0" y="0" width="72" height="10" rx="1" fill={primary} opacity="0.85" />
            <rect x="0" y="13" width="18" height="35" rx="1" fill={mid} />
            <rect x="21" y="13" width="51" height="35" rx="1" fill={faint} />
          </>
        ) : null}
      </svg>
      <Typography.Text size="small">{label}</Typography.Text>
    </button>
  );
}
