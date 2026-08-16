# 架构与插件边界

## 运行单元

| 目录 | 责任 |
| --- | --- |
| `server/` | Webman 后端、核心配置、PostgreSQL 安装器和宿主运行时。 |
| `server/plugin/sandadmin/` | SandAdmin 核心插件及核心安装 SQL。 |
| `sandadmin-artd/` | Vue 管理前端。 |
| `plugins/<plugin>/` | 可独立发布的插件源包、生命周期 SQL、说明和前端载荷。 |
| `docs/` | 宿主公共文档、架构契约和兼容性说明。 |

## 数据和命名

SandAdmin 面向 PostgreSQL。全新 PostgreSQL 基线使用 `sand_system_*`、`sand_tool_*` 等核心表；新 Sand 平台插件使用 `sand_<domain>_*` 表前缀，例如 `sand_workflow_*`、`sand_iam_*`。

部分历史实例和兼容层仍会出现 `sa_*` 表或标识。它们不能仅因对外品牌更名而机械改名。对既有库的表名转换不属于常规安装步骤；在提供可验证、可回滚的专门迁移方案前，不应将其描述为受支持的升级路径。

## 插件契约

插件应拥有自己的业务表、菜单、权限、路由和版本生命周期。宿主负责核心身份、基础权限、安装入口和运行时扩展能力；插件不得把自己的领域数据混入核心表，也不得把插件内部实现当作宿主稳定 API。

插件与宿主的直接耦合（核心命名空间、前端载荷路径、环境变量或命令）必须记录在插件兼容性矩阵中。更名和兼容过渡详情见[更名与插件兼容性通知](compatibility/sandadmin-rename-notice.md)。
