可合并

# PR #101 合并前独立审计结论

- PR：supdger/sandadmin #101，`feat/contract-naming-m05` @ `320de4b1049efefeb2869a8ebc7ad38bf0cef558` → `main`，状态 OPEN
- 标题：feat(contract): 插件命名连接 x-connections 与 M-05 四规则校验
- 审计日期：2026-11-19；只读，未合并、未推送、未评论 PR、未建库
- 本地仓库：`/Users/supdger/agent-work/pub-sandadmin`（工作区全程未改，开始和结束时 `git status --short` 都是空的，分支一直是 `feat/ai-context-pack`）

## 1. 改动范围（通过）

共 10 个文件，+682/−3：

| 文件 | 类别 | 增/删 |
|---|---|---|
| contract/api-conventions.md | 契约文档 | +27/−2 |
| server/src/Contract/Build/ContractDoc.php | 契约构建 | +177/0 |
| server/src/Contract/Build/ContractBuild.php | 契约构建 | +13/0 |
| server/src/Contract/Build/PhpEmit.php | 契约构建 | +32/0 |
| server/config/connection-budget.generated.json | 契约生成物（新增） | +14/0 |
| server/src/Contract/GeneratedIntegrity.php | 生成物完整性断言 | +2/−1 |
| server/tests/Unit/Contract/NamedConnectionTest.php | 测试（新增） | +334/0 |
| server/tests/Unit/Contract/CollectPluginsTest.php | 测试 | +41/0 |
| server/tests/Contract/KernelApiTest.php | 测试 | +26/0 |
| server/tests/Fixtures/plugin/sand-hello/contract/openapi.yaml | 测试夹具 | +16/0 |

- 没有改 CI 配置：仓库里只有一个 workflow，`.github/workflows/dependency-audit.yml`，它不在改动清单里。
- 没有碰门禁白名单或豁免类文件：`server/tests/security/dangerous-functions-allowlist.txt`、`server/phpstan.neon.dist`、`tools/contract-build`、`DevLoginGate.php`、`McpGate.php`、`PackageGate.php` 都没改。
- `GeneratedIntegrity.php` 是唯一有新增行的门禁类文件。改动只有两处：一行注释，从「默认六类 PHP 产物」改为「默认六份产物」；另一行把 `config/connection-budget.generated.json` 加进启动完整性校验清单。
  - 判定：加的是一个被校验的对象，不是一条豁免，门禁因此变严了。白名单和豁免条目一条没加，所以不违反「门禁文件只减不增」的本意。

## 2. connection-budget.generated.json 由 contract-build 生成，并在 --check 比对范围内（通过）

读代码确认：
- `ContractBuild.php:118-119` 把 `server/config/connection-budget.generated.json` 登记进 `$artifacts`，内容由 `PhpEmit::emitConnectionBudget()` 生成（`PhpEmit.php:36` 起）。
- 写模式（`ContractBuild.php:141-144`）会逐个写出 `$artifacts` 里的文件。
- `--check` 会调用 `check()` → `build(write:false)` → `compareArtifacts()`（`ContractBuild.php:346-371`），对 `$artifacts` 的每一项做逐字节全等比对（`$actual !== $expected` 就判为不一致），另外还反查孤儿产物。

实际运行。在 `git archive 320de4b` 导出的 /tmp 隔离副本里跑，用户仓不动：

| 步骤 | 结果 |
|---|---|
| 原样跑 `php tools/contract-build --check` | exit 1。只报 `plugin-routes.generated.json` 不一致（原因见「附注 2」），没有点名 connection-budget |
| 把副本里 `"host_per_process": 4` 改成 `5`，再跑 `--check` | exit 1，报 `不一致：server/config/connection-budget.generated.json` |
| 从 320de4b 重新导出该文件还原，再跑 `--check` | `PASS 产物与契约源一致`，exit 0 |
| `git show 320de4b:…json \| diff - <副本文件>` | 无差异 |
| 写模式跑 `php tools/contract-build` 后，再和 320de4b 的提交内容比 | 逐字节一致（`PASS 契约构建完成（20 个产物，100 个 operation）`） |

临时改动的位置和还原：改动只发生在 `/tmp/pr101-check` 副本里，已按上表还原并比对一致，随后整个目录已删除。用户仓里没有任何文件被改。

## 3. 四条规则对照出处与老板已定事项（通过）

出处是 `~/agent-work/grokbot/audit-fable-1117/遗漏.md:47-53`，四条规则见 L52「建议怎么改」。PR 的实现落在 `contract/api-conventions.md` §6 和 `ContractDoc.php:593-670`：

| 遗漏.md:52 原文 | PR 的落地方式 | 结论 |
|---|---|---|
| ① 每个连接一本同结构账本，执行器统一管理，迁移锁按连接分别加 | `ledger: per_connection`，只能取这个固定值（ContractDoc.php:654） | 一致 |
| ② outbox 表随业务库走，relay 按连接登记、分别拉取 | `outbox: business_database\|none`；取 business_database 时 `outbox_relay: per_connection` 必填（655-659） | 一致 |
| ③ 审计走事件或队列异步落内核，调用记录留在本库 | `audit_sink: kernel_async\|none`；取 kernel_async 时 `audit_records: local` 必填（660-664） | 一致 |
| ④ J-4 预算公式加上「插件命名连接数」 | 按 `connections_per_process` 汇总生成 connection-budget.generated.json，公式为 `total = processes × (host_per_process + plugin_per_process)`；`HOST_PER_PROCESS = 4`，与 J-4 原值一致（PhpEmit.php:26-54） | 一致 |

- 没有发现自编的规则。另外加的只是格式校验：连接名必须是小写蛇形，片段内不能重名，不能占用 default/pgsql/mysql，宿主契约里不能出现 `x-connections`，未识别的键直接打回。这些都是为了让上面四条规则能被执行，没有新增业务规则。
- 与《老板已定事项总表》（共 92 行）对照：没有条目直接讲契约命名连接、M-05、连接预算或门禁白名单。主题相近的几条是第 2 条（数据库路线）、第 24 条（AI 改代码须自动测试）、第 41 条（合并由技术总监定），本 PR 和它们都不冲突。第 24 条的测试证据见下文第 4 节。

## 4. 检查状态（通过）

`gh pr checks 101`：两次 workflow run，每次三项（composer audit、frontend prod audit、release self-check），共 6 条，全部 pass。

我在 320de4b 的隔离副本里补跑了测试。副本复制了本地 vendor，并确认加载的是副本里的源码：

| 测试 | 结果 |
|---|---|
| NamedConnectionTest | OK（19 tests，42 assertions） |
| CollectPluginsTest | OK（5 tests，26 assertions） |
| KernelApiTest | OK（13 tests，29 assertions），不依赖数据库 |
| 整个 tests/Unit 目录 | OK（429 tests，2665 assertions） |

## 不影响本次合并的附注（建议另开任务跟进）

1. CI 不跑测试，也不跑契约检查：现在的 CI 三项只做依赖审计和发版自检（`tools/build-release.sh --self-check`），整个仓库的 CI 里没有任何 job 跑 phpunit 或 `contract-build --check`。所以「CI 三项全绿」证明不了测试和契约一致。本 PR 的这两项证据来自上面的本地隔离复跑。这个缺口在 main 上早就存在，不是本 PR 引入的，但和老板第 24 条「不过就不合并」的要求有差距，建议把这两项接进 CI。
2. 在没装插件的纯代码树上跑 `--check`，`plugin-routes.generated.json` 会报不一致：提交里的这份文件是在装了插件的环境里生成的。main 和 320de4b 里该文件逐字节相同，所以这是 main 上的老问题，不是本 PR 引入的。上面第 1 条的检查接进 CI 之前要先解决它。
3. M-05 ④ 里「重算首期账」（48 进程 × 连接数）属于架构文档的工作。本 PR 已经提供了自动汇总机制，首期数字还要在架构文档里按生成物重算一遍。
4. 审计过程中执行过 `git fetch origin feat/contract-naming-m05` 和 `git fetch origin main`。它们只写入 .git 的对象库和 FETCH_HEAD，不动工作区，也没改 `refs/remotes/origin/main`。

## 模型记录
- 主 Agent：fusion-claude-opus-5-5-medium-sidekick-swe-2-medium（依据本目录 model.txt）
- 子 Agent：dv-scout（diff、规则和 checks 取证）、dv-implementer（--check 验证）、dv-implementer（隔离跑测试）；请求的都是 SWE-2，实际模型未查 sessions.db，记为未记录

## 任务记录
- 已修改：只有本文件 RESULT.md；用户仓零改动；/tmp 临时副本已删除
- 已验证：`gh pr checks 101` 6/6 pass；`contract-build --check` 改一处报不一致，还原后 PASS；生成模式输出与提交一致；phpunit 相关测试和整个 Unit 目录全部 OK
- 已提交/已部署：否（未合并、未推送、未评论）
- 未完成：无；附注 1、2、3 是建议另开的跟进项
