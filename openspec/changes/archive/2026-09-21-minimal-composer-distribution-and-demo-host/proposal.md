## Why

SandAdmin 当前把产品源码、完整 Webman 运行骨架、演示宿主和维护过程材料混在同一仓库，既扩大公开仓库体积，也让“源码权威”和“可运行演示”边界不清。参考 SaiAdmin 6.x 的 Composer 插件安装方式，本次将 SandAdmin 收敛为可安装的后端载荷与独立前端源码，并以 `/Users/code/project/sand_demo` 作为 SandAdmin 和 Sand 插件唯一演示宿主。

## What Changes

- 增加根 Composer library 入口和 Webman 插件安装器，让已有 Webman 项目通过 Composer 安装 SandAdmin 后端载荷，而不是从源码仓库直接运行完整框架骨架。
- **BREAKING**：从 SandAdmin 源码仓库移除 Webman 消费者骨架；`server/` 只保留 SandAdmin/SandPackage 后端载荷、安装器、测试和维护脚本。
- 保留 `sandadmin-artd/` 作为独立 Vue 前端源码，前端不由 Composer 隐式复制或构建。
- 将根测试、维护脚本和工具归并到 `server/`；精简根目录治理文件和过时文档。
- 暂时保留 `plugins/catalog.json` 的公开路径，避免旧客户端目录请求变成 404。
- 建立 `/Users/code/project/sand_demo/server` 与 `/Users/code/project/sand_demo/sandadmin-artd`，承接零插件 SandAdmin 演示及 Sand 插件安装、演示和验收。
- 将仍指向 `sand_plugins/sandadmin-demo-host` 的活动配置、脚本和测试默认值迁移到 `sand_demo`，不把演示副本当作源码权威。

## Capabilities

### New Capabilities

- `composer-installable-sandadmin`: 定义 SandAdmin 后端作为 Webman Composer 插件的安装、更新、持久数据保护和前端边界。
- `unified-sand-demo-host`: 定义 `sand_demo` 作为唯一演示/验收宿主及其与 SandAdmin、Sand 插件权威源码的单向消费关系。
- `minimal-source-repository`: 定义 SandAdmin 公开源码仓库允许保留的产品、文档、测试和发布目录边界。

### Modified Capabilities

无。

## Impact

- SandAdmin 根目录、Composer 元数据、`server/` 结构、测试路径、插件打包脚本和安装文档。
- Webman 消费者的首次安装与升级方式。
- `plugins/catalog.json` 的兼容路径保持不变。
- `/Users/code/project/sand_demo` 新演示宿主。
- `sand_plugins` 中演示路径默认值、同步脚本和活动文档；其现有未提交业务代码不在本次改动范围。
