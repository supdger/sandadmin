# SandAdmin

SandAdmin 是一个基于 Webman 的 PostgreSQL 原生后台管理基础项目，提供权限管理、系统配置、代码生成、任务调度和插件化扩展能力。完整运行单元由 `server/`（Webman 后端）和 `sandadmin-artd/`（Vue 管理前端）组成；`plugins/` 存放可独立安装的 Sand 平台插件。

> **来源说明**：SandAdmin 是基于 [SaiAdmin 6.x](https://github.com/saithink/saiadmin6.x) 修改和维护的独立 PostgreSQL fork。它不是 SaiAdmin 官方发行版，也不代表 SaiAdmin 或其作者的背书。名称、目录和运行配置已按 SandAdmin 维护；为了已有实例与第三方依赖兼容，部分历史标识仍会保留在实现层。

## 特性与边界

- PostgreSQL 优先：核心安装器按数据库驱动选择初始化脚本，核心 SQL 位于 `server/plugin/sandadmin/db/`。
- 插件化：Sand 平台新插件使用 `sand_<domain>_*` 表前缀；`plugins/sandworkflow` 是独立维护的 PostgreSQL 插件包。
- 兼容优先：部分历史核心 `sa_*` 表和必要的第三方兼容标识不会因品牌更名被强制改写。
- 非迁移工具：本仓库不承诺将既有 MySQL 实例原地迁移到 PostgreSQL；升级或迁移应先在隔离环境验证。

## 快速开始

运行环境、配置项和首次安装步骤见[本地运行与首次安装](docs/getting-started.md)。简要流程为：

1. 以 `server/.env.pgsql.example` 创建本地 `server/.env`，填写 PostgreSQL 连接信息。
2. 在 `server/` 安装 PHP 依赖并启动 Webman。
3. 在 `sandadmin-artd/` 安装前端依赖并启动开发服务，或执行生产构建。
4. 在全新数据库访问 `/install` 完成初始化。

默认前端目录为 `sandadmin-artd`；服务与 Channel 端口变量为 `SANDADMIN_SERVER_PORT`、`SANDADMIN_CHANNEL_PORT`。

## 文档

- [文档总览](docs/README.md)
- [本地运行与首次安装](docs/getting-started.md)
- [架构与插件边界](docs/architecture.md)
- [插件开发与发布约定](docs/plugin-development.md)
- [更名与插件兼容性通知](docs/compatibility/sandadmin-rename-notice.md)
- [上游来源、署名与许可证说明](docs/upstream-and-licensing.md)

## 插件

| 插件 | 说明 | 文档 |
| --- | --- | --- |
| SandWorkflow | PostgreSQL 工作流：流程定义、发起、待办/已办/抄送与流程数据管理 | [插件 README](plugins/sandworkflow/README.md) |

各插件必须在自己的发布目录保留安装、升级、卸载、权限、兼容性和已知限制说明；宿主只维护公共约定与兼容性入口，避免两处文档漂移。

## 参与与安全

- [贡献指南](CONTRIBUTING.md)
- [安全漏洞报告](SECURITY.md)
- [社区行为准则](CODE_OF_CONDUCT.md)
- [支持范围](SUPPORT.md)
- [变更记录](CHANGELOG.md)

## 来源与许可证

本仓库及保留的上游源代码遵循 MIT 许可证。发布、分发或再修改时，必须保留适用的版权与许可证声明；完整来源和第三方组件说明见 [NOTICE](NOTICE)。
