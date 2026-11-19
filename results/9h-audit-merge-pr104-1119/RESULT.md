结论：可合并（内容审计）——审计时 head = 10ac4b562faf0d45f69ae0f4fef4d845e2f80164；但未执行合并：head 不以 8530ce2 开头，不满足合并前置条件。

# PR supdger/sandadmin#104 合并前独立审计（测试门禁 feat/test-gate）

## 合并判定
- 未合并。原因：任务规定只有 head 以 8530ce2 开头才合并；实际 head 为 10ac4b5（8530ce2 之后又推了一个提交 "test(gate): Unit 测试去库化，SQL 用例挪 Integration"）。
- 其余条件当时满足：gh pr checks 104 退出码 0，3 项（composer audit / frontend prod audit / release self-check）×2 次触发全部 pass；mergeable 复查结果 MERGEABLE / CLEAN（首次查询为 UNKNOWN，复查后确定）。
- 关键事实：8530ce2 时 Unit 套件中的 tests/Unit/Module/Menu/PluginMenuTest.php 还在用 pdo_sqlite 内存库（DriverManager::getConnection(['driver'=>'pdo_sqlite','memory'=>true])），不符合"Unit 测试不连任何库"的要求；去掉它的正是 10ac4b5。所以 8530ce2 本身达不到本次审计口径，审计通过的是 10ac4b5。
- 下一步要请人定：技术总监复验并认可 10ac4b5 后，按新 head 重新下发合并指令（合并条件里的 SHA 改为 10ac4b5）。

## 审计项
1. 范围与描述一致：基本一致。38 个文件，+3298/−39，未改 server/src、contract/openapi.yaml、.github/workflows、phpunit.xml.dist、phpstan 配置。描述里没提但影响很小的改动：web/vite.config.ts 中 vitest 排除 e2e/**、web/.gitignore 新增 2 行、tools/dev-only-paths.txt 新增 2 条剔除项（只在提交标题里出现）；另外约 1300 行 Integration 测试大部分是本 PR 新写的，正文没写明。
2. 门禁文件只减不增（豁免/白名单）：符合。没有新增任何豁免、白名单或 baseline 条目；check-module-tests.php 不带豁免机制。dev-only-paths.txt 的 +2 条（rm web/e2e、rm web/playwright.config.ts）是发布时剔除开发文件，属于收紧，不是门禁豁免。新增的 check-module-tests.php、check-diff-coverage.php、test-gate.json 都是新增约束；pre-merge.sh 中没有 || true / continue-on-error。
3. Unit 测试不连任何库（含 sqlite 内存库）：符合（10ac4b5）。在 head 上搜索 server/tests/Unit、phpunit.xml.dist、tests/bootstrap.php，关键词 sqlite|:memory:|pdo|DriverManager|getConnection|DB_|DATABASE_URL|pgsql|mysql|Doctrine|Connection|SAND_TEST_DB_URL，0 命中（evidence/db-hits-head-unit.txt 为空）。原来 Unit 里唯一的 sqlite 内存库用例 PluginMenuTest 已改名移到 Integration，改用 SAND_TEST_DB_URL（PostgreSQL）。带 Connection 的 LifecycleFixture / NotificationFixture 只被 Integration 引用。Unit 遇到仓储依赖时用 Disconnected 生成不带依赖的假实例，代码一旦碰到依赖就直接报错，不会去连库。
4. 覆盖率阈值 80%：符合。tools/test-gate.json 中 "diff_line_coverage_min": 80；check-diff-coverage.php 读这个值，读不到时默认也是 80.0；判定为 percent >= threshold，不达标退出码 1；接在 pre-merge.sh --full 上；调用 phpunit 时 suite 参数经过 escapeshellarg 转义。
5. 与《老板已定事项总表》不冲突：不冲突。L12"SQLite 不进服务端"——本 PR 把服务端测试里的 sqlite 去掉了，方向一致；L13"先按 PostgreSQL"——Integration 测试只用 PG，一致；L35"AI 改代码须自动测试，不过就不合并"——本 PR 就是落实这一条的门禁（但目前只在本地执行，见下方建议）。
6. 安全与正确性：没有阻断问题。web/e2e/menu-smoke.spec.ts 里写死了口令样字面量 'Admin@123456'，但只发给 msw mock 的登录接口，不对应真实凭证，风险低。

## 不阻断合并的建议（后续单独立项，本次按约束不改 CI 和门禁脚本）
- 新门禁只接在本地 tools/pre-merge.sh，CI 不跑 PHPUnit、覆盖率和冒烟测试。L35"不过就不合并"目前靠人工执行，CI 没有强制。
- 没设 SAND_TEST_DB_URL 时，--full 里的 Integration 用例全部 markTestSkipped，结果仍显示通过，容易误以为集成测试已跑过。
- 覆盖率默认只算 unit 套件；src 里只有 Integration 能覆盖的 SQL / 仓储改动行会拉低比例，可能达不到 80%，需要决定是否把 integration 套件也算进去。
- 阈值可以用 --threshold、基准可以用 --base / SAND_COVERAGE_BASE 在本地改；playwright 本地 reuseExistingServer 可能复用不是 mock 的 dev server。

## 证据
evidence/ 目录：pr.json、checks.txt / checks.exit、pr.diff、files.txt、gate-*.diff、db-hits-in-diff.txt、db-hits-head-unit.txt、suspicious-hits.txt、mergeable-recheck.txt。
提交：ee08b60cc4 → 8530ce26e5 → 10ac4b562f（head）。
