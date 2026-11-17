import { useCallback, useRef, useState } from 'react';
import type { PointerEvent as ReactPointerEvent, SyntheticEvent } from 'react';

import { Button, Slider, Typography } from '@douyinfe/semi-ui';
import { useTranslation } from 'react-i18next';

export interface ImageCropperProps {
  /** 图片来源（object URL 或同源可访问地址）。 */
  src: string;
  /** 裁剪框宽高比；缺省为整幅舞台自由裁。 */
  aspectRatio?: number;
  /** 输出图片 MIME。 */
  outputType?: string;
  stageWidth?: number;
  stageHeight?: number;
  /** 确认输出裁剪结果；环境无 canvas 时不会回调。 */
  onConfirm: (blob: Blob) => void;
  onCancel: () => void;
}

interface CropRect {
  x: number;
  y: number;
  width: number;
  height: number;
}

function cropRectOf(stageW: number, stageH: number, ratio?: number): CropRect {
  let width = stageW;
  let height = stageH;
  if (ratio && ratio > 0) {
    width = Math.min(stageW * 0.8, stageH * 0.8 * ratio);
    height = width / ratio;
  }
  return { x: (stageW - width) / 2, y: (stageH - height) / 2, width, height };
}

/** 偏移钳制：图片必须始终盖住裁剪框。 */
function clampOffset(
  x: number,
  y: number,
  rect: CropRect,
  imgW: number,
  imgH: number,
): { x: number; y: number } {
  return {
    x: Math.min(rect.x, Math.max(rect.x + rect.width - imgW, x)),
    y: Math.min(rect.y, Math.max(rect.y + rect.height - imgH, y)),
  };
}

/**
 * 上传前图片裁剪：拖动图片在固定裁剪框下定位，滑杆缩放，
 * 确认后 canvas 按裁剪框导出 Blob 交给上传组件。
 * 拖动的是图而不是框——框始终居中，数学上少一层换算。
 */
export function ImageCropper({
  src,
  aspectRatio,
  outputType = 'image/png',
  stageWidth = 480,
  stageHeight = 320,
  onConfirm,
  onCancel,
}: ImageCropperProps) {
  const { t } = useTranslation('feMisc');
  const rect = cropRectOf(stageWidth, stageHeight, aspectRatio);
  const [image, setImage] = useState<HTMLImageElement | null>(null);
  const [zoom, setZoom] = useState(1);
  const [offset, setOffset] = useState({ x: 0, y: 0 });
  const [dragging, setDragging] = useState(false);
  const dragRef = useRef<{ startX: number; startY: number; baseX: number; baseY: number } | null>(
    null,
  );

  // cover 基准缩放：图天然尺寸 × base 后至少铺满舞台。
  const base = image
    ? Math.max(stageWidth / image.naturalWidth, stageHeight / image.naturalHeight)
    : 1;
  const scale = base * zoom;
  const imgW = (image?.naturalWidth ?? 0) * scale;
  const imgH = (image?.naturalHeight ?? 0) * scale;

  const handleLoad = (e: SyntheticEvent<HTMLImageElement>) => {
    const img = e.currentTarget;
    setImage(img);
    const cover = Math.max(stageWidth / img.naturalWidth, stageHeight / img.naturalHeight);
    const coverW = img.naturalWidth * cover;
    const coverH = img.naturalHeight * cover;
    setOffset(
      clampOffset(
        rect.x + (rect.width - coverW) / 2,
        rect.y + (rect.height - coverH) / 2,
        rect,
        coverW,
        coverH,
      ),
    );
  };

  const handlePointerDown = (e: ReactPointerEvent<HTMLDivElement>) => {
    e.currentTarget.setPointerCapture(e.pointerId);
    dragRef.current = { startX: e.clientX, startY: e.clientY, baseX: offset.x, baseY: offset.y };
    setDragging(true);
  };

  const handlePointerMove = (e: ReactPointerEvent<HTMLDivElement>) => {
    const drag = dragRef.current;
    if (!drag) {
      return;
    }
    setOffset(
      clampOffset(
        drag.baseX + e.clientX - drag.startX,
        drag.baseY + e.clientY - drag.startY,
        rect,
        imgW,
        imgH,
      ),
    );
  };

  const handlePointerUp = () => {
    dragRef.current = null;
    setDragging(false);
  };

  const handleZoom = (value: number) => {
    setZoom(value);
    setOffset((prev) => clampOffset(prev.x, prev.y, rect, imgW * (value / zoom), imgH * (value / zoom)));
  };

  const handleConfirm = useCallback(() => {
    if (!image) {
      return;
    }
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    if (!ctx || typeof canvas.toBlob !== 'function') {
      return;
    }
    canvas.width = Math.round(rect.width);
    canvas.height = Math.round(rect.height);
    ctx.drawImage(
      image,
      (rect.x - offset.x) / scale,
      (rect.y - offset.y) / scale,
      rect.width / scale,
      rect.height / scale,
      0,
      0,
      canvas.width,
      canvas.height,
    );
    canvas.toBlob((blob) => {
      if (blob) {
        onConfirm(blob);
      }
    }, outputType);
  }, [image, rect, offset, scale, outputType, onConfirm]);

  return (
    <div className="flex flex-col gap-3" data-testid="image-cropper">
      <div
        role="presentation"
        className="relative touch-none overflow-hidden select-none"
        style={{
          width: stageWidth,
          height: stageHeight,
          background: 'var(--semi-color-fill-0)',
          cursor: dragging ? 'grabbing' : 'grab',
        }}
        onPointerDown={handlePointerDown}
        onPointerMove={handlePointerMove}
        onPointerUp={handlePointerUp}
      >
        <img
          src={src}
          alt=""
          draggable={false}
          onLoad={handleLoad}
          style={{
            position: 'absolute',
            left: offset.x,
            top: offset.y,
            width: imgW,
            height: imgH,
            maxWidth: 'none',
            maxHeight: 'none',
          }}
        />
        {/* 裁剪框：四角暗化提示边界，边框描出有效区。 */}
        <div
          className="pointer-events-none absolute border-2 border-dashed"
          style={{
            left: rect.x,
            top: rect.y,
            width: rect.width,
            height: rect.height,
            borderColor: 'var(--semi-color-primary)',
            boxShadow: '0 0 0 9999px rgba(0, 0, 0, 0.35)',
          }}
        />
      </div>
      <div className="flex items-center gap-3">
        <Typography.Text size="small" type="secondary" style={{ width: 32 }}>
          {t('imageCropper.zoom')}
        </Typography.Text>
        <div className="flex-1">
          <Slider
            min={1}
            max={3}
            step={0.05}
            value={zoom}
            onChange={(v) => handleZoom(Array.isArray(v) ? (v[0] ?? 1) : (v ?? 1))}
          />
        </div>
      </div>
      <div className="flex justify-end gap-2">
        <Button onClick={onCancel}>{t('imageCropper.cancel')}</Button>
        <Button theme="solid" type="primary" onClick={handleConfirm} disabled={!image}>
          {t('imageCropper.confirm')}
        </Button>
      </div>
    </div>
  );
}
