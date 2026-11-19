# RESULT — 再修 PR #104 测试门禁

仓库：`~/agent-work/pub-sandadmin`，分支：`feat/test-gate`（远端 `https://github.com/supdger/sandadmin.git`）。
最终提交：`10ac4b562faf0d45f69ae0f4fef4d845e2f80164`（`test(gate): Unit 测试去库化，SQL 用例挪 Integration`）。

## 一、打回点 1：8530ce2 上推

- `git fetch origin feat/test-gate`：远端在 `ee08b60`，`8530ce2` 仅本地存在。
- `git push origin feat/test-gate`（普通推送，未强推）：`ee08b60..8530ce2`。完整 SHA `8530ce26e5b0ddc992a5bf1a06c951dacc62e788`。
- `gh pr view 104`（只读）：headRefOid = `8530ce26…`，state = OPEN。

## 二、打回点 2：Unit 去库化

改法（最终提交 `10ac4b5` 内）：

- 新增 `server/tests/Support/Disconnected.php`：`ReflectionClass::newInstanceWithoutConstructor()` 建免构造实例，Unit 只测入库前的校验/鉴权/提前返回分支；一旦误触未初始化的 Connection 属性会直接报错，比静默通过更安全。
- 改写 Unit（去掉 `DriverManager`/`pdo_sqlite`/建表/夹具，全部只用 `Disconnected`）：
  - `tests/Unit/Module/Dict/DictServiceTest.php`——只留入库前输入校验。
  - `tests/Unit/Module/Lifecycle/LifecycleHttpTest.php`——游客/非法 UUID/失败信封。
  - `tests/Unit/Module/Notification/NotificationHttpTest.php`——游客/非法 ID/非法幂等键/失败信封。
  - `tests/Unit/Module/Package/PackageOpsTest.php`——概览标志、操作未开启提前返回、鉴权门禁、compiled/未开启门禁、advance 前置分支。
- 挪到 Integration（连 `SAND_TEST_DB_URL` 的 PostgreSQL，用真实列，不建表）：
  - `Integration/Module/Dict/DictServiceTest.php`（字典增删改查 + 引用计数删除保护）
  - `Integration/Module/Lifecycle/LifecycleRepositoryTest.php`（由 Unit 原文件改真实连接搬入）
  - `Integration/Module/Notification/NotificationRepositoryTest.php`、`NotificationWriteTest.php`
  - `Integration/Module/Package/PackageOpsTest.php`（含 PG 方言：enqueue `RETURNING public_id::text`、CAST jsonb、updateStatus、advance 阶梯）
  - `Integration/Module/Menu/PluginMenuTest.php`（同口径搬移）
- 夹具改造：`LifecycleFixture`、`NotificationFixture` 不再建表，按基线迁移真实列插行（`RETURNING id`、`ParameterType::BOOLEAN`）。
- Unit/Menu 空位补 `MenuExceptionTest.php`（纯异常工厂）。
- `PackageGateTest.php` 未动（`git diff 8530ce2 -- .../PackageGateTest.php` 为空）。
- 验证 Unit 无库：`grep -rln 'sqlite|pdo_sqlite|DriverManager|PDO' tests/Unit tests/Support` → 无匹配。

## 三、验证记录（命令 / 退出码 / 关键输出）

| # | 命令 | 退出码 | 关键输出 |
| --- | --- | --- | --- |
| 1 | `php -l` × 14 个改动/新增文件 | 0 | 全部 `No syntax errors` |
| 2 | `cd server && vendor/bin/phpunit --testsuite unit` | 0 | `OK (448 tests, 2752 assertions)`，0.4s |
| 3 | `cd server && vendor/bin/phpstan analyse --no-progress` | 0 | `[OK] No errors`（首轮 11 错已修：ownTypeItems docblock、assertContains 第三参误用 → `in_array` 严格比较） |
| 4 | `php tools/check-module-tests.php` | 0 | `全部模块在 Unit 与 Integration 套件下均有测试目录`（8 模块） |
| 5 | `tools/pre-merge.sh --fast` | 0 | 8/8 步通过：`pre-merge --fast 全部通过`（含模块目录检查、dev-only 扫描 FAIL=0、Unit 448 绿、PHPStan 绿） |
| 6 | `tools/build-release.sh 1.0.0-rc.test --self-check` | 0 | 第 6 步 `自检：清单路径不存在、无开发构建标记、无 .env/密钥残留` → `PASS 清单所列路径均不存在` / `PASS vendor 无非运行文件残留（豁免 11 项）` / `PASS 类映射 5030 条`；全程 `OK 自检通过（未产出制品）` |
| 7 | `naming_check.py code <14 个文件>` | 0 | 无输出（PASS，无 FAIL/WARN） |
| 8 | `git push origin feat/test-gate` | 0 | `8530ce2..10ac4b5 feat/test-gate -> feat/test-gate` |
| 9 | `git ls-remote origin feat/test-gate` | 0 | `10ac4b562faf0d45f69ae0f4fef4d845e2f80164` |
| 10 | `gh pr view 104`（只读） | 0 | number=104, state=OPEN, headRefName=`feat/test-gate`, headRefOid=`10ac4b562faf0d45f69ae0f4fef4d845e2f80164`（推送后首次查询曾因 API 延迟显示旧 head，复查已更新） |

## 四、未做的事（按约束）

- Integration 套件未本地执行：`SAND_TEST_DB_URL` 未设置，且任务禁止建库/启库；挪入的 SQL 用例等建库放行后再跑。
- PR 未合并；CI/门禁脚本、豁免/白名单未改；未强推/未 reset --hard/未 --no-verify/未 git add -A。
- 推送 ≠ 合并；`gh pr view` 显示 PR 仍 OPEN，远端 CI 结论以 GitHub 为准。
