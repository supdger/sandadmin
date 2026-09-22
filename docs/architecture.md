# 架构与插件边界

## 运行单元

| 目录 | 责任 |
| --- | --- |
| `server/` | 完整 Webman 宿主、宿主配置、锁文件、测试和维护脚本。 |
| `supdger/sand-core` | 核心后端与完整管理前端的独立 Composer 权威仓库。 |
| `supdger/sand-package` | 插件安装器后端与专属前端的独立 Composer 权威仓库。 |
| `server/plugin/sandadmin/`、`server/plugin/sandpackage/` | Composer 安装后发布的 Webman 运行副本。 |
| `sandadmin-artd/` | 与宿主 revision 锁定、可直接开发运行的 Vue 前端源码组合。 |
| `docs/` | 宿主公共文档、架构契约和兼容性说明。 |
| 消费宿主 `server/storage/sandpackage/` | 本机持久安装登记、候选、备份、归档及恢复数据；不随源码分发或缓存清理。 |

`start.php`、`webman`、`app/`、`support/`、宿主 `config/` 和 Composer 锁文件均由本仓维护。基础 Composer 包使用统一顶层布局 `server/`、`sandadmin-artd/`；包内前端只保存源码，不执行依赖安装或构建。统一演示消费者位于 `/Users/code/project/sand_demo`，只接收锁定 revision，不反向承载源码。

## 数据和命名

SandAdmin 面向 PostgreSQL。全新 PostgreSQL 基线使用 `sand_system_*`、`sand_tool_*` 等核心表；新 Sand 平台插件使用 `sand_<domain>_*` 表前缀，例如 `sand_workflow_*`、`sand_iam_*`。

部分历史实例和兼容层仍会出现 `sa_*` 表或标识。它们不能仅因对外品牌更名而机械改名。对既有库的表名转换不属于常规安装步骤；在提供可验证、可回滚的专门迁移方案前，不应将其描述为受支持的升级路径。

## 插件契约

ZIP 业务插件应拥有自己的业务表、菜单、权限、路由和版本生命周期。`sand-core`、`sand-package` 是 Composer 基础包，不走 ZIP 插件安装生命周期。宿主负责核心身份、基础权限、安装入口和运行时扩展能力；插件不得把自己的领域数据混入核心表，也不得把插件内部实现当作宿主稳定 API。

SandAdmin 必须在零业务插件状态下保持可安装、可登录和可用。可选插件的源码工作区、宿主副本同步规则和真实验收要求见[仓库与插件治理](repository-governance.md)。

`catalog.json` 只保存统一仓库清单，不保存业务插件源码或安装包，也不自动加载。本源码工作树不保存业务插件安装副本。插件源码和 Release 位于各自独立权威仓库；安装后的插件目录只存在于消费工作区的演示或隔离宿主。宿主版本由消费方按[宿主发布与消费同步](host-consumer-sync.md)主动拉取并锁定。

插件与宿主的直接耦合（核心命名空间、前端载荷路径、环境变量或命令）必须记录在插件兼容性矩阵中。更名和兼容过渡详情见[更名与插件兼容性通知](compatibility/sandadmin-rename-notice.md)。
