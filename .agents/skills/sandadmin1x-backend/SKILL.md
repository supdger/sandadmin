---
name: sandadmin1x-backend
description: 在 SandAdmin 1.x（PHP/Webman + Doctrine DBAL，PostgreSQL/MySQL 双库）开发、修改或审查宿主后端代码时使用：内核分层、接口与响应壳、建表迁移、编号、TypePHP 写法、鉴权与安全开关。前端页面、插件契约、AI 开发闭环流程各有独立 skill。
---

# SandAdmin 1.x 后端开发

本 skill 只管 **1.x 宿主内核（`server/`）的后端写法**。框架本身单租户：不在这里讲 SandIAM、SandAI、workflow 等任何具体插件，也不为它们开后门。

## 什么时候用

- 写或改 `server/` 下的 PHP 代码、路由、中间件、迁移脚本；
- 建表、改表、设计编号或双库写法；
- 碰登录、会话、权限码、审计日志、安全开关相关的后端代码；
- 审查 1.x 后端 PR。

纯前端、插件包契约、CI/分支/发版流程不在这里，分工见最后一节。

## 硬性规矩清单（每条一句话，细节进 references）

1. **内核权威在 `server/src`（命名空间 `Sandadmin\`）**，`server/app` 只是薄壳、`server/config` 管路由/中间件/进程/数据库、`server/plugin/` 是 Composer 产物目录不是源码 —— 新代码进 `src`，分层和目录见 [references/backend-structure.md](references/backend-structure.md)。
2. **数据访问只走 DBAL 仓储层（`src/Infra/Db`）**：禁止裸写 PDO/拼接 SQL/`SELECT *`/非白名单字段；方言差异只许出现在 `Infra/Db` 方言文件里，规则见 [references/database.md](references/database.md)。
3. **双库是硬要求**：PostgreSQL 15+ 和 MySQL 8.0+ 都支持（推荐并测试 8.4 LTS，特性小版本见 v4 L-1）；每份迁移覆盖两库——方言无关子集优先、差异处同一逻辑两套方言由迁移层分流——且都带回退脚本；**生产环境不执行回退脚本**，生产回退只切上一版制品 + 按需恢复备份，写法见 [references/database.md](references/database.md)。
4. **编号分两类**：数量可预见的表（部门、岗位、菜单、角色等）用 `int` 自增；不可预见持续增长的表（日志、会话、票据等）用雪花 64 位 `bigint`（方案甲 41 位毫秒 + 13 位节点 + 9 位序列，应用层发号、接口输出字符串）；nanoid 只作对外不可猜链接的单独一列、不当主键；逐表归类以《编号两列表-修订》为准（老板审表前是草稿）；分区表两库一律不建数据库级外键，见 [references/database.md](references/database.md)。
5. **TypePHP 兼容写法是硬规矩**：`declare(strict_types=1)`、全量类型标注、禁魔术方法/动态属性/动态调用/可变变量、常驻进程不存请求态；PHPStan 最高级别是合并门禁，见 [references/typephp.md](references/typephp.md)。
6. **响应壳走 v1 契约信封**：`/api/admin/v1` 内出 `{code,message,...,request_id}` 形状，业务拒绝抛契约异常不自己拼响应；路由、中间件链、异常 handler 写法见 [references/backend-structure.md](references/backend-structure.md)。
7. **鉴权只认内核会话 + v1 权限码**：身份一律走 `IdentityProvider`/`RequestContext`，代码里不自己解析令牌、不构造身份；RBAC、数据范围、审计、sudo 高危操作规则见 [references/security-auth.md](references/security-auth.md)。
8. **安全开关口径固定**：日志留存可配置——安全日志（登录、操作、审计、找回/双因素事件、权限变更）下限 183 天、界面不许调低，普通日志默认 183 天、可下调到 30 天；TOTP 双因素和密码定期更换是后台开关、默认关（等保三级部署必须开双因素）；开发免登录整条链只在开发构建存在、生产包编译期剔除；机器令牌走 `sand_system_api_token`（摘要 + scope + 过期 + authz_version 联动），MCP 高危写只生成待确认请求由人 sudo 批准，见 [references/security-auth.md](references/security-auth.md)。
9. **部署形态**：生产环境 = CI 构建的编译制品，不安装、不编译、无网页安装器；生产上插件动作只有查看/启用/停用，见 [references/backend-structure.md](references/backend-structure.md)。
10. **不做代码生成器**——网页版、命令行脚手架都不做（生成业务代码的工具才算；从 OpenAPI 机械派生的路由表/权限注册表/MCP 工具表/TS 类型/测试骨架是构建产物【待老板确认 R-2】）；新模块和大方案先做能力对照表（对照 saiadmin 6.x、若依、Laravel、ThinkPHP 生态、webman 官方插件），没有对照表不通过。

## 与其他 1.x skill 的分工

| skill | 管什么 | 什么时候换它 |
| --- | --- | --- |
| `sandadmin1x-backend`（本 skill） | `server/` 后端：分层、响应壳、路由、双库与迁移、编号、TypePHP、鉴权安全、部署形态 | — |
| `sandadmin1x-frontend` | `web/` 前端（React + Semi Design）、接口调用、权限显隐 | 写页面、组件、前端 API 层 |
| `sandadmin1x-plugin` | 框架对插件的契约：扩展点、迁移记账、安装闸、插件能/不能清单 | 设计或审查插件包、改 PackageOps 对外契约 |
| `sandadmin1x-ai-loop` | AI 开发闭环：AGENTS.md/skill 形态、MCP 双向、测试与 OpenAPI 生成、分支门禁、发版回退 | 改测试体系、CI 门禁、AI 协作入口 |

后端要配合 AI 闭环的门槛只写一条底线：**每个后端模块必须带测试、接口以 `openapi.yaml` + `api-conventions.md` 为契约权威、合并前 PHPStan 最高级别 + 编译检查 + 测试全过**；流程细节（分支、OpenAPI 生成测试、MCP、发版保留上一版）归 `sandadmin1x-ai-loop`。

## 上手顺序

1. 建表/改表/写迁移 → 先读 [references/database.md](references/database.md)。
2. 写控制器、路由、异常、分层 → 先读 [references/backend-structure.md](references/backend-structure.md)。
3. 写任何 PHP → [references/typephp.md](references/typephp.md) 的写法清单逐条照做。
4. 碰登录、权限、日志、安全开关 → [references/security-auth.md](references/security-auth.md)。
5. 命名（表、列、接口字段、错误码、权限码、迁移文件）一律按仓库《命名与注释规范》；交付前跑 `naming_check.py sql|code|notes|terms`，FAIL 改完再交。
