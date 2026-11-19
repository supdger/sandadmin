# U-6「AI 改代码闭环」无库部分 — RESULT

依据：顶层架构设计 v4 第 655 行 U-6 行、U-6 节第 2204–2247 行、第 524–531 行迁移口径；总表第 24 条「AI 改代码须自动测试，不过就不合并、打回重改，并保留可回退」。无库任务：全程未建库、未启数据库/Redis、无 DDL/迁移。

- 仓库：supdger/sandadmin（本机 `~/agent-work/pub-sandadmin`）
- 分支：`feat/ai-loop-rollback`，基线 SHA `977427acc285da074a443e8206309289a9bd94fb`（origin/main，PR #92 merge）
- 最终 SHA：`d3e4783`（提交序列：`907c0d1` 实现 → `d3e4783` 验收返修）
- PR：https://github.com/supdger/sandadmin/pull/110 （目标 main，未合并）

## 一、模块测试规范与样例

| 交付物 | 位置 | 说明 |
|---|---|---|
| Dict Unit 样例测试 | `server/tests/Unit/Module/Dict/DictServiceTest.php`（12 用例）+ `DictExceptionTest.php`（4 用例） | sqlite 内存库 + 最小建表；覆盖 source 三分类、used 三态、createType 写库+审计、删除门槛（系统/插件拒删、自建可删）、item 入参校验、is_default 排他、status 切换、排序、404。纯内存不连库，`--testsuite unit` 内运行 |
| 模块测试规范页 | `server/docs/module-tests.md` | 新模块必须带哪些测试：Unit/Integration 两目录分工、测试数据用工厂、命名规则、Unit 不连库 / Integration 需 `SAND_TEST_DB_URL`、改动行覆盖率 80% 起步（注明技术总监口径可再调）、契约权威归 `contract/openapi.yaml` |

注意：`server/src/Module/Dict` 业务代码未改一行。

## 二、pre-merge.sh × U-6 逐条对照

main 现有 7 步：①PHP 语法 ②契约一致性（contract-build --check）③危险函数扫描 ④dev-only 引用门禁 ⑤TypePHP diff 扫描 ⑥PHPUnit Unit ⑦PHPStan。

| U-6 / delivery-loop 要求 | 现状 | 处置 |
|---|---|---|
| 静态检查（语法、PHPStan level max） | ①+⑦ | 已覆盖 |
| 生成物与契约一致性 | ② | 已覆盖 |
| 安全回归（危险函数、dev-only 引用） | ③+④ | 已覆盖 |
| TypePHP 写法 | ⑤+`--typephp` 可选编译 | 已覆盖 |
| 单元测试 | ⑥ | 已覆盖 |
| 命名检查 | 缺 | **本 PR 已补**：diff 模式 `code`/`notes`/`terms` 三子命令，基线 `merge-base(origin/main,HEAD)`，无基线跳过；插在脚本末尾「全部通过」echo 之前，set -e 下失败即退，门禁效力等同步骤位 |
| 模块测试目录检查 + 改动行覆盖率 | 缺 | PR #104（feat/test-gate，未合并）已做，本任务不重复，避免同段冲突 |
| 接口测试骨架从 OpenAPI 生成 | 缺 | 已并入审计表 #5 另一任务 |
| 双库矩阵（PG + MySQL 8.0/8.4） | 缺（--full 仅 Integration） | 要库/CI，未做 |
| 迁移带回退 + 测试库「升级→回退→再升级」 | 缺 | 要库，未做（见剩余项） |
| 机器可读报告（JSON/SARIF） | 缺 | 列出未做，建议随 #104 的 `--json` 口径统一做 |
| 门禁自保护（CODEOWNERS、基线净增检出） | 缺 | 仓外 GitHub 设置，本任务禁碰 |
| 保留上一版可回退 | 缺 | **本 PR 已补**（见三） |

### 与 PR #104 的冲突处置（按任务要求写明）

- 首轮实现把命名自查插在 PHPStan 后并改了第 3–4 行用法注释，`git merge-tree` 与 `refs/pull/104/head` 实测 **CONFLICT**（#104 重编全部步骤号并改同一注释行）。
- 返修：头注释还原基线原文，命名自查块整体移到脚本末尾（#104 未动的语境），`git diff 977427a` 对 pre-merge.sh 为 **+17/−0 纯插入**；`git merge-tree --write-tree d3e4783 <pr104-head>` 输出干净树 `9f310ef…`，**无冲突**。
- 代价：新步无 `N/7` 编号位（守「不改既有行文」之约），位置在末尾而非步骤序列中；语义不变。

## 三、保留上一版与回退命令

### build-release.sh（+12 行）

打包成功后追加：`dist/releases.txt`（版本号、包名、sha256、构建时间，tab 分隔，追加不覆盖）；`dist/previous`（本版之前最后一个异版版本号；同版重建不自我覆盖，取追加本版行之前的最后异版）。不删除任何已产出 tar 包，天然满足「至少保留上一版」。

### tools/rollback-release.sh（新）

回退 = 切回上一版产物；**不碰数据库、不跑迁移回退、不启停服务**（结束提示按运维流程重启）。口径与 v4 一致：本版「先加」类迁移不回退，生产回滚只切旧制品 + 按需恢复备份。

```
tools/rollback-release.sh --root <部署根> [--dry-run]   # releases/<版本>/ + current 符号链接，原子重指上一版
tools/rollback-release.sh --dist [目录] [--dry-run]      # 默认 dist/：读 previous，校验 tar 存在 + sha256
                                                          # 与 releases.txt 最后一条登记一致，打印回退目标（不切换）
tools/rollback-release.sh --help
```

退出码：0 成功 / 1 拒绝（无上一版、目标缺失、sha256 不符、current 非指向 releases/ 的符号链接、已在上一版）/ 2 参数错误。

实现要点：`--root` 用 `ln -sfn` 临时链接 + PHP `rename(2)` 原子替换（绕开 macOS `mv` 跟随符号链接把链接移进目录的坑），失败清理临时链接；`--dry-run` 两条路径均零写入；`--dist` sha 取 releases.txt 中该版本**最后一条**登记（同版重建盘上 tar 对应末次构建）。

### 测试 `tests/rollback-release-test.sh`（14 用例，临时目录 fixtures）

覆盖：dry-run 零副作用、正常切换、无 previous 拒、目标目录缺失拒、dist sha256 不符拒、已在上一版拒、同版两条记录取最后一条、参数错误、非符号链接 current 拒等。

## 四、命令、退出码与关键输出

| 命令 | 退出码 | 关键输出 |
|---|---|---|
| `cd server && vendor/bin/phpunit --testsuite unit` | 0 | `OK (424 tests, 2662 assertions)`（含 Dict 16 用例，`--filter Dict` 单跑 16/49 assertions 通过） |
| `cd server && vendor/bin/phpstan analyse --no-progress` | 0 | `[OK] No errors`（level max） |
| `tools/pre-merge.sh --fast` | 0 | 7 步全过 + 末尾命名自查执行通过，`pre-merge --fast 全部通过` |
| `python3 tools/naming_check.py code …`（新文件） | 0 | 无 FAIL；首轮 WARN `$c` 单字母已改 `$itemC` 清零；notes 0（INFO 注释占比 1.8%）；terms 0 |
| `php tools/typephp_check.php` | 0 | diff 模式 `FAIL=0 WARN=0` |
| `bash tests/rollback-release-test.sh` | 0 | `通过 14 项，失败 0 项` |
| `tools/rollback-release.sh --dist <fixture> --dry-run` | 0 | 校验通过打印目标；目录树哈希前后一致（零副作用） |
| `tools/rollback-release.sh --help` | 0 | 用法正常，无杂行 |
| `tools/build-release.sh --self-check` | 0 | 完整剔除链+自检全过（未产制品）；releases.txt/previous 登记段以局部执行该段验证：连建两版后 previous=首版、releases.txt 两行带 sha256 |
| `git merge-tree --write-tree d3e4783 <pr/104/head>` | 0 | 干净树，无 CONFLICT |

两轮独立验收（dv-verifier-swe，未参与实现）：第一轮打回 4 处（pre-merge 注释与 #104 冲突、--dist sha 取首条、--help 泄漏 set -euo 行、临时链接残留）→ 全部修复 → 第二轮复核**通过**。

## 五、环境事实

- 本机 PHP 8.4（Homebrew）；Unit 套件不连库；shellcheck 本机未安装，以 `bash -n` + 实跑替代。
- 未跑：`--full` Integration 档（要库自动 skip，本任务禁起库）、`--typephp` 可选编译、`build-release.sh` 完整构建链（长，且产出物 dist/ 不入库）。

## 六、剩余项（U-6 闭环未收口部分）

- **迁移回退脚本与「升级→回退→再升级」验证**：要库，本任务未做，待有库任务承接（含删列删表「不可逆，靠升级前备份」声明、恢复点检查口径）。
- 双库测试矩阵（PG + MySQL 8.0 系末版 + 8.4 LTS）：要库/CI。
- pre-merge 机器可读报告（JSON/SARIF）：建议随 #104 `--json` 口径统一。
- 门禁自保护 CODEOWNERS / 基线净增检出 / ≥2 人审批：仓外 GitHub 设置，需老板或管理员操作。
- 回退的「按需恢复备份」与重启/重载服务属运维动作，本脚本只做制品切换并提示，发版手册与演练按 P-8 暂缓口径未展开。
- PR #110 待人工审批合并（AI 提的门禁改动不算自批）；合并顺序建议 #104 先或后均可（已验证无冲突，但两者都改 pre-merge.sh，后合者 rebase 一次更稳）。
