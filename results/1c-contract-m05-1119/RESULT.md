# RESULT — 1c-contract-m05-1119（契约套件命名连接四规则 M-05）

- PR：https://github.com/supdger/sandadmin/pull/101（`feat/contract-naming-m05` → main，未合并）；基线 origin/main `977427acc285da074a443e8206309289a9bd94fb`，最终 commit `320de4b1049efefeb2869a8ebc7ad38bf0cef558`
- 验证全绿：phpunit unit 429 测 / contract 242 测 / NamedConnectionTest 19 测 0 失败，PHPStan max 0 错误，`contract-build --check` PASS，`pre-merge --fast` 7/7，两个 naming_check --diff FAIL=0
- 独立验收（dv-verifier-swe，未参与实现）：**通过**，7 条款逐项核对含反证实跑
- 现有插件契约违规：**无**（仓内 server/plugin/* 无契约片段；sand-workflow 片段未声明命名连接，kernel_api 检查 PASS）
- 遗留：规则 1-3 落为「声明字面量校验」，运行期行为（执行器逐本记账、relay 按连接拉取、审计异步落库）需在内核实现落地时另行验收；规则 4 落为预算输入透出，阈值核对属容量评审

## 一、四条规则来源（先定位，未自编）

权威定义：`~/agent-work/grokbot/audit-fable-1117/遗漏.md:47-53`（2026-11-17 独立审计新增表，严重度：必改）。四条规则原文（遗漏.md:52，按分号拆条）：

1. 「一套执行器，每个连接一本同结构账本（宿主库一本，`sandai` 库一本），执行器统一管理，迁移锁按连接分别加」
2. 「outbox 表随业务库走，relay 按连接登记、分别拉取」
3. 「审计走事件或队列异步落内核，调用记录本身留在 SandAI 库」
4. 「J-4 预算公式加上『插件命名连接数』，并重算首期账」

细化出处：`~/agent-work/grokbot/review-1117-r3/顶层架构设计-v4.md` :33（压缩定义）、:1147（审计新增表 #6 行）、:217（账本细化「连接独立=账本独立」）、:1290-1291（J-4 公式 = 进程数×(宿主连接数+命名连接数)，SandAI 48×(4+2)≈288 例与三场景门禁值）、:1378（outbox 建业务连接库、relay 按「连接+插件命名空间」记账、审计事件经队列异步落内核审计面）；`~/agent-work/grokbot/audit-fix-1117/差异表-audit.md:17`（M-05 条目，审计落点挂钩 AuditSinkInterface）、:96（B1 门禁口径）。仓内 skill 部分口径：`.agents/skills/sandadmin1x-backend/references/database.md:97`、`sandadmin1x-plugin/references/contract.md:107`、`sandadmin1x-plugin/SKILL.md:24`。

`老板已定事项总表.md`（Mac 实际路径 `~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md`）无 M-05 相关条目。

## 二、实现位置（规则 → 代码映射）

设计：契约片段新增**插件专用**顶层 `x-connections` 段（宿主契约出现即打回）；每条命名连接声明五个键，`outbox`/`audit_sink` 取 `none` 表示「声明不用」且伴随键禁写——未声明命名连接的插件零误报。校验在 `ContractDoc::parseConnections`，经 `FragmentCheck`/`checkKernelApi`/`collectPlugins`/`--check` 全链生效。

| # | 规则原文 | 实现位置 | 检查逻辑 |
|---|---|---|---|
| 1 | 每连接一本同结构账本、锁按连接加 | `server/src/Contract/Build/ContractDoc.php` `parseConnections`/`expectFixed`（约 :654） | `ledger` 必填且只能 `per_connection`，声明私有/宿主台账即构建失败 |
| 2 | outbox 随业务库、relay 按连接登记 | `ContractDoc.php`（约 :655-659） | `outbox` 必填限 `business_database`/`none`；用时 `outbox_relay` 必填且只能 `per_connection`，none 时禁写 |
| 3 | 审计异步落内核、调用记录留 SandAI 库 | `ContractDoc.php`（约 :660-664） | `audit_sink` 必填限 `kernel_async`/`none`；用时 `audit_records` 必填且只能 `local`，none 时禁写 |
| 4 | 预算公式加「插件命名连接数」 | `ContractDoc.php`（约 :677-686）+ `PhpEmit.php` `emitConnectionBudget`（:36-56，`HOST_PER_PROCESS=4`）+ `ContractBuild.php`（:90-98,:118-119） | `connections_per_process` 必填 ≥1 整数；产物 `server/config/connection-budget.generated.json` 透出 host_per_process=4 + 逐插件明细 + 汇总，进 `GeneratedIntegrity` 启动校验与 `--check` 逐字节比对；报告加 `named_connections` |

辅助校验：连接名小写蛇形/片段内唯一/不占 `default|pgsql|mysql` 宿主连接名；未识别键打回；宿主契约出现 `x-connections` 打回。

## 三、改动文件（10 个，diff +682/-3）

- `server/src/Contract/Build/ContractDoc.php`（+177：`ConnectionSpec` 类型 + 4 个解析校验方法）
- `server/src/Contract/Build/PhpEmit.php`、`ContractBuild.php`、`GeneratedIntegrity.php`（默认校验清单加预算产物）
- `contract/api-conventions.md`（顶层扩展段清单 + 第 6 节 `x-connections` 规范——验收裁定：契约扩展的人读文档同步，属必要同步未越界）
- `server/tests/Unit/Contract/NamedConnectionTest.php`（新，19 例 334 行）
- `server/tests/Unit/Contract/CollectPluginsTest.php`（+2 例）、`server/tests/Contract/KernelApiTest.php`（+2 例）
- `server/tests/Fixtures/plugin/sand-hello/contract/openapi.yaml`（合规样例：hello_pg 全量 + hello_ro 双 none）
- `server/config/connection-budget.generated.json`（新产物，contract-build 生成）

`contract/openapi.yaml` 零改动；白名单/PHPStan 基线/CI workflow 零改动；其余既有产物零漂移。

## 四、用例清单

- **通过用例**：全合规单连接、双 none 声明、未声明 x-connections、sand-hello 夹具端到端（KernelApiTest/CollectPluginsTest）。
- **失败用例**（各一条断言）：`ledger: private`、outbox 落内核库、outbox 用时缺 relay、none 带 relay、`audit_sink: sync_table`、审计用时缺 records、none 带 records、缺/零 `connections_per_process`、占用宿主连接名 `pgsql`、片段内重名、非法名 `Sand-AI`、未识别键、x-connections 非 list、宿主契约声明。

## 五、命令、退出码与关键输出

| 命令 | 退出码 | 关键输出 |
|---|---|---|
| `php tools/contract-build` | 0 | PASS（20 产物，100 operation；2 条 legacy 插件 warning 为既有口径） |
| `php tools/contract-build --check` | 0 | PASS 产物与契约源一致（验收者独立重跑同过） |
| `vendor/bin/phpunit --testsuite unit` | 0 | OK（429 tests, 2665 assertions） |
| `vendor/bin/phpunit --testsuite contract` | 0 | OK（242 tests, 461 assertions；100 incomplete 为既有 TODO 占位，与本改动无关） |
| `vendor/bin/phpunit tests/Unit/Contract/NamedConnectionTest.php` | 0 | OK（19 tests, 42 assertions） |
| `vendor/bin/phpstan analyse --no-progress --memory-limit=1G` | 0 | No errors |
| `bash tools/pre-merge.sh --fast` | 0 | 7/7 全过 |
| `php tools/contract-build --plugins /tmp/m05-badplugins`（反证夹具） | 1 | FAIL：`命名连接 sandai 的 ledger 必须声明为 per_connection` / `outbox 必须声明为 business_database 或 none` / `audit_sink 必须声明为 kernel_async 或 none` 等 5 条中文文案逐条指名连接与字段 |
| `python3 tools/naming_check.py`（仓内，code/notes/terms/sql --diff） | 0 | FAIL=0，WARN=1 |
| `python3 ~/agent-work/grokbot/devin-queue/naming_check.py`（code/notes/terms --diff） | 0 | FAIL=0，WARN=1（同一条） |

未运行：Integration 套件（需 `SAND_TEST_DB_URL` 指向 itest 库，本机未配置；按授权不建库）。

naming_check WARN×1 说明：`ContractDoc.php:41`「注释过长 9 行」实为 `ConnectionSpec` 的 phpstan-type 数组形状定义（7 字段逐行），与既有 `Operation`/`McpSpec` 形状块同构，非说明文字。

## 六、现有插件契约检查结论（不改插件仓）

- 仓内 `server/plugin/sandadmin`、`sandpackage`：无契约片段（legacy 路由回退 + warning），**无违规**。
- `~/agent-work/grokbot/contract2-1117/repo/sand-workflow` 的 `plugin/sandworkflow/contract/openapi.yaml`（只读）：无 `x-connections` 声明，**无违规**；`php tools/contract-build --kernel-api` 实测 PASS。若将来接独立库需按新段声明。

## 七、独立验收（dv-verifier-swe，未参与实现）：通过

7 条款逐项判定：①四规则对照原文忠实，无编造冲突约束（「声明字面量校验」是契约层最强可达保证；规则 4 透出版本忠实于 v4 J-4 措辞）；②diff 边界合规、api-conventions.md 裁定未越界；③全部命令独立重跑退出码同上表，反证 FAIL 文案可读；④新增测试为真断言（逐条断言具体问题文案）；⑤预算产物被 GeneratedIntegrity 与 --check 覆盖、重生成零漂移；⑥插件违规结论属实、宿主 --check 不变红；⑦diff 无密钥/agent 痕迹/临时文件。

记录项（非阻止）：①命名检查 WARN×1（同上说明）；②宿主契约若写非标量 `x-connections` 会因前置守卫静默放过（插件侧同形状会报错，宿主契约本仓自持，风险极低）；③预算产物不做数值阈值判定；④contract 套件 100 个既有 Incomplete 占位与本切片无关。

## 八、遗留与边界

- **声明 ≠ 运行期做到**：契约层强制插件把账本/outbox/审计落位写成固定合规字面量；执行器统一逐本记账、迁移锁按连接、relay 按连接拉取、审计事件异步落库的真实行为由内核实现保证，需在内核落地时另行验收（M-05 运行期侧）。
- **规则 4 为透出而非算式核对**：进程数是部署期参数；产物透 `plugin_per_process` 明细与宿主固定 4，v4 三场景阈值（288/420/400/200 口径）的核对属容量评审，产物漂移即评审触发点。
- **未做**：manifest.json `databases` 与 `x-connections` 跨文件一致性（清单不在构建收集范围）；命名连接跨插件撞名（relay 按「连接+插件命名空间」记账天然隔离）。
- Integration 套件未跑（见上）；`contract/api-conventions.md` 改动边界已经验收裁定通过。
