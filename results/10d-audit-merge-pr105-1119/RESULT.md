改后可合并（必须修改：解决与 main 的合并冲突）｜审计时 head：e5e3774651c0fd8b8532e678c4384475b6f77a50｜未合并

# PR #105 合并前独立审计（第二轮）

- 仓库 / PR：supdger/sandadmin #105 `feat(idgen): 雪花发号器 + 两类主键约定文档 + 启动自检`，`feat/id-generator` → `main`，OPEN，非草稿
- 审计时 head：`e5e3774651c0fd8b8532e678c4384475b6f77a50`（以 e5e3774 开头，与派单一致）
- 基线 `977427a`；3 个提交（a7067c3、d44a048、e5e3774）；18 个文件，只增不删
- 审计方式：只读。gh 查询和读 diff 交给本机 SWE-2 只读子代理（dv-scout，跑了 2 次），主代理负责判断并写本结论。没有合并、推送、评论，也没有改仓库。

## 不合并的原因

GitHub 报告 `mergeable=CONFLICTING`、`mergeStateStatus=DIRTY`。main 已从 977427a 前移到 `02cdbe8f6ffa4d685ccb43262577612887df3316`（PR 落后 38 个提交，领先 3 个）。

- 冲突文件：`server/.env.example`。main 那边由别的 PR（N5/M11 字段加密）在 `SAND_WEAK_PASSWORD_PATH=` 后面追加了 `SAND_CRYPTO_KEY` / `SAND_CRYPTO_PREVIOUS_KEYS` 的注释块，PR #105 在同一位置追加了 `SAND_IDGEN_*` 三项。PR 的另外 17 个文件，main 这段时间都没改过。
- 派单要求"无冲突"才合，并且"前一个 PR 合入后若产生冲突，停下写明"。所以本次停下，没有执行 `gh pr merge`。

## 必须修改点

1. 在 `feat/id-generator` 上合入或变基最新 main，解决 `server/.env.example` 冲突：两段都保留（加密密钥块和 idgen 三项各自独立）。不要改动其他内容，不要强推覆盖别人的提交。解决后会产生新 head，需要 CI 重新全绿，并按"只核对上轮必改"再核对一次（核对的是：冲突解决只动了 .env.example，且两段都在）。

## 上一轮审计（results/8a-audit-pr105-1119，head d44a048）必改点核对

| 上轮必改 | 当前状态 | 证据 |
| --- | --- | --- |
| 1. `config/idgen.php` 三项 `env(...) ?: ''` 会把 `'0'` 吞掉，导致基值 0 拒绝启动 | 已修改 | d44a048→e5e3774 增量只改了 2 个文件：`config/idgen.php` 三处都改成 `env('KEY', '')`；`SnowflakeConfigTest::testEnvZeroNodeBaseAccepted` 用 putenv `'0'` 走真实 config 文件，断言 `nodeBase === 0`，并在 finally 里清理 env |
| 2. PR 描述里"剩余项"过时（仍写着多 worker 细分待做） | 已修改 | 现在的描述里，剩余项只剩节点租约表、外键同型检查、两库实测；描述也写明了 e5e3774 的修复 |

8c 目录（8c-audit-pr107-1119）审计的是 PR #107，与本 PR 无关，不用核对。

## 审计项结论

1. **范围与描述一致：一致。** 18 个文件都是发号器相关：`src/Infra/Id/*`、启动自检、`config/idgen.php`、`config/bootstrap.php`（+2 行）、`.env.example`、文档、`phpstan.neon.dist`（+1 行）和测试。描述已更新到 e5e3774。
2. **安全与正确性：通过。** 上轮的结论没有变化：63 位布局；序列溢出时等下一毫秒；回拨小于 10ms 等时钟追上，达到 10ms 拒发并告警；节点号细分到进程，多 worker 不重号；配置非法拒绝启动。本轮增量只改了 env 读取：`env(key, '')` 未设置时返回 `''`，仍按必填缺失拒绝启动；设为 `'0'` 时原样透传，由 `intValue` 的 `ctype_digit` 接受；`"true"` 这类值会被 env 转成 bool，同样拒绝。没有新的外部依赖、密钥或放宽项。
3. **门禁文件只减不增：通过。** 没有改 `.github/workflows/*`、`tools/*`、白名单、豁免或基线文件。唯一相关的改动是 `phpstan.neon.dist` paths 加了 1 行，把新文件纳入分析范围，属于收紧。
4. **与《老板已定事项总表》：不冲突。** 符合第 17 条：可预见的表用自增；不可预见、持续增长的表用雪花 bigint（41+13+9），接口输出字符串；nanoid 单独一列、不当主键。第 24 条"自动测试不过就不合并"：CI 没有 phpunit/phpstan（见下）。本地测试结果是 PR 自报的，加上技术总监的复验。备注：描述里引用的"编号两列表-修订（11-18 批准）"总表里没有记录，以技术总监的复验为准。
5. **CI：** `gh pr checks 105` 三项全部 pass（composer audit、frontend prod audit、release self-check，run 44629672849，headSha 是 e5e3774）。CI 不跑 phpunit/phpstan，这是仓库现状，本次不改 CI。

## 上轮建议项（不阻塞，仍然有效）

`waitUntil()` 没有等待上限；真实 Workerman Worker 的身份提取没有单测；不发号的进程也占槽，号段宽度要在部署文档里说明怎么算；部署之间号段重叠查不出来（等节点租约表）；`SnowflakeRuntime::mount` 重复挂载会静默覆盖。

## 边界与模型说明

- 没有建库或启动数据库，没有跑本地测试、phpstan 或真实 webman 启动，没有改 CI 或门禁，没有合并。
- 主代理：本目录 `model.txt` 记录的是 `fusion-claude-opus-5-5-medium-sidekick-swe-2-medium`（Opus 5.5 medium 主控）。这和 `~/.config/devin/AGENTS.md` 写的"主代理固定 SWE-2"不一致，特此声明。按派单，执行和读取都交给了 SWE-2 子代理（dv-scout）。
