# SandAdmin 更名与插件兼容性通知

## 结论

PostgreSQL 宿主原名 **SaiAdmin-PG**，现更名为 **SandAdmin**。该更名会影响直接耦合核心命名空间、后台前端目录、环境变量或开发命令的插件；它**不**改变历史核心 `sa_*` 表，也不要求业务插件重命名其 `sand_*` 表、路由或 API。

## 受影响项目

| 项目 | 影响 | 必须动作 |
| --- | --- | --- |
| SandIAM | 直接依赖核心基类、异常、鉴权中间件和权限服务 | 将 `plugin\\saiadmin` / `plugin.saiadmin` 改为 SandAdmin 对应名称，并更新前端载荷路径 |
| SandAI | 直接依赖核心基类、异常、鉴权中间件、权限服务；项目依赖仍含旧核心包 | 更新代码引用、Composer 自动加载和依赖边界，并更新前端载荷路径 |
| SandWorkflow | 已安装旧版本的工作流账号空间与前端载荷名称与旧宿主绑定 | 升级到 SandAdmin 适配版，并按发布说明核对账号空间与工作流回归结果 |

因此，受影响的不是两个项目，而是 **SandIAM、SandAI、SandWorkflow 三个插件/项目**。其中 SandWorkflow 的源码适配已纳入本仓更名工作；SandIAM、SandAI 的项目内通知分别位于其 `docs/development/sandadmin-rename-notice.md`。

本仓源码进度（2026-08-14）：`server/plugin/sand-iam` 已与源码包对齐为 `plugin\\sandadmin`；运行配置 `FRONTEND_DIR` / `SANDADMIN_*` 已切到新名（端口仍为既有 8788/2207）；`server/plugin/sandworkflow/update.sql` 已含账号空间改写。SandWorkflow 的权威包与宿主副本（后端和 `sandadmin-artd` 前端）逐文件一致，且全部 PHP 源码语法检查通过。SandAI 源码的 SandAdmin 适配、SandIAM 管理 UI 迁移和 Composer 锁文件刷新已完成；其后端、前端宿主副本均已与唯一源码逐文件同步，并通过主宿主后端检查、前端类型检查与生产构建。可丢弃空白宿主上的双插件安装、依赖阶段、加载和卸载回收已验证；受保护管理路由与身份上下文仍须由目标部署的 signer 和管理员会话验收。版本控制的前端目录已是 `sandadmin-artd/`；本机遗留的被忽略旧目录不进入发布制品，不作为安装路径。

## 统一替换表

| 旧项 | 新项 |
| --- | --- |
| `plugin\\saiadmin\\...` | `plugin\\sandadmin\\...` |
| `plugin.saiadmin...` | `plugin.sandadmin...` |
| `saiadmin-artd/` | `sandadmin-artd/` |
| `FRONTEND_DIR=saiadmin-artd` | `FRONTEND_DIR=sandadmin-artd` |
| `SAIADMIN_*` | `SANDADMIN_*` |
| `sai:*` | `sand:*` |

## 兼容边界

- 全新 PostgreSQL 基线使用 `sand_system_*`、`sand_tool_*` 等核心表；历史实例中遗留的 `sa_*` 表或标识不因 SandAdmin 品牌更名而被强制改名。对既有库的表名转换必须有单独、可回滚且经过验证的迁移方案，不能由本通知或常规插件安装替代。
- Sand 插件仍遵守 `sand_<domain>_*` 表前缀，例如 `sand_iam_*`、`sand_ai_*`；
- `SandIAM`、`SandAI`、`SandWorkflow` 的插件标识和业务 API 不因宿主更名而改变；
- 任何使用旧宿主运行的实例，须先完成备份并在隔离 PostgreSQL 环境完成插件安装、登录链与关键业务回归，再安排生产升级。

## 发布顺序

1. 发布 SandAdmin 首个正式标签，并提供迁移说明；
2. 发布 SandIAM 与 SandAI 的 SandAdmin 适配版本；
3. 发布 SandWorkflow 的 SandAdmin 适配版本；
4. 由每个使用方在目标 PostgreSQL 宿主完成构建、安装和受保护路径验收。

在上述发布完成前，不应将旧 `saiadmin-pg` 引用视为已失效，也不要删除项目内为过渡保留的兼容加载逻辑。

前端路径、图标资产、设计任务书和执行分工见[前端迁移与图标设计交接](../brand/sandadmin-frontend-and-icon-handoff.md)。
