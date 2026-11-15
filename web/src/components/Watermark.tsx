import { useMemo } from 'react';

import { useThemeMode } from '@/layouts/themeMode';

export interface WatermarkProps {
  /** 平铺的水印文字（当前查看人的昵称）。 */
  text: string;
}

/**
 * 个人信息页的浅色水印：SVG data-URI 平铺背景，绝对定位铺满内容区，
 * pointer-events 关闭不挡交互，z-index 0 不盖 Semi 弹层（1000+）。
 * 明暗两套透明度保证深色模式仍可读但不刺眼。
 */
export function Watermark({ text }: WatermarkProps) {
  const { isDark } = useThemeMode();

  const tile = useMemo(() => {
    const color = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.07)';
    const svg =
      `<svg xmlns="http://www.w3.org/2000/svg" width="280" height="200">` +
      `<text x="10" y="150" transform="rotate(-20 140 100)" font-size="14" ` +
      `font-family="sans-serif" fill="${color}">${text}</text></svg>`;
    return `url("data:image/svg+xml;utf8,${encodeURIComponent(svg)}")`;
  }, [text, isDark]);

  if (!text) return null;
  return (
    <div
      aria-hidden
      data-testid="watermark"
      style={{
        position: 'absolute',
        inset: 0,
        pointerEvents: 'none',
        backgroundImage: tile,
        backgroundRepeat: 'repeat',
        zIndex: 0,
      }}
    />
  );
}
