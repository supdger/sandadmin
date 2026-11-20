# RESULT — M-22 / 计划项 1-12：拒收带 migrations 的插件包

- worktree：`/Users/supdger/agent-work/assistant/12f-pkg-reject-migrations-1119/wt`
- 分支：`feat/package-reject-migrations`，基线 SHA `5360129`（origin/main），提交 `00c772d`
- PR：**#116** https://github.com/supdger/sandadmin/pull/116 （未合并）

## 需求对应

计划项 1-12 / M-22：「没有迁移执行器：带 migrations 的包能 PASS，但表不存在」。验收口径：执行器没实现之前，安装器拒收带 migrations 的包。本实现把闸放在 `PackageArchive::inspect`（install/upgrade 共用 `applyPackage`，一处生效两边覆盖），拒收发生在任何文件系统写入之前（inspect 只读 zip 目录项，早于 extract）。

## 判定口径（任务要求写清）

1. zip 任一条目路径含 `migrations` 目录段即拒——**不限 payload 子树**（比任务示例的 `plugin/<app>/migrations/`、`server/migrations/` 更严：`docs/migrations/x.md` 也拒）。理由：执行器整体未实现，包里出现迁移内容没有任何合法去向，放行只是死文件；全路径段判定语义零歧义、无绕过面。
2. `manifest.json` 显式声明**非空** `migrations` 字段同样拒；空数组/null 视为未声明（`PluginPackageManifest::parse` 不消费未知字段，inspect 里单独 `json_decode` 看一眼）。
3. 文件名级不另设规则：`migrations.sql`、`0001_init.sql` 不在 migrations 目录段下不命中（与现有 BANNED_FILES 的 install.sql/update.sql/uninstall.sql 分工不变）。

## 结构化报告形状（偏差说明）

按建议方案执行：报告新增 `error_code` 字段（`?string`，取 `Sandadmin\Contract\Generated\ErrorCodeRegistry` 常量）。`PackageArchive::inspect` 返回同步新增 `error_code`（迁移拒收=`PLUGIN_MIGRATIONS_FORBIDDEN`，其余=null）；`PackageExecutor` 的 install/upgrade/uninstall/recover 四个入口报告统一带 `error_code`，`applyPackage` 在 inspect 失败分支透传。其余拒绝路径（gate/signature/journal/collect）为 `null`，成功报告为 `null`。`tools/package-ops` 只在失败输出追加一行 `error_code: XXX`，problems 打印不变。未选其他形状的理由：现有报告已是固定键结构，加可空键是最小侵入且 tools 只按 `problems` 消费、天然兼容。

## 改动文件

源码/工具：
- `server/src/Module/Package/PackageArchive.php` — entryProblem 增 `migrations` 段拒收；inspect 增 manifest migrations 字段检查与 `error_code` 返回；新增 `isMigrationsEntry()`。
- `server/src/Module/Package/PackageExecutor.php` — `report()`/`fail()` 增 `?string $errorCode`；applyPackage inspect 分支透传；recover() 手写数组补 `error_code => null`；全部 @return 形状更新。
- `tools/package-ops` — check 与 install/upgrade/uninstall 失败路径透出 `error_code`。
- `contract/openapi.yaml` — `x-error-catalog` 新增 `PLUGIN_MIGRATIONS_FORBIDDEN {http_status: 422, summary: 这个安装包带数据库迁移，迁移执行器上线前不能安装或升级}`；挂入 pluginsUpload / pluginsInstall / pluginsUpgrade 的 `x-error-codes`。
- 派生产物（全部 `php tools/contract-build` 生成，未手改）：`server/src/Contract/Generated/{ErrorCodeRegistry,McpToolRegistry,PermissionRegistry}.php`、`server/config/{route.generated.php,plugin-routes.generated.json,connection-budget.generated.json}`、`server/tests/Contract/Generated/*ContractTest.php`（12 个）、`web/src/api/v1/contract.generated.ts`。

测试：
- `server/tests/Unit/Module/Package/PackageArchiveTest.php` +6：payload 内 migrations 条目拒收、payload 外 migrations 段拒收、server/ 布局 migrations 拒收、manifest 非空 migrations 字段拒收、manifest 空数组放行、非迁移问题 error_code=null。
- `server/tests/Unit/Module/Package/PackageExecutorTest.php` +2：install 带 migrations 拒收（stage=inspect、error_code、assertNoSideEffects）、upgrade 带 migrations 拒收且已装 0.1.0 原样保留。

## 验证命令与结果

| 命令 | 退出码 | 关键输出 |
|---|---|---|
| `cd server && composer install` | 0 | 含 post-install `sand:publish` |
| `cd server && vendor/bin/phpunit --testsuite unit` | 0 | OK (745 tests, 6906 assertions) |
| `cd server && vendor/bin/phpstan analyse` | 0 | No errors（本机 php.ini memory_limit=128M 需 `--memory-limit=1G`，见偏差） |
| `php tools/contract-build` + `--check` | 0 | PASS 产物与契约源一致（20 个产物，101 个 operation） |
| `bash tools/pre-merge.sh --fast` | 0 | 8/8 全过：模块测试目录、语法、契约一致、危险函数、dev-only、TypePHP diff（FAIL=0）、PHPUnit Unit、PHPStan、命名自查 |
| `git diff -U0 HEAD \| python3 tools/naming_check.py code --diff -` | 0 | FAIL=0；WARN 2 条见下 |
| `git diff -U0 HEAD \| python3 tools/naming_check.py notes --diff -` | 0 | FAIL=0；WARN 1 条（注释占比）见下 |
| Integration 套件 | 未跑 | 需 `SAND_TEST_DB_URL` 指向 PostgreSQL，本任务未建库（授权边界内跳过） |

命名自查 WARN 逐条说明：
1. `code` WARN `空泛词 $dataRemoved`（PackageExecutor.php:418）：`report()` 既有参数名，报告键 `data_removed` 是既定契约形状不能改；本次只在该签名行尾追加 `?string $errorCode`，非新增命名。
2. `notes` WARN `注释占比 29.9%`（PackageArchive.php）：该文件本就是注释密集的安全边界代码（每条红线都带原因注释是仓内惯例），本次新增注释均为判定口径与拒因说明，属必要说明而非流水账。

## #109 清单（sandadmin1x-design-standard，54 条）逐项

涉及：
- **M3 升级回滚**：涉及——本改动是「迁移 expand/contract 通道未实现」期间的保守闸：拒收带 migrations 的包，避免装上却永远建不出表的假成功；不改 expand/contract 写法与回滚原则。
- **M5 数据迁移**：涉及——同上，划定迁移内容的准入边界，>100 万行在线变更通道落地前 migrations 一律不进包。
- **E2 插件隔离**：涉及——插件包能力边界检查：宿主不具备执行能力的内容（迁移）在安装闸被拒，属插件边界的组成部分。
- **Q3 契约**：涉及——新错误码只经 `contract/openapi.yaml` 的 `x-error-catalog` 登记，派生产物由 contract-build 生成并以 `--check` 门禁一致。

不涉及（按组归并，逐条）：
- D1–D6 数据层：不涉及，无表结构/连接/事务改动。
- H1–H5 高可用：不涉及，无部署与故障切换改动。
- A1–A8 异步并发：不涉及，无队列/锁/并发模型改动。
- C 组（契约/代码规范类细则）：不涉及，写法遵循既有 skill，无新机制。
- T1–T3 限流熔断超时：不涉及。
- S1–S2 存储：不涉及。
- X1 鉴权 / X2 审计 / X4 加密 / X5 合规：不涉及；X3 密钥不涉及——未动签名验签与密钥路径，仅透传既有 `signature_verified` 语义。
- O1–O4 可观测：不涉及。
- M1 备份恢复 / M2 容灾 / M4 灰度：不涉及。
- E1 租户隔离 / E3 国际化：不涉及；problems 文案沿用同文件既有中文硬编码惯例（面向 CLI 报告非 UI 文案），契约 summary 为中文与相邻 PLUGIN_* 条目一致。
- Q1 压测 / Q2 混沌：不涉及，本改动无运行时性能面。
- P1–P6 高性能：不涉及，inspect 仅在安装/升级命令路径执行，非请求热路径。

## 遗留风险 / 未完成项

1. **迁移执行器落地后需拆闸**：本次是过渡拒收。执行器实现时要同步移除 `entryProblem` 的 migrations 段拒收、manifest 字段检查、`PLUGIN_MIGRATIONS_FORBIDDEN` 的 x-error-codes 挂载（或改语义为「迁移失败」类），并更新本口径注释——建议在执行器任务的验收清单里显式列出。
2. **判定偏严**：payload 子树外的 `migrations` 目录段（如 `docs/migrations/`）也会拒。当前包格式下 payload 外内容本就不落盘，偏严无实际误伤；若未来允许包带非载荷附属目录需复核。
3. **manifest `migrations` 字段是预留判定**：sand-package 参考仓未见该字段的正式定义，本实现按「显式声明非空即拒」处理；若未来契约定义该字段语义，需对齐。
4. **HTTP 链路未实测**：executor 目前只被 CLI `tools/package-ops` 调用；`x-error-codes` 挂载是面向将来生命周期通道透传 error_code 的契约预留，本次未做 HTTP 端到端验证。
5. **环境偏差（已处理，提请知悉）**：
   - `composer install` 的 sand:publish 骨架覆盖了 `server/webman`、`server/windows.php`（丢 gssencmode 注入），已 `git checkout` 还原、未提交。
   - 本机 php.ini `memory_limit=128M` 导致 phpstan worker OOM；验证用 `PHP_INI_SCAN_DIR=/tmp/sandadmin-php-ini`（内含 `memory_limit=1G`）跑通 pre-merge，未改系统配置。
   - worktree 共享的本地 `main` 引用停在 67 个提交前的旧 SHA，导致 `typephp_check` 把历史提交的 `$resolve()` 行误判为新增 FAIL；已将本地 `main` 快进到 `origin/main`（纯祖先关系、无工作区影响）后 pre-merge 全绿。此改动影响共享 .git 的 main 引用，主检出不在 main 上、无工作区文件被触碰。
