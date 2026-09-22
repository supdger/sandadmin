## Why

SandAdmin 当前同时承担 Webman 宿主、后台核心、插件安装器和前端源码的权威来源，导致已有 Webman 无法通过独立 Composer 依赖复用完整后台，也使核心、安装器和业务插件的版本与仓库边界不一致。需要参考 SaiAdmin 6.x 的宿主与 Composer 包分层，同时保持 Sand 插件统一的 `server/`、`sandadmin-artd/` 源码布局。

## What Changes

- 新建 `sand-core` 包：统一包含 `server/` 后端源码和 `sandadmin-artd/` 前端源码，并以 Composer 包提供核心后端及前端源码发布能力。
- 新建 `sand-package` 包：统一包含 `server/` 后端源码和 `sandadmin-artd/` 插件管理前端源码，并以 Composer 包提供插件安装与生命周期能力。
- 将 `sandadmin` 收敛为标准 Webman 宿主，`server/composer.json` 改为依赖 `supdger/sand-core` 和 `supdger/sand-package`。
- 为已有 Webman 提供通过 Composer 安装核心后台、再将匹配版本的 `sandadmin-artd` 源码发布到宿主外部可编辑目录的标准流程。
- 保持 SandIAM、SandAI、SandWorkflow 等业务插件使用 ZIP 及 SandPackage 生命周期安装，不转换为 Composer 核心依赖。
- 所有 Sand 包统一使用顶层 `server/` 和 `sandadmin-artd/`，不混用仅部分仓库具有的顶层 `src/`。
- **BREAKING**：`sandadmin` 不再以内置 `server/plugin/sandadmin` 和 `server/plugin/sandpackage` 作为长期权威源码；消费者必须通过新的 Composer 包获取对应能力。

## Capabilities

### New Capabilities

- `composer-installed-sand-foundation`: 定义标准宿主和已有 Webman 通过 Composer 获取 Sand 核心后端、插件安装器及匹配前端源码的行为。
- `sand-source-layout`: 定义 Sand 核心、安装器和业务插件统一的源码目录及所有权边界。

### Modified Capabilities

无。

## Impact

- 影响 `server/composer.json`、Composer 锁文件、Webman 插件加载、配置发现、命令、路由、数据库初始化资源和安装入口。
- 影响当前 `server/plugin/sandadmin`、`server/plugin/sandpackage`、`server/compat/Saithink/Saipackage` 与 `sandadmin-artd` 的源码所有权和发布位置。
- 需要在 `/Users/code/project/sand_plugins` 建立 `sand-core`、`sand-package` 权威源码目录，并保持 SandAdmin 宿主与这些包之间的单向消费关系。
- 不执行数据库迁移、服务启停、部署、提交或推送；真实安装、升级、恢复和浏览器验收作为迁移后的独立验证层。
