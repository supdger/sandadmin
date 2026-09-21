## Why

SandAdmin 当前被改造成 Composer 后端包后，源码仓库不再能像既有 SaiAdmin 6.x 完整项目一样直接克隆、安装前后端依赖并运行；同时 `sand_plugins` 仍残留旧演示宿主职责，与已经建立的 `/Users/code/project/sand_demo` 重复。现在需要恢复清晰的单一源码、单一演示宿主边界。

## What Changes

- **BREAKING**：停止把 `supdger/sandadmin` 作为后端 Composer library 继续演进，恢复仓库根目录下完整的 `server/` Webman 项目与 `sandadmin-artd/` Vue 前端源码。
- 恢复“克隆 SandAdmin 仓库后，分别在前端和后端目录安装依赖并启动”的首选安装方式。
- 保留 SandAdmin 为零业务插件可安装、可运行的宿主；不把 SandAI、SandIAM、SandWorkflow 源码或运行副本并入 SandAdmin 源码仓库。
- 将 `/Users/code/project/sand_demo` 定义为 SandAdmin 与独立 Sand 插件的唯一演示、安装和生命周期验收宿主，源码只允许从权威仓库单向同步到该宿主。
- 停用 `sand_plugins` 内旧的 SandAdmin 演示宿主、锁文件和同步入口，但保留其未迁移插件源码、未提交工作与历史验收材料，不做仓库级清空或重置。
- 清理 SandAdmin 远端已合并或补丁等价的功能分支，最终只保留 `main`。
- 不删除已发布的历史 Composer 标签、GitHub Release 或 Packagist 记录；文档明确其为停止演进的历史发布，避免破坏既有消费者。

## Capabilities

### New Capabilities

- `complete-source-distribution`：定义 SandAdmin 完整前后端源码仓库的目录、安装、启动、零业务插件和发布边界。
- `unified-demo-host`：定义 `sand_demo` 对 SandAdmin 与独立插件的单向同步、演示、验收和运行数据保护职责。

### Modified Capabilities

无。

## Impact

- `sandadmin`：恢复 Webman 项目骨架、后端项目级 Composer 元数据和完整安装文档；撤销根级 Composer library 作为主安装入口的定位。
- `sand_demo`：成为唯一演示/验收消费者，需要记录 SandAdmin revision、插件锁定版本和同步方向。
- `sand_plugins`：旧演示宿主与同步脚本退役；其大量现有未提交插件工作不得被覆盖、删除或纳入 SandAdmin 提交。
- GitHub：清理 `codex/plugin-catalog-preview` 与 `codex/sandadmin-rename`；不改写 `main` 历史，不删除历史标签与 Release。
- Packagist：既有 `6.1.5` 保留为历史兼容发行，本次不继续发布新的 Composer 后端包。
