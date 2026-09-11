# SandAdmin 前端迁移与图标设计交接

> 状态：已执行品牌资源替换。2026-08-15 已确认并接入 v6「六边形 + 立体粒子 S」方案。

## 已确认的决定

- `sa-*` 内部组件标签、目录和工具名**不改**。它们属于实现层，与早期 MineAdmin 保留 `ma-*` 组件相同；改动只会制造无业务价值的兼容风险。
- 对外名称统一为 `SandAdmin`；宿主前端目录统一为 `sandadmin-artd`。
- 前端内部 `sa-*` 组件保持不变；数据库核心表已迁移为 `sand_system_*`、`sand_tool_*`，Sand 应用继续使用 `sand_<domain>_*` 数据表。

## 前端改动清单与归属

| 范围 | 必须改动 | 负责人 | 验收 |
| --- | --- | --- | --- |
| SandAdmin 宿主前端 | 保持 `sandadmin-artd/`、`index.html` 标题和说明为 SandAdmin；接入新 Logo 与 favicon | Codex 已接入；后续由 Codex 审核构建 | `pnpm run build` 通过；浏览器标题、标签图标、侧栏 Logo 正确 |
| SandIAM 管理端 | 将交付目录从 `saiadmin-artd/src/views/plugin/sand-iam/` 迁移为 `sandadmin-artd/src/views/plugin/sand-iam/`；更新插件打包/说明中的路径 | Cursor | 管理端构建通过；插件包解压后路径正确 |
| SandAI 管理端 | 由开源插件载荷提供 `sandadmin-artd/src/views/plugin/sand-ai/`；菜单组件路径为 `/plugin/sand-ai/...` | 插件维护者 | 插件包前端载荷、构建与类型检查通过 |
| SandAI 发布形态 | 可安装的开源插件，权威包位于 `/Users/code/project/sand_plugins/sand-ai/`；宿主不内置 SandAI 的 API、管理路由或菜单 | 插件维护者 | 包含 `plugin/sand-ai/**`、前端载荷和 PostgreSQL 生命周期脚本；可在消费方锁定的空白宿主安装/卸载 |
| SandWorkflow 管理端 | 保持已迁移的 `sandadmin-artd/` 打包路径；核对说明、安装包与实际宿主一致 | Codex | 包安装后页面可进入，关键工作流回归通过 |
| 安装页模板 | 去除 `https://saithink.top/images/logo.png` 远程引用，改为仓库内 SandAdmin 资产 | Codex | 离线安装页仍显示 Logo；不请求上游域名 |

SandIAM 与 SandAI 均按插件交付。SandAI 的应用版开发与运行归独立 SandAI 工作区；当前仓库只维护可安装的开源插件包。前端路径、后端结构和最终验收必须以这一边界为准。

## 图标资产清单

### 必须替换

| 位置 | 当前用途 | 交付规格 | 负责人 |
| --- | --- | --- | --- |
| `sandadmin-artd/src/assets/images/common/logo.png` | 侧栏/系统主 Logo，170×170 | 已替换为透明 PNG，由主 SVG 的透明预览导出 | 已完成 |
| `sandadmin-artd/src/assets/images/favicon.ico` | 前端 `index.html` 的开发与构建入口图标 | 已替换为 16、32、48、60、64、128、256 px 多尺寸 ICO | 已完成 |
| `sandadmin-artd/public/favicon.ico` | 静态资源根路径兜底图标 | 已替换为同源多尺寸 ICO | 已完成 |
| `server/public/favicon.ico` | 后端首页 `/favicon.ico` | 已替换为同源多尺寸 ICO | 已完成 |
| `server/plugin/sandadmin/app/view/install/index.html` | 安装页 Logo | 已改用仓库内 `/app/sandadmin/assets/sandadmin-logo.svg` | 已完成 |
| `server/plugin/sandadmin/app/view/install/error.html` | 安装错误页 Logo | 已改用同一仓库内 SVG | 已完成 |
| `server/overrides/sandadmin/app/view/install/index.html` | 安装页覆盖模板 | 已与正式模板同步 | 已完成 |

### 必须处理、但当前未发现运行时引用

| 位置 | 处理要求 |
| --- | --- |
| `sandadmin-artd/src/assets/images/common/logo.webp` | 已同步替换为同源 SandAdmin 图。 |

### 不属于品牌替换范围

- `sa-*` 组件及 Iconify 图标库；
- 登录页装饰图、错误页插画、菜单布局预览图、SandWorkflow 审批印章；
- 业务上传的系统配置 favicon。它是管理员配置数据，不应被代码迁移覆盖。

## 图标设计任务书

### 品牌目标

SandAdmin 是面向 PostgreSQL 的独立后台管理基础项目。图标应传达“稳定、结构、数据底座”，而不是聊天机器人、通用 AI 或上游 SaiAdmin 的衍生标志。

### 最终选型（2026-08-15）

- 透明背景、无黑色外框；
- 青色发光六边形表达稳定结构与科技属性；
- 金、紫、青三层粒子构成立体扭转的 `S`，表达沙粒、创意和可能性；
- 粒子随模拟深度改变大小、亮度和透明度，避免平铺圆点感；
- `S` 内部使用高密度粒子脊柱提高小尺寸辨识度；
- 两端粒子团越过六边形约一条边线宽度；
- 16px 允许粒子融合为带纹理的 `S` 轮廓，48px 以上逐渐显现独立粒子与立体层次。

### 双层品牌资产定位

| 资产 | 定位 | 使用范围 |
| --- | --- | --- |
| `六边形 + 立体粒子 S` | 当前系统图标 | favicon、后台侧栏、安装页小尺寸标识 |
| `开放立方体边线 + 立体粒子 S` | 未来完整品牌标识 | 官网主视觉、发布页、演示文稿、海报和启动画面 |

未来完整品牌标识归档在 `docs/brand/sandadmin-future-brand-mark/`，当前确认为 v3 强化沙带版。该目录保存真实透明 SVG/PNG、浅色／深色预览及用户确认的参考图；v1、v2 在同级目录保留，不得用于覆盖当前 favicon。

### 推荐方向

使用抽象的 **沙丘层理 + S 形负空间**：方形轮廓内由 2–3 条有节奏的曲线形成向上的 `S` 感，表现 Sand 与结构化数据。图形在 16px 时必须仍可识别，不依赖文字。

建议基调（可在出图阶段微调）：

- 深石墨：`#1F2933`，作为深色背景和文字基底；
- 暖砂金：`#C99043`，作为主识别色；
- 浅砂白：`#F7F3EA`，用于浅色背景版本；
- 单色版：纯黑和纯白各一份。

### 禁止项

- 不复刻或近似 SaiAdmin、MineAdmin、OpenAI 或其他现有项目的图形语言；
- 不使用文字、中文、缩写或小字作为 favicon 的必要识别元素；
- 粒子和渐变必须服务于 `S` 的小尺寸轮廓；不得让 16px 只剩噪点或让六边形完全压过主体；
- 不将 AI 生成的单张位图直接作为唯一源文件。

### 交付物

1. 可编辑主源 `sandadmin-logo-master.svg`；
2. 深色背景、浅色背景、单色黑、单色白四个 SVG 版本；
3. 透明 PNG：1024×1024 母版、512×512、170×170；
4. 多尺寸 `favicon.ico`：16、32、48、60 px；
5. 一张深/浅背景预览图，供发布页和 README 选择；
6. 简短的来源声明：生成工具、人工选择/修改范围，以及不使用上游 Logo 的确认。

## 生成与选型流程

1. 用 ChatGPT 图像生成创建 3 个方向草图，只做概念筛选；
2. 选定一个后，人工整理为可编辑 SVG 母版，检查小尺寸轮廓；
3. 导出上述规格，替换全部“必须替换”资产；
4. 分别在浅色/深色浏览器标签、侧栏、登录页和安装页截图验收；
5. 执行前端构建与离线安装页检查，再提交为单独的品牌资源变更。

图标设计与资源替换应在独立任务中完成，避免与核心重命名、插件迁移或业务功能改动混在同一个提交中。
