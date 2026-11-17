import { useMemo } from 'react';
import type { CSSProperties } from 'react';

import { useMe } from '@/hooks/useMe';
import { formatDateTime } from '@/lib/format';

export interface WatermarkProps {
  /**
   * 水印文案；缺省为「当前账号名 + 挂载时刻」。
   * 后台设置面（水印开关/文案）就位前由调用方决定要不要渲染。
   */
  text?: string;
  /** 平铺间距 px（相邻水印中心的水平/垂直间隔）。 */
  gapX?: number;
  gapY?: number;
  /** 逆时针旋转角度。 */
  rotate?: number;
  /** 0-1，越大越深。 */
  opacity?: number;
  fontSize?: number;
  zIndex?: number;
  className?: string;
  style?: CSSProperties;
}

/** canvas 画一个平铺单元，返回 dataURL；jsdom 无 canvas 实现时返回 null。 */
function buildTile(
  text: string,
  gapX: number,
  gapY: number,
  rotate: number,
  opacity: number,
  fontSize: number,
): string | null {
  const canvas = document.createElement('canvas');
  const ctx = canvas.getContext('2d');
  if (!ctx) {
    return null;
  }
  ctx.font = `${fontSize}px sans-serif`;
  const textWidth = Math.ceil(ctx.measureText(text).width);
  canvas.width = textWidth + gapX;
  canvas.height = fontSize * 2 + gapY;
  // 改宽高会重置上下文，样式要重设。
  ctx.font = `${fontSize}px sans-serif`;
  ctx.fillStyle = `rgba(128, 128, 128, ${opacity})`;
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  ctx.translate((textWidth + gapX) / 2, (fontSize * 2 + gapY) / 2);
  ctx.rotate((rotate * Math.PI) / 180);
  ctx.fillText(text, 0, 0);
  return canvas.toDataURL('image/png');
}

/**
 * 页面水印：canvas 单元图平铺满视口，pointer-events 不拦截操作。
 * 默认文案取当前登录人昵称 + 时间，防止截图外发泄密时溯源。
 */
export function Watermark({
  text,
  gapX = 140,
  gapY = 100,
  rotate = -22,
  opacity = 0.15,
  fontSize = 14,
  zIndex = 1000,
  className,
  style,
}: WatermarkProps) {
  const { data: me } = useMe();
  // 挂载时刻固定下来，不因重渲染刷新成一串新时间。
  const stamp = useMemo(() => formatDateTime(new Date().toISOString()), []);
  const name = me?.nickname || me?.username || '';
  const content = text ?? `${name} ${stamp}`.trim();
  const tile = useMemo(
    () => (content ? buildTile(content, gapX, gapY, rotate, opacity, fontSize) : null),
    [content, gapX, gapY, rotate, opacity, fontSize],
  );

  return (
    <div
      aria-hidden
      data-watermark-text={content || undefined}
      className={className}
      style={{
        position: 'fixed',
        inset: 0,
        zIndex,
        pointerEvents: 'none',
        backgroundImage: tile ? `url(${tile})` : undefined,
        backgroundRepeat: 'repeat',
        ...style,
      }}
    />
  );
}
