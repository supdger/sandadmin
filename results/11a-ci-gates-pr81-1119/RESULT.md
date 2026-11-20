# 11a HR3-CI + M-27 — RESULT

**载体**：PR #81 `feat/merge-gates`（supdger/sandadmin）
**交付 SHA**：`f5c35c438270623a7764c823c333ef72014b7809`（merge main `5360129` → merge commit `8899c69`，后续提交 `f643936`、`29d4e78`、返工修复 `f5c35c4`；普通 push，无强推，旧 head `1bc282f` 在历史中）
**状态**：#81 OPEN 未合并（合并与分支保护 HR3-SET 另排）；独立验收 21 项全 PASS。

## Job / check 清单（check 名一字不差）

| workflow | job key = name（check 名） | 必过 | 命令 |
|---|---|---|---|
| merge-gates.yml | `phpunit` | 是 | `cd server && composer install && vendor/bin/phpunit --testsuite unit,contract` |
| merge-gates.yml | `phpstan` | 是 | `vendor/bin/phpstan analyse --no-progress`（level max，phpstan.neon.dist，无 baseline） |
| merge-gates.yml | `security-regression` | 是 | `phpunit --testsuite unit --filter 'SecurityHeadersMiddlewareTest|CsrfMiddlewareTest|CorsMiddlewareTest|SecurityConfigFileTest'` + `php tests/security/ProductionDebugTest.php` + `tools/assert-security-headers.sh`（真实起 webman 断言 7 项安全头，job 内设 SAND_IDGEN_* 测试 env） |
| dependency-audit.yml | `dependency-audit` | 是 | composer validate --strict --no-check-publish；composer audit --locked；php bin/ci/check-release-tags.php；git ls-files 拒收 vendor；sandadmin-artd pnpm audit --prod；tools/build-release.sh --self-check；生成 SBOM/provenance；upload-artifact `release-metadata` |
| merge-gates.yml | `pre-merge`（显示名「快档：pre-merge --fast（本地同一套门禁）」） | 非四必过名之一，但同样拦截 | `tools/pre-merge.sh --fast`（快档与脚本同一套命令） |
| merge-gates-slow.yml | `phpunit`（三库矩阵 pg16/mysql8.0/mysql8.4）、`e2e-slot` | 否（不挂 PR；mysql 两档与 e2e 为 continue-on-error 已知槽位） | 全量 phpunit 连库矩阵 / e2e 存在性断言 |

慢档中与 dependency-audit 重复的 composer-audit、frontend-prod-audit 两个 job 已删除。全部 `setup-php` = **8.4**（含慢档）。

## M-27

- `server/composer.json`：`require.php` = `">=8.4"`，`config.platform.php` = `"8.4.0"`。
- `composer.lock`：以 #81 lock 为底 `composer update --lock` + 定向 `composer update symfony/yaml`（最小 diff），platform `>=8.4` / overrides `8.4.0`；`composer validate --strict`、`check-platform-reqs` 均 0。
- CI 无 8.2 残留（lock 内 `>=8.2` 均为依赖包自身约束）。

## 冲突处理（3 个冲突文件）

- `dependency-audit.yml`：重建为单 job `dependency-audit`（main 三 job 并集 + SBOM/provenance + artifact），`name:` 与工作流同名对齐。
- `composer.lock`：见上。
- `tools/pre-merge.sh`：保留 #81 重写结构，恢复 main 被静默删掉的三道门禁——`check-module-tests.php` 与 `tools/contract-build --check` 进 `--fast`，`check-diff-coverage.php` 进 `--full`。

## 门禁卫生

- `tools/strict-types-baseline.txt` 29 行未动（任务单写 46 条，实际 29 行/23 条有效条目，以文件为准）；只减不增闸已修复 bootstrap 死逻辑（原判据 grep BASE 侧 workflow 的 `baseline-shrink` 永不命中 → 改为 grep `BASE:tools/pre-merge.sh` 的 `strict-types-baseline`，合并后增长即 hard fail，两种情形均实跑验证）。
- 未新建 `phpstan-baseline.neon`；phpstan level max 下修的代码：`PdoTaskStore`/`TaskProgress`/`FakeTaskStore`（PHP 8.4 `modify()` 改抛 `DateMalformedStringException`）、`PackageArchive::extract→unpack`（命中 extract( 扫描）、`bin/migrate.php` strict_types 声明位置。
- 各 allow 文件无新增（dev-only-paths.txt +5 行系 main 版原文由 merge 带入，与 main 逐字节相同）。

## 发版产物

- 新增 `tools/build-release-metadata.php`：`sandadmin-<版本>.sbom.json`（CycloneDX 1.5，160 components）+ `sandadmin-<版本>.provenance.json`（SLSA-lite）；`--self-check` 与正式构建两路均产出，正式包 subjects 含 tar.gz sha256。
- `dependency-audit` job 上传 artifact `release-metadata`（已确认存在）。
- `RELEASE-CHECKLIST.md`：写入四个 check 名、产物名、artifact 名、M-27 核对项（require.php >=8.4 + platform 8.4.x）。

## 本地验证（PHP 8.4.18，无库）

| 命令 | 退出码 |
|---|---|
| composer validate --strict / check-platform-reqs / install | 0 / 0 / 0 |
| vendor/bin/phpstan analyse --no-progress | 0（修前 5 错） |
| vendor/bin/phpunit --testsuite unit | 0（737 tests） |
| vendor/bin/phpunit --testsuite contract | 0（253 tests，实测无库 → 纳入 phpunit job） |
| tools/check-strict-types.sh | 0（修前 4 条，补 strict_types 声明） |
| php tests/security/ProductionDebugTest.php | 0 |
| tools/assert-security-headers.sh | 0（21 断言，无库可跑 → 进 CI） |
| tools/build-release.sh --self-check | 0 |
| tools/pre-merge.sh --fast | 本地 1（typephp_check diff 命中本地过期 main 基线噪音，35 条与 PR diff 交集为 0；CI 该步绿） |

## CI 结果（#81 @ f5c35c4，push + pull_request 双跑）

`phpunit`、`phpstan`、`security-regression`、`dependency-audit`、`快档：pre-merge --fast` —— 全部 SUCCESS。

## 红 PR 演示

https://github.com/supdger/sandadmin/pull/112 —— `phpstan` check = **FAILURE**（`function.notFound`，18s 判红；`pre-merge` 快档同步判红），其余 check 绿。已关闭，远端/本地临时分支已删。

## 遗留风险（不阻塞本切片）

1. vitest 偶发未捕获异常（`DeptsPage.test.tsx` semi-ui Tooltip 异步竞态，main 带入的既有测试），重跑即绿，未来可能偶发再红。
2. `--full` 档 `check-diff-coverage.php` 只挂 `--full` 不进快档 CI，真实口径未验证。
3. `IdGeneratorGuard::start` 的 `fwrite(STDERR)` 在 daemon 下可能抛 TypeError 掩盖启动错误，建议后续修。
4. baseline 闸在「BASE ref 存在但 pre-merge.sh blob 缺失」边角下 fail-open（残余低风险，验收记录在案）。
