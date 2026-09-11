# 2026-09-11 零业务插件宿主纯净化

本次迁移把 SandAdmin 源码工作树恢复为零业务插件宿主，并建立消费方主动拉取协议。它不执行数据库操作、插件生命周期、服务启停、提交、推送或部署。

## 权威来源核对

| SandAdmin 历史副本 | 权威来源 | 核对结论 |
| --- | --- | --- |
| `plugins/sand-ai`、`server/plugin/sand-ai`、对应管理端 | `/Users/code/project/sand_plugins/sand-ai` | 权威源码包含全部副本内容并有更多新文件；宿主副本不是开发源 |
| `server/plugin/sand-iam`、对应管理端 | `/Users/code/project/sand_plugins/sand-iam` | 权威源码包含更完整的新版本；宿主仅有旧安装/开发副本 |
| `plugins/sandworkflow`、`server/plugin/sandworkflow`、对应管理端 | `/Users/code/project/sand_plugins/sandworkflow` | 后端与管理端逐文件一致；权威文档和宿主检查器更新 |
| SandAI `server/app`、配置、数据库、测试与脚本 | `/Users/code/project/sand_ai` | SandAI 主应用由独立工作区维护；SandAdmin 副本属于历史混入 |

仅存在于 SandAdmin 的 `waiting-codex` 页面是未冻结能力占位符，不是发布能力；旧 `Admin/SandAi` 和 unavailable adapter 已被 SandAI 工作区的新目录与实现取代，没有反向同步到权威源码。

## 清理范围

- 移除业务插件发布副本、安装副本和管理端载荷；
- 移除混入宿主的 SandAI 主应用代码、配置、数据库脚本、测试和运行脚本；
- 移除 SandAI 专属 Composer autoload、检查脚本和上传配置；
- 移除 SandAdmin 主动写入 SandAI 的 post-commit hook；
- 保留 SandAdmin 核心、SandPackage 通用安装能力、通用插件契约和宿主兼容性证据。

纯净化只证明源码边界和静态结构；零插件数据库安装、登录与运行仍需独立运行验收。
