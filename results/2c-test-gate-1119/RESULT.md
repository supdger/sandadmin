# 1.x 框架测试判定口径（独立审计新增表 #12）— RESULT

依据：顶层架构设计 v4 第 1153 行 / U-6 第 2208 行（M-11 其余、S-15）。无库任务：全程未建库、未启数据库/Redis、无 DDL/迁移。

- 仓库：supdger/sandadmin（本机 `~/agent-work/pub-sandadmin`）
- 分支：`feat/test-gate`，基线 SHA `977427acc285da074a443e8206309289a9bd94fb`（origin/main，PR #92 merge）
- 最终 SHA：`ee08b60cc48fcd388e035eb124b772a8186e1b31`
- PR：https://github.com/supdger/sandadmin/pull/104 （目标 main，未合并）

## 四项实现位置

| 项 | 位置 | 说明 |
|---|---|---|
| 1 模块测试目录检查 | `tools/check-module-tests.php` | `server/src/Module/<名>` 必须同时在 `server/tests/Unit/Module/<名>` 与 `server/tests/Integration/Module/<名>` 存在含 `*Test.php` 的目录；`--json` 机器可读报告（M-09）；退出码 0/1/2 |
| 2 改动行覆盖率 | `tools/check-diff-coverage.php` + `tools/test-gate.json` | 只对 `git diff -U0 <base> -- server/src` 新增行 + 未跟踪文件全行计算；pcov 优先、xdebug 兜底、都没有退出 3；分母只算可执行改动行（clover stmt 行） |
| 3 测试数据工厂 | `server/tests/Support/Factory/` | UserFactory、DeptFactory、RoleFactory、AnnouncementFactory、AuthenticatedUserFactory、DemoNoteFactory；Unit 可用、纯内存不连库；自测 `server/tests/Unit/Support/Factory/`（2 类 5 用例） |
| 4 菜单页 Playwright 冒烟 | `web/playwright.config.ts` + `web/e2e/menu-smoke.spec.ts` | dev 服务器 + msw（`VITE_ENABLE_MOCKS=true`），端口 19100（约定 19100–19199 段内）；mock 登录 admin 后按 `/me/menus` 枚举菜单页逐个打开，断言无 console error/pageerror、无空白、无「页面不存在/没有权限/出了点问题」错误态、加载收敛 |
| 5 pre-merge 接入 | `tools/pre-merge.sh` | `--fast` 第 1/8 步跑模块检查；`--full` 在 Integration 后追加覆盖率门禁；冒烟单列 `cd web && pnpm test:smoke`（脚本头注释已注明） |

配套小改：`web/package.json`（`test:smoke` 脚本 + `@playwright/test@1.65.0`，2026-11-05 发布）、`web/pnpm-lock.yaml`、`web/vite.config.ts`（vitest exclude `e2e/**`）、`web/.gitignore`（`test-results/`、`playwright-report/`）。

## 现有缺测试模块清单（不加豁免）

`server/src/Module` 共 8 个模块，Integration 目录齐备，Unit 目录缺 4 个：

| 模块 | `tests/Unit/Module/<X>` | `tests/Integration/Module/<X>` |
|---|---|---|
| Dict | **缺失** | 有（DictTypeRemoval/DictTypeSource） |
| Lifecycle | **缺失** | 有（LifecycleHttpTest） |
| Notification | **缺失** | 有（NotificationHttpTest） |
| Package | **缺失** | 有（PackageContractLifecycleTest） |

按口径该门禁使主干 `--fast` 在第 1 步即失败——已停下列清单，未加任何豁免/白名单。

## 阈值起步值

`tools/test-gate.json` → `diff_line_coverage_min: 80`（v4 U-6「建议 80%，已定技术总监口径可再调」；**写 80 待总监确认**，改配置即生效，`--threshold` 可临时覆盖）。

## 命令、退出码与关键输出

| 命令 | 退出码 | 关键输出 |
|---|---|---|
| `tools/pre-merge.sh --fast` | **1** | `== 1/8 模块测试目录检查` → 列上表 4 缺口，`共 4 处缺口`，即停（口径预期） |
| `tools/pre-merge.sh --full` | **1** | 同上，第 1 步即停 |
| `php tools/check-module-tests.php` / `--json` | 1 / 1 | `模块测试目录检查：8 个模块`，gaps JSON 4 条（module/suite/expect/reason=missing_dir） |
| `vendor/bin/phpunit --testsuite unit` | 0 | `OK (413 tests, 2627 assertions)`（含新工厂自测 5 用例） |
| `vendor/bin/phpunit --testsuite integration` | 0 | `OK, but some tests were skipped! Tests: 153, Skipped: 125`（无 `SAND_TEST_DB_URL` 自动跳过，未连库） |
| `vendor/bin/phpstan analyse --no-progress` | 0 | `[OK] No errors`（level max，含 tests/ 新文件） |
| `php tools/typephp_check.php` | 0 | `计数 模式=diff FAIL=0 WARN=0 豁免=0` |
| `php tools/check-diff-coverage.php`（本分支） | 0 | `server/src 相对 977427a… 无改动，跳过`（本 PR 未改 src） |
| `php tools/check-diff-coverage.php --base origin/main~15` | 1 | `合计 1776/3043 = 58.36%（阈值 80%）未达标`——pcov 全管线实测正确判失败 |
| `pnpm test`（vitest） | 0 | `Test Files 124 passed；Tests 530 passed \| 2 skipped` |
| `pnpm typecheck`（tsc --noEmit） | 0 | 无输出 |
| `pnpm lint`（eslint） | 0 | 无输出 |
| `pnpm test:smoke`（Playwright） | 0 | `ok /`、`ok /system/user`…`ok /plugin/sand-hello`，13/13，`1 passed (17.2s)` |
| `naming_check.py code`（全部新/改文件） | 0 | 无 FAIL；2 条 WARN：`$dataScope`、`$updatedBy`——与实体构造参数/列名 `data_scope`、`updated_by` 契约字段一致，保留原名（规范 §一：契约优先） |
| `naming_check.py notes` | 0 | 无 FAIL/WARN，仅 INFO（注释占比 4.6% php / 9.6% ts） |

其他环境事实：本机 PHP 8.4.18（Homebrew，NTS），原无 pcov/xdebug——已用 `pecl install pcov` 装 pcov 1.0.12 完成覆盖率实测；`@playwright/test` 浏览器 chromium-1254 已装。

## 剩余项

- **主干 `--fast` 当前必然失败**（4 个模块缺 Unit 测试目录）：补测是各模块自己的任务，不在本单范围；本单只把门禁立起来并如实报缺口。
- **覆盖率阈值 80 待总监确认**；`--full` 的 Integration 段本次无库全部 skip，覆盖率跑的是 Unit 套件——是否换/加 suite 也留总监口径（`--suite` 参数已留）。
- 覆盖率改动行口径：merge-base(`HEAD`,`origin/main`) 起 diff + 未跟踪文件；`SAND_COVERAGE_BASE`/`--base` 可覆盖。
- pcov 是本地验证依赖：跑 `--full` 的覆盖率段需要 `ext-pcov` 或 `ext-xdebug`，脚本在无驱动时以退出码 3 明示（本机已装 pcov 验证通过）。
- 菜单页冒烟当前为单条串行用例（13 页约 17s）；「打开、列表、新建、保存」四步中的后三步未在本单范围（本单按任务口径只做打开+无报错+无空白）。
- Playwright 冒烟未接 CI（本单明确不改 CI）；本地命令 `cd web && pnpm test:smoke`。
- 命名 WARN 2 条如上表说明；门禁白名单/基线零新增，只减不增。
