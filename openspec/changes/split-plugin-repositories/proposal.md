## Why

SandAdmin 当前把插件 Release 放在宿主仓库，导致插件发布资产与宿主生命周期耦合，也让使用者误以为下载宿主源码会携带全部插件。插件应拥有独立仓库和发布历史，同时由 SandAdmin 维护统一、可信的发现目录。

## What Changes

- 为目录中的每个插件声明独立公开 GitHub 仓库，安装器从该仓库的 Release 下载包。
- SandAdmin 继续维护统一 `plugins/catalog.json`，但不再保存业务插件源码或 Release 资产。
- 将 SandIAM、SandWorkflow、SandAI 从已提交的权威源码拆分为独立仓库，保留可追溯历史、文档、许可证和发布包。
- 先发布并验证独立仓库资产，再切换目录；切换成功后清理 SandAdmin 中已被替代的插件 Release。
- 保留旧目录条目的读取兼容，禁止浏览器提交任意仓库或下载 URL。

## Capabilities

### New Capabilities

- `independent-plugin-repositories`: 定义统一目录、逐插件独立仓库、可信下载和安全迁移清理的行为契约。

### Modified Capabilities

无。当前项目尚未归档主规格；原同仓方案保留在既有变更记录中，本变更以新能力替代其发布模型。

## Impact

- 影响 `RepositoryLogic` 的目录解析和 Release URL 构造、目录契约测试、宿主文档与治理说明。
- 新增 `supdger/sand-iam`、`supdger/sand-workflow`、`supdger/sand-ai` 三个公开仓库及各自 Release。
- 迁移期间同时涉及 `sand-plugins` 的权威来源记录和 SandAdmin 目录，但不会把本地未提交工作公开发布。
- 已安装插件和数据库状态不因仓库迁移自动变化。
