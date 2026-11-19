# RESULT — PackageOps 合并前审计 M1–M4 修复（PR #97）

- 时间：2026-11-19 PST；执行：本 Devin 会话（SWE-2），未开下级会话，未用 Cursor/云端。
- 仓库：`supdger/sandadmin`；工作树：`~/agent-work/assistant/2a-pkgops-m1m4-1119/sandadmin`（`pkgops-8809904-reverify-1118` 检出的 git worktree）。
- 分支：`fix/packageops-lifecycle`（PR #97 → `feat/kernel-api-runtime-align`）。
- `git fetch` 核实 `origin/fix/packageops-lifecycle = be4e7701309359c3a992b229d9801c8236dea176`，与任务给定头一致。
- 交付提交：`a506118cb7c6ec6c22ac4eb5d729403acd663f4c`，已普通推送（`be4e770..a506118`）。未合并、未发布、未打 tag。

## 改动清单（9 文件，+408/−43）

| 文件 | 改动 |
|---|---|
| `server/src/Module/Package/PackageExecutor.php` | M1(a)：构造器新增 `devBuildMarker`；install/upgrade/uninstall 闸改为 `locked()` = `PackageGate::compiled() \|\| !is_file(devBuildMarker)` |
| `tools/package-ops` | M1(a)：传 `repoRoot/server/.sand-dev-build`；M2：协议不支持只回显协议名 |
| `tools/dev-only-paths.txt` | M1(b)：加 `rm tools/package-ops`（剔除项，收紧） |
| `server/src/Module/Package/PackageJournal.php` | M3：`removeTree` 先判 `is_link`（目录本身与遍历项都是），链接只 `unlink` 不递归 |
| `server/src/Module/Package/DbalPluginRegistry.php` | M4：`recordUninstall(false)` 同事务内 `suspend`；`recordInstall` 同事务内 `resume`；purge 只删行（与 `PackageOps::apply` 逐行对齐） |
| `server/tests/Unit/Module/Package/PackageExecutorTest.php` | setUp 建标记文件；executor 传 markerPath；+4 用例 |
| `server/tests/Unit/Module/Package/PackageJournalTest.php` | +2 用例（removeTree 软链） |
| `server/tests/Unit/Module/Package/DbalPluginRegistryTest.php` | 新文件，sqlite 内存库，5 用例 |
| `server/tests/Unit/Module/Package/PackageOpsCliTest.php` | 新文件，子进程跑 CLI，1 用例 |

### M1 设计说明

发布制品仍含 `PackageExecutor.php` 且必须在制品里执行标记检查，但 `check-dev-refs` 门禁按 `str_contains` 扫 `.sand-dev-build`/`SAND_DEV_BUILD`/`DevBuild` 字面量——进制品的文件不许出现这些符号，而「门禁白名单只减不增」又不许为它开豁免。因此标记路径由调用方注入：`tools/package-ops`（经 M1(b) 登记为 rm 项、不受符号扫描约束）传 `$repoRoot/server/.sand-dev-build`，执行器用 `is_file` 判定，与 `bootstrap.php` 里 `SAND_DEV_BUILD` 常量的口径（同一标记文件存在性）一致；标记不在按编译版拒绝，`stage='gate'`、验签之前、零副作用。

## 补测与证据

### M1(a) 无标记拒绝（PackageExecutorTest +3 用例）

- `testMissingDevBuildMarkerRefusesUnsignedInstall`：无标记 + `SAND_COMPILED=0` + `allowUnsigned` → `failed`/`stage=gate`，`assertNoSideEffects`（无插件目录、账本无记录、work-dir 零残留）。
- `testMissingDevBuildMarkerRefusesSignedInstall`：无标记 + 合法 Ed25519 签名 → `failed`/`stage=gate`，`signature_verified=false`（闸在验签之前），零副作用。
- `testMissingDevBuildMarkerRefusesUninstall`：装好后删标记 → `failed`/`stage=gate`，插件目录与账本原样保留。
- 修前失败：三条用例针对的旧代码路径——`PackageGate::compiled()` 只看可伪造环境变量（旧 `applyPackage`/`uninstall` 唯一的闸），旧代码下 `SAND_COMPILED=0` 直接放行，用例必然失败；修后全部通过（见下）。

### M1(b) 发布剔除（build-release --self-check）

`tools/build-release.sh --self-check`（对已提交的 a506118 做 `git archive` → 剔除 → composer --no-dev → pnpm 构建 → 8 段自检）：

```
== 6/8 自检：清单路径不存在、无开发构建标记、无 .env/密钥残留
PASS 清单所列路径均不存在        # 含 rm tools/package-ops：发布树无 package-ops
PASS vendor 无非运行文件残留（豁免 11 项）
PASS 类映射 5045 条全部指向存在的文件
== 7/8 自检：PHP 语法与 dev-only 引用扫描
PASS 未发现未登记的 dev-only 引用（8086 个文件）   # 含 .sand-dev-build 字面量仅存在于被剔除的 tools/package-ops
== 8/8 自检：生产启动守卫路径（ProductionDebugGuard）
PASS 生产启动守卫自检通过
OK 自检通过（sandadmin-0.2.4-57-ga506118，未产出制品）   # 退出码 0
```

### M2 连接串不回显（PackageOpsCliTest +1 用例）

`tools/package-ops` L150：`{$dbUrl}` 改为只回显 `$scheme`（无法解析时固定文案「无法解析」）。子进程用例置 `SAND_DB_URL=oracle://deploy:s3cr3t-word@db.internal:1521/app` 跑 `install`，断言退出码 2、stderr 含「协议不支持：oracle」、不含 `s3cr3t-word`/`deploy`/`db.internal`。修前该行回显整条连接串，口令必进 stderr，用例必然失败；修后通过。

### M3 符号链接（PackageJournalTest +2，PackageExecutorTest +1）

- `PackageJournal::removeTree`：`$dir` 本身是链接只 `unlink`；遍历项 `is_link || !is_dir` 一律 `unlink`，真目录才递归。
- `testRemoveTreeUnlinksSymlinksWithoutFollowing`：树内放指向外部的目录软链、文件软链、悬空软链 → 树删净、外部原样。
- `testRemoveTreeOnSymlinkOnlyRemovesLink`：对链接本身调用 → 只删链接。
- `testUninstallKeepsExternalSymlinkTarget`（端到端）：装插件后在其目录放指向外部目录的软链，卸载 → 外部 `keep.txt` 原样。修前 `removeTree` 对软链 `is_dir` 为真、递归删掉外部内容后 `rmdir` 链接失败，用例必然失败；修后通过。

### M4 残留 suspend/resume（DbalPluginRegistryTest 新文件，5 用例，sqlite 内存库执行真实 SQL）

- `recordInstall`：插入/更新行与 `PluginResidue::resume` 在 `connection->transactional` 内；`recordUninstall(false)`：UPDATE 与 `suspend` 同事务；`recordUninstall(true)`：DELETE 即返（purge 不 suspend，与 apply 一致，残留靠级联）。
- 用例：装→行 inserted；keep 卸→`state=uninstalled` + schedule/extension `suspend_time` 置位（2 行）；重装→`suspend_time` 清零；purge→行删除且无 suspend；残留表缺失时 `recordUninstall` 抛错且账本行回滚保持 `installed`（同事务证据）。
- 仍未验证：真实 PostgreSQL/MySQL 上 `sand_system_schedule`/`sand_system_plugin_extension` 实际 schema 的端到端联动（列名取自 `ScheduleClaim::TABLE`/`PluginExtensionRepository::TABLE` 与 PluginResidue 的 SQL，sqlite 用最小同名列验证）；与 `PluginResidueTest` 同口径的 Integration 用例留到有真实库（`SAND_TEST_DB_URL`）时补——`--full` 模式会跑 Integration 套件，本任务未启库。

## 门禁结果（逐条）

| 命令 | 退出码 | 结果 |
|---|---|---|
| `cd server && vendor/bin/phpunit --testsuite unit` | 0 | `OK (517 tests, 3024 assertions)` |
| `cd server && vendor/bin/phpunit --testsuite contract` | 0 | `Tests: 248, Assertions: 482, Incomplete: 101`（Incomplete 为既有的需库跳过项，无失败） |
| `cd server && vendor/bin/phpstan analyse --no-progress` | 0 | `[OK] No errors` |
| `php tools/contract-build --check` | 0 | `PASS 产物与契约源一致` |
| `tools/pre-merge.sh --fast` | 0 | 7/7 全过（语法、契约、危险函数、dev-only 引用、TypePHP diff、unit、PHPStan），末行 `pre-merge --fast 全部通过` |
| `git diff --cached -U0 \| naming_check.py code --diff -` | 0 | 0 FAIL 0 WARN（无输出即通过） |
| `git diff --cached -U0 \| naming_check.py notes --diff -` | 0 | INFO 注释占比 9.2%（<15% 阈值，无需逐条复核） |
| `php tools/typephp_check.php` | 0 | `计数 模式=diff FAIL=0 WARN=0 豁免=0` |
| `tools/build-release.sh --self-check` | 0 | 8/8 自检通过，`tools/package-ops` 不在发布树（清单 verify PASS） |

## 仍未验证部分

- **真实库**：`DbalPluginRegistry` 的新 SQL 序列（UPDATE+suspend / UPDATE+resume 同事务）未在 PostgreSQL/MySQL 上执行；sqlite 内存库验证了 SQL 形状与事务边界，双库矩阵端到端（CLI 走真实账本的装/升/卸/回滚）按审计风险说明留待 `SAND_TEST_DB_URL` 可用的 Integration 轮次。
- **Integration 套件**：`--testsuite integration` 未跑（任务禁止启库）；`PluginResidue` 与 CLI 账本联动的 Integration 用例待补。
- **系统级演练**：`php tools/package-ops` 在无 `.sand-dev-build` 的剔除树上实跑 `install` 的端到端拒绝未单独执行（单测已覆盖闸逻辑，self-check 已证明制品无标记亦无工具）。
- CI 无 PHPUnit/PHPStan job（仓库现状，#81 merge-gates 待批准），以上均为本地复跑证据。

## 边界声明

- 已修改：上列 9 文件；未碰 sand-package、sand-core、sand-workflow、`.env`、私有目录；未建库/迁移/DDL；未合并/发布/tag；验签规则未动；门禁清单只加 `rm` 剔除项。
- `composer install` 的 `sand:publish` 钩子曾重写 `server/webman`、`server/windows.php`（与任务无关），已 `git checkout` 还原后提交。
- worktree 留在任务目录供复核；dist/ 已由 --self-check 清理。
