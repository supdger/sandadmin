# AGENTS

先读 [README.md](README.md) —— 宿主范围、安装入口与公开定位。

改动设计或代码前必读：

- [仓库与插件治理](docs/repository-governance.md) —— 宿主零插件门槛与源码边界。
- [架构与插件边界](docs/architecture.md) —— 运行时职责与数据边界。
- [插件开发与发布约定](docs/plugin-development.md) —— 生命周期、发布与验收要求。
- [宿主发布与消费同步](docs/host-consumer-sync.md) —— 版本发布、消费方拉取与宿主锁定协议。
- [文档总览](docs/README.md) —— 宿主公共文档入口。

## 硬门槛

- SandAdmin 必须在**零业务插件**状态下完成安装、迁移、登录和基础后台运行；不得为任一具体 Sand 插件建立隐式启动或数据依赖。
- 目标为独立插件仓库发布：`plugins/` 只保存统一仓库清单，不保存业务插件源码；每个插件从其权威仓库独立发布，迁移完成前仍以 `sand_plugins` 的已提交版本为来源，禁止两处并行维护。
- 不要把具体插件的领域表、业务路由、菜单、权限、配置或服务实现作为新的宿主能力提交到本仓库。
- 本源码工作树不得持久保存 `server/plugin/sand-*`、`server/plugin/sandworkflow` 或对应管理端业务插件运行副本；这些只允许存在于消费工作区的演示或隔离宿主。
- SandAdmin 只发布版本和变更通知，不得通过 post-commit hook 或脚本主动改写其他工作区。`sand_plugins`、`sand_ai` 及未来消费者必须主动拉取锁定版本并记录宿主 revision。
- 跨宿主/插件故障先冻结版本组合并按 [`docs/host-requests/`](docs/host-requests/README.md)提交；单插件复现默认在插件侧解决，只有零插件、中立扩展或多个独立插件复现的通用缺陷才进入宿主。宿主修复须尽量保持 SaiAdmin 上游结构和升级路径，优先扩展契约而非核心分叉。前两轮单变量诊断未收敛时，第 3 轮使用 `gpt-6-astra/high` 做有界根因审查；仍无可区分结论则停止并标记阻塞。
- 插件安装、升级、卸载的证据必须来自消费工作区的独立验证宿主；静态检查或构建不能替代真实生命周期验收。

## 提交与协作

- 保持现有分支和无关工作区改动；未经明确授权，不提交、推送、迁移数据或启停服务。
- 新插件或插件迁移先更新 [仓库与插件治理](docs/repository-governance.md) 中的边界与兼容记录，再实施代码变更。

## 按场景使用

- 改 Sand 平台命名、领域归属或跨插件边界：`sand-platform-conventions`。
- 改宿主、同步插件或做安装验收：`sandadmin-host-plugins`。
