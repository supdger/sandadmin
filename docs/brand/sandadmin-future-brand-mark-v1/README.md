# SandAdmin 未来品牌标识（开放立方体粒子 S）

## 定位

本目录保存 SandAdmin 的未来完整品牌标识，不作为当前 favicon 或后台侧栏小图标。

- 当前系统小图标：`六边形 + 立体粒子 S`；
- 未来完整品牌标识：`开放立方体边线 + 立体粒子 S`；
- 推荐用于官网主视觉、发布页、演示文稿、海报、启动画面和其他中大尺寸品牌场景；
- 不得使用本目录文件覆盖 `sandadmin-artd/public/favicon.ico` 或 `server/public/favicon.ico`。

## 透明性

- PNG 母版及导出图包含真实 alpha 透明通道；
- 背景、网格、立方体面和三根竖向支撑均已移除；
- 只保留上下开放边线、发光节点、立体粒子 S 和必要辉光；
- SVG 为自包含文件，内嵌透明母版 PNG。

## 文件

- `sandadmin-brand-mark-master.svg`：自包含 SVG 母版；
- `sandadmin-brand-mark-master.png`：1024×1024 透明 PNG 母版；
- `sandadmin-brand-mark-1024.png`、`512.png`、`170.png`：透明 PNG 导出；
- `sandadmin-brand-mark-170.webp`：透明 WebP 导出；
- `sandadmin-brand-mark-preview.png`：浅色／深色背景预览；
- `sandadmin-brand-mark-reference.png`：用户确认保留的原始参考图。

## 使用约束

- 中大尺寸场景优先使用 SVG 或 1024px PNG；
- 不应直接缩小为 16px favicon，细粒子和开放边线在该尺寸下会丢失；
- 未来如调整品牌标识，应从本目录创建新版本，不覆盖已确认的归档文件。
