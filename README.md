# SandAdmin

SandAdmin 是一个基于 Webman 的 PostgreSQL 原生后台管理项目，提供权限管理、系统配置、代码生成、任务调度和插件化扩展能力。本仓库维护可直接运行的 `server/` Webman 宿主和与之锁定的 `sandadmin-artd/` Vue 管理前端；核心与插件安装器的权威源码分别由 Composer 包 `supdger/sand-core`、`supdger/sand-package` 维护。

> **来源说明**：SandAdmin 是基于 [SaiAdmin 6.x](https://github.com/saithink/saiadmin6.x) 修改和维护的独立 PostgreSQL fork。它不是 SaiAdmin 官方发行版，也不代表 SaiAdmin 或其作者的背书。名称、目录和运行配置已按 SandAdmin 维护；为了已有实例与第三方依赖兼容，部分历史标识仍会保留在实现层。

> **版本状态**：SandAdmin 尚处于早期开发与验证阶段，版本线从 `0.x` 开始，当前基线为 `0.1.0`。在完成更大范围的安装、升级、插件生命周期和业务链验证前，不作 1.0 稳定性承诺。

## 特性与边界

- PostgreSQL 优先：`sand-core` 安装到宿主后的核心安装器按数据库驱动选择初始化脚本，核心 SQL 运行路径为 `server/plugin/sandadmin/db/`。
- 插件化：Sand 平台新插件使用 `sand_<domain>_*` 表前缀；可选插件独立打包发布，插件仓库直接读取 GitHub 清单和 Release 附件，无需独立市场平台。
- 兼容优先：部分历史核心 `sa_*` 表和必要的第三方兼容标识不会因品牌更名被强制改写。
- 非迁移工具：本仓库不承诺将既有 MySQL 实例原地迁移到 PostgreSQL；升级或迁移应先在隔离环境验证。

## 快速开始

运行环境、配置项和首次安装步骤见[本地运行与首次安装](docs/getting-started.md)。简要流程为：

1. 克隆仓库：`git clone https://github.com/supdger/sandadmin.git`。
2. 在 `server/` 执行 `composer install`，保持 `.env` 不存在并运行 `php start.php`。
3. 在 `sandadmin-artd/` 按需复制 `.env*.example`，执行 `pnpm install` 和 `pnpm dev`。
4. 自行准备空 PostgreSQL 数据库，访问 `http://localhost:8787/core/install` 完成初始化。

全新安装完成后，使用 `admin` / `123456` 登录管理后台，并在首次登录后立即修改默认密码。该初始凭据仅适用于由当前安装器创建的全新数据库；已安装实例不会被安装器重置管理员密码。

默认前端目录为 `sandadmin-artd`；服务与 Channel 端口变量为 `SANDADMIN_SERVER_PORT`、`SANDADMIN_CHANNEL_PORT`。

## 文档

- [文档总览](docs/README.md)
- [本地运行与首次安装](docs/getting-started.md)
- [架构与插件边界](docs/architecture.md)
- [仓库与插件治理](docs/repository-governance.md)
- [插件开发与发布约定](docs/plugin-development.md)
- [仓库插件分发](docs/repository-plugin-distribution.md)
- [插件管理端载荷激活契约](docs/frontend-plugin-activation.md)
- [宿主发布与消费同步](docs/host-consumer-sync.md)
- [更名与插件兼容性通知](docs/compatibility/sandadmin-rename-notice.md)
- [上游来源、署名与许可证说明](docs/upstream-and-licensing.md)

## 可选插件

根目录 `catalog.json` 只维护统一插件目录。`sand-core` 与 `sand-package` 是宿主基础 Composer 包，不属于 ZIP 业务插件；每个可选业务插件拥有独立源码仓库和 Release，宿主不保存其源码、安装包、运行副本或专属自动加载映射。安装器按目录声明的受信仓库下载所选插件；未选择插件的用户只会获取 SandAdmin 主体。

`/Users/code/project/sand_demo` 是本机统一演示与验收宿主，只从 SandAdmin 和各插件权威仓库单向接收源码或发布包，不反向承载源码开发。插件实际安装与验收仍在该消费工作区或可丢弃隔离宿主完成。

插件发布包必须在其自己的源码单元保留安装、升级、卸载、权限、兼容性和
已知限制说明；宿主只维护公共约定与兼容性入口，避免两处文档漂移。

## 版本

当前开发基线为 `0.1.0`，尚未形成 1.0 稳定性承诺。版本变化见 [CHANGELOG](CHANGELOG.md)。

## 来源与许可证

本仓库及保留的上游源代码遵循 MIT 许可证。发布、分发或再修改时，必须保留适用的版权与许可证声明；完整来源和第三方组件说明见 [NOTICE](NOTICE)。
