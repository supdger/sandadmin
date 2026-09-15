# SandAdmin

SandAdmin 是一个基于 Webman 的 PostgreSQL 原生后台管理基础项目，提供权限管理、系统配置、代码生成、任务调度和插件化扩展能力。完整运行单元由 `server/`（Webman 后端）和 `sandadmin-artd/`（Vue 管理前端）组成；它可以在不安装任何业务插件的情况下独立使用。

> **来源说明**：SandAdmin 是基于 [SaiAdmin 6.x](https://github.com/saithink/saiadmin6.x) 修改和维护的独立 PostgreSQL fork。它不是 SaiAdmin 官方发行版，也不代表 SaiAdmin 或其作者的背书。名称、目录和运行配置已按 SandAdmin 维护；为了已有实例与第三方依赖兼容，部分历史标识仍会保留在实现层。

## 特性与边界

- PostgreSQL 优先：核心安装器按数据库驱动选择初始化脚本，核心 SQL 位于 `server/plugin/sandadmin/db/`。
- 插件化：Sand 平台新插件使用 `sand_<domain>_*` 表前缀；可选插件的统一源码工作区是 `sand-plugins`，宿主只维护扩展契约与真实安装验收。
- 兼容优先：部分历史核心 `sa_*` 表和必要的第三方兼容标识不会因品牌更名被强制改写。
- 非迁移工具：本仓库不承诺将既有 MySQL 实例原地迁移到 PostgreSQL；升级或迁移应先在隔离环境验证。

## 快速开始

运行环境、配置项和首次安装步骤见[本地运行与首次安装](docs/getting-started.md)。简要流程为：

1. 以 `server/.env.pgsql.example` 创建本地 `server/.env`，填写 PostgreSQL 连接信息。
2. 在 `server/` 安装 PHP 依赖并启动 Webman。
3. 在 `sandadmin-artd/` 安装前端依赖并启动开发服务，或执行生产构建。
4. 在全新数据库访问 `/install` 完成初始化。

全新安装完成后，使用 `admin` / `123456` 登录管理后台，并在首次登录后立即修改默认密码。该初始凭据仅适用于由当前安装器创建的全新数据库；已安装实例不会被安装器重置管理员密码。

默认前端目录为 `sandadmin-artd`；服务与 Channel 端口变量为 `SANDADMIN_SERVER_PORT`、`SANDADMIN_CHANNEL_PORT`。

## 文档

- [文档总览](docs/README.md)
- [本地运行与首次安装](docs/getting-started.md)
- [架构与插件边界](docs/architecture.md)
- [仓库与插件治理](docs/repository-governance.md)
- [插件开发与发布约定](docs/plugin-development.md)
- [插件管理端载荷激活契约](docs/frontend-plugin-activation.md)
- [宿主发布与消费同步](docs/host-consumer-sync.md)
- [更名与插件兼容性通知](docs/compatibility/sandadmin-rename-notice.md)
- [上游来源、署名与许可证说明](docs/upstream-and-licensing.md)
- [AI 实现来源与追溯范围](AI_CONTRIBUTIONS.md)

## 可选插件

可选 Sand 插件的唯一源码由 `sand-plugins` 工作区管理。SandAdmin 不内置
SandWorkflow、SandAI、SandIAM 或其他业务插件的源码、运行时副本与专属
自动加载映射；插件包仅在消费工作区的独立验证宿主中按受控同步方式安装和验收。
SandAdmin 发布版本与变更通知，但不主动写入其他工作区；每个消费者负责拉取并锁定所需宿主版本。

插件发布包必须在其自己的源码单元保留安装、升级、卸载、权限、兼容性和
已知限制说明；宿主只维护公共约定与兼容性入口，避免两处文档漂移。

## 参与与安全

- [贡献指南](CONTRIBUTING.md)
- [AI 实现来源与追溯范围](AI_CONTRIBUTIONS.md)
- [安全漏洞报告](SECURITY.md)
- [社区行为准则](CODE_OF_CONDUCT.md)
- [支持范围](SUPPORT.md)
- [变更记录](CHANGELOG.md)

## 来源与许可证

本仓库及保留的上游源代码遵循 MIT 许可证。发布、分发或再修改时，必须保留适用的版权与许可证声明；完整来源和第三方组件说明见 [NOTICE](NOTICE)。
