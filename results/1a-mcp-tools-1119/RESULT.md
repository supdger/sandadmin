# RESULT — 1.x「框架即 MCP 服务」业务工具集（无库部分）

- 仓库：supdger/sandadmin
- 分支：`feat/mcp-business-tools`（目标 `main`，**未合并**）
- 基线 SHA：`977427a`（main，开工时 HEAD）
- 最终 SHA：`927b7f34b00984ff8f2a33a29150f159da9dabe5`（commit `927b7f3`）
- PR：https://github.com/supdger/sandadmin/pull/100
- 工作副本：`~/agent-work/grokbot/mcp-dev-1117/repo`

## 做了什么

在既有 MCP 适配层（PR #85：协议/SDK/开发期四工具）上补业务工具集。
只改 `server/src` 的 MCP 相关模块与对应测试；`contract/openapi.yaml`、
门禁文件、CI 均未改；未建库、未写 DDL、未跑迁移、未启数据库。

### 业务工具（27 个，全部显式登记于 `BusinessCatalog`）

| 档位 | 工具 | 映射 v1 路由 | 权限码 |
| --- | --- | --- | --- |
| read | biz_menus_tree | GET /api/admin/v1/menus/tree | system:menu:index |
| read | biz_depts_tree | GET /api/admin/v1/depts/tree | system:dept:index |
| read | biz_roles_index | GET /api/admin/v1/roles | system:role:index |
| read | biz_role_show | GET /api/admin/v1/roles/{role_id} | system:role:index |
| read | biz_role_permissions | GET /api/admin/v1/roles/{role_id}/permissions | system:role:index |
| read | biz_permission_tree | GET /api/admin/v1/permission-tree | system:role:index |
| read | biz_users_index | GET /api/admin/v1/users | system:user:index |
| read | biz_user_show | GET /api/admin/v1/users/{user_id} | system:user:index |
| read | biz_posts_index | GET /api/admin/v1/posts | system:post:index |
| read | biz_dicts_index | GET /api/admin/v1/dicts | system:dict:index |
| read | biz_dict_show | GET /api/admin/v1/dicts/{type_id} | system:dict:index |
| write | biz_dept_create / biz_dept_update | POST/PUT /api/admin/v1/depts[/{dept_id}] | system:dept:create / update |
| write | biz_post_create / biz_post_update | POST/PUT /api/admin/v1/posts[/{post_id}] | system:post:create / update |
| write | biz_dict_create / biz_dict_update | POST/PUT /api/admin/v1/dicts[/{type_id}] | system:dict:create / update |
| write | biz_menu_create_group / biz_menu_create_link | POST /api/admin/v1/menus/groups / links | system:menu:create |
| write | biz_menu_update / biz_menu_move / biz_menu_visible | PATCH/POST/PUT /api/admin/v1/menus/{menu_key}[/move\|/visible] | system:menu:update |
| write | biz_menus_reset | POST /api/admin/v1/menus/reset | system:menu:reset |
| high_risk | biz_users_export | POST /api/admin/v1/users/export | system:user:export |
| high_risk | biz_user_reset_password | POST /api/admin/v1/users/{user_id}/reset-password | system:user:reset-password |
| high_risk | biz_user_assign_roles | PUT /api/admin/v1/users/{user_id}/roles | system:user:assign-role |
| high_risk | biz_role_save_permissions | PUT /api/admin/v1/roles/{role_id}/permissions | system:role:permission:update |

口径：11 read / 12 write / 4 high_risk。high_risk 即鉴权 P0 的 N4 高危清单
（全量导出、重置他人密码、分配/移除角色、改权限）。

### 权限（两层闸，无特权旁路）

- 外闸：`McpKernel` 按 `ToolSpec.permission` 查 `McpIdentity::can()`
  （凭据 scope），权限码与 v1 契约同一套。
- 内闸：`KernelBizGateway` 经 `McpActorSource`（`OrgActorSource`）把 MCP
  身份落成 `OrgActor`（有效权限 = 账号权限 ∩ 凭据 scope，只能缩小），
  再走 `BizDispatch` 显式映射表派发到与 v1 控制器同一个业务服务方法
  （UserService/DeptService/RoleService/PostService/DictService/MenuService），
  服务内部沿用 v1 的 `actor->can` 判定、校验与业务审计。身份落不到
  有效账号按未认证拒绝。
- 档位语义（M-02）：read 直执行；write 工具的 inputSchema 强制声明
  `idempotency_key`（必填）与 `dry_run`；high_risk 永不直接执行，
  返回 `PendingRequest` 待确认请求形状。
- `ToolRunner` 增加 `CallContext` 参数（request_id/ip/device），请求态
  只走参数不进全局；`BizKernel` 经工厂闭包延迟求值，库不可用时单工具
  回 `INTERNAL_ERROR`，不拖垮 tools/list 与其余工具。

### 审计

- 复用 `McpKernel`→`AuditSink`→`McpCall` 链路：`McpController` 的
  `LogAuditSink` 改接 `security` 日志通道（原为 default）。写文件日志，
  不建表；参数记 SHA-256 摘要，不落原文；request_id 与调用同一条。

### 新增/修改文件

新增：`Domain/Mcp/{CallContext,BizGateway,BizReply,McpActorSource}.php`、
`Infra/Mcp/Biz/{BusinessCatalog,BizDispatch,BizToolRunner,KernelBizGateway,OrgActorSource}.php`、
`tests/Unit/Infra/Mcp/Biz/`（5 个测试）、`tests/Contract/McpBusinessToolsTest.php`。
修改：`McpKernel`（透传 CallContext）、`ToolRunner`（接口加参数）、
`McpController`（业务工具并入目录 + security 审计通道）、四个 Dev 工具
与两个 Application 测试（跟随接口签名）。

## 验证（命令 / 退出码 / 关键输出）

| 命令 | 退出码 | 关键输出 |
| --- | --- | --- |
| `cd server && vendor/bin/phpunit --testsuite unit` | 0 | `OK (432 tests, 2868 assertions)` |
| `cd server && vendor/bin/phpunit --testsuite contract` | 0 | `OK, but there were issues! Tests: 243, Assertions: 608, Incomplete: 100.`（incomplete 均为既有标记） |
| `cd server && vendor/bin/phpstan analyse` | 0 | `[OK] No errors` |
| `tools/pre-merge.sh --fast` | 0 | `pre-merge --fast 全部通过`（7/7 PHPStan 含在内） |
| `python3 tools/naming_check.py code --allow naming-allow.txt <改动文件>` | 0 | 两处 WARN（`$actual` 含糊修饰、类注释 7 行超限）已改净后复跑 EXIT=0 |
| `python3 tools/naming_check.py notes <新文件>` | 0 | 无 WARN/FAIL |
| `python3 tools/naming_check.py terms tools/naming-terms.tsv <新文件>` | 0 | 无 WARN/FAIL |
| `php tools/typephp_check.php` | 0 | `模式=diff FAIL=0 WARN=0` |

环境修复记录：工作副本 vendor 缺 `symfony/yaml`，跑过一次
`composer install`（它按 webman/console 骨架覆盖了 `server/webman`，
被覆盖删掉的 gssencmode 注入块已手工还原，未进提交）。

## 剩余项（本任务不做）

- **机器令牌**：`sand_system_api_token` 归属与表结构未定，需库，不做。
- **持久幂等存储**：存储归属未定，需库；当前 write 档只强制校验
  `idempotency_key` 必带并支持 `dry_run`，无去重持久化。
- **流式**：不做；现有 Stream 抽象保持占位，未接 MCP。
- 高危工具的人工 sudo 审批通道（PendingRequest 落地后接）随令牌/审批
  归属一起后置。
- `contract/openapi.yaml` 的操作级 `x-mcp` 元数据落地后，
  `BusinessCatalog` 登记表应改为构建期产物（登记表内已注明）。
