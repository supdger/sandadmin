结论：可合并（审计时 head = 36759180d8ea798d25d0292569ab22d0aacdf6be）——已合并，main 新 SHA = 3b2f7be3488a6f242a93fbacc6965da0504e8a2b

# PR #111 合并前独立审计（supdger/sandadmin，feat/migration-runner）

- 审计日期：2026-11-19
- 对照 SHA：results/8e-migration-runner-1119/RESULT.md 的最终提交 `36759180d8ea798d25d0292569ab22d0aacdf6be`，与 PR headRefOid 完全一致。PR 只有这一个提交。
- 证据来源：`gh pr view` / `gh pr diff`（35 个文件，3276 行，全部审过），以及在本地 clone `~/agent-work/assistant/upload-1119/sandadmin` 用 `git show 36759180:<path>` 直接读文件（没有 checkout）。
- 进度口径：数据库行为只在临时库上验证过（临时 PG 18.4，127.0.0.1:19100，已销毁，见 8e RESULT）。本次审计没有建库、没有启动或连接任何数据库，也没有部署。

## 一、审计项

| 项 | 结论 | 依据 |
|---|---|---|
| 范围与描述一致 | 通过 | 35 个文件全在描述范围内：执行器 `server/src/Infra/Migration/*`、`bin/migrate.php`、0001–0003 基线、0004–0007 的 `.down.sql`、database.php 与 Dbal/File/Task Connection、测试、AGENTS.md 迁移说明。没有夹带别的改动。 |
| checksum 校验 | 通过 | `MigrationFile` 计算 sha256 并写入账本 `checksum`。`LedgerConsistency::problems` 用 `hash_equals` 比对：已应用的 up 文件、已回退的 down 文件，任何一个被改过就拒绝执行。 |
| advisory lock 不排队 | 通过 | `MigrationLock::acquire` 用 `pg_try_advisory_lock`（不阻塞），拿不到锁就抛 `MigrationBusyException`，退出码 3。锁与执行、账本用同一个 DBAL Connection，也就是同一个会话，所以会话级锁的语义成立。`finally` 里会释放锁。集成测试 `testLockConflictRefusesWithoutQueuing` 覆盖了这一点。 |
| 半成品库拒绝执行 | 通过 | `upgrade` 和 `downgrade` 都按这个顺序执行：先确认是 PG，再取锁，再做账本一致性检查，然后要恢复点，最后才执行。账本里有 failed 记录、status 非法、找不到对应文件、checksum 不符、序列断档或丢行，都会抛 `MigrationConsistencyException`（退出码 4）并附修复指引。每份迁移单独一个事务，失败就回滚并记 failed，下次执行会被拒绝（测试已覆盖）。 |
| down 文件 | 通过 | 0004–0007 各自带同名 `.down.sql`，文件头注明了会丢哪些数据，并说明恢复靠升级前的备份。基线 0001–0003 不带回退，要回退到基线以下会被拒绝。临时库上已跑通"0007 回退到 0003 再升回 0007"。 |
| 0001–0003 哈希锁定 | 通过 | 按 head 实测 sha256：0001 `d2dc35f0…fe232`、0002 `34e94ce2…2dac8`、0003 `0e33062d…c6616`，与 `BaselineHashTest::HASHES` 和 8e RESULT 一致。这三个文件是新增的，main 上以前没有。0004–0007 的 up 文件和 main 逐字节相同，PR 没改。 |
| database.php 去掉默认 127.0.0.1:5432 | 通过 | 现在是 `'host' => $resolve('DB_HOST', …)`、`'port' => (int) $resolve('DB_PORT', …)`，有 .env 但缺值就抛异常，没有 .env 就得到空值或 0。Dbal、File、Task 三个连接助手的兜底改为 `''`/`0`，并加上 `$host === '' \|\| $port < 1` 就拒绝连接。 |
| 门禁文件只减不增 | 通过（零改动） | diff 里没有 `.github/`、门禁或检查脚本、白名单或豁免文件。 |
| 与《老板已定事项总表》不冲突 | 通过 | 第 2 条（下一批起同时支持 PG 和 MySQL）：本 PR 只支持 PG，`assertPostgres` 会明确拒绝其他库，MySQL 留给后续包，这是分批落地，不是否定这条决定。第 17 条（数量可预见的表用自增 ID）：账本主键是 int identity，符合。第 24 条（自动测试通过才合并，并且可回退）：本地 unit 603 和 integration 157 全绿，增量迁移都有回退文件。第 41 条（合并由技术总监决定）：任务依据写明总监已复验，并且本地 Opus 审计通过即视为批准。 |
| 安全与正确性 | 通过（有非阻断遗留，见第三节） | DSN 只从参数或环境变量取，没有默认地址。up 和 down 必须带 `--recovery-point`，down 还必须带 `--to`。执行器只在 CLI 下运行，单连接，没有连接池换会话的风险。 |

## 二、合并门槛与执行

- head 与 8e RESULT 的最终 SHA 一致：是（`36759180…`）。
- `gh pr checks 111`：6 项全部 pass，退出码 0（composer audit、frontend prod audit、release self-check 各两次运行）。
- 冲突：mergeable=MERGEABLE，mergeStateStatus=CLEAN。
- 执行命令：`gh pr merge 111 --repo supdger/sandadmin --merge`，退出码 0。没有用 --admin，没有删分支。
- 结果：PR 状态为 MERGED，mergedAt 2026-11-19T22:55:08Z，合并提交 `3b2f7be3488a6f242a93fbacc6965da0504e8a2b`。它有两个父提交：`a18fef94…`（合并前的 main）和 `36759180…`（PR head）。
- 分支 `feat/migration-runner` 保留，仍指向 `36759180…`。
- 注：合并前 main 已从分支基点 `c506398` 前进到 `a18fef94`。GitHub 判定 CLEAN，可以直接合并，但临时库验证是在 `36759180` 上做的，没有在合并后的 main 上重跑。

## 三、非阻断遗留（建议后续单独开包，不在本 PR 范围）

1. 还有两处没有去掉 127.0.0.1:5432 兜底，都是本 PR 之前就有的代码：
   - `server/config/think-orm.php:25-26`：`env('DB_HOST', '127.0.0.1')`、`env('DB_PORT', 5432)`。
   - `server/src/Task/TaskCli.php:61-62`：`$env['DB_HOST'] ?? '127.0.0.1'`、`?? 5432`。

   风险：TaskCli 先把默认地址填好再传给 TaskConnection，等于绕过了本 PR 在 TaskConnection 里加的拒绝连接，缺 DB_* 时仍会连到本机 5432。建议下一包按 R1 一起清掉。
2. `MigrationLedger::recordFailed` 记账本身失败时（例如连接已断），会抛出新的 `MigrationException`，原始迁移 SQL 的错误信息就丢了，退出码也从 5 变成 1。`revert()` 有同样的结构。因为 PG 的 DDL 在事务里，这种情况下库结构已经回滚，所以不影响数据正确性，只影响排障。建议把原始错误也带进异常消息。
3. CI（`dependency-audit.yml`）不跑 phpunit，所以 `BaselineHashTest` 的哈希锁目前只在本地测试时生效。执行期另有账本 checksum 防改兜底。按本任务禁令，CI 未改，建议由总监另行决定是否把单测加进 CI。
4. AGENTS.md 仍写着"无 CI"，与实际存在的 `dependency-audit.yml` 不符，属于文档小错。
5. MySQL 适配按总表第 2 条，需要在后续批次补上。

## 四、遵守的禁令

没有建库、没有启动或连接任何数据库；没有改 CI、门禁、白名单或豁免；没有强推、reset、--no-verify、git add -A、--admin；没有用云端或 Cursor，没有开下级会话；没有读取或打印秘密。

## 模型记录
- 主 Agent：Fusion（Claude Opus 5.5 Medium + SWE-2 Medium，见 model.txt）。按 Devin 规则不运行 current_model_record.py，sessions.db 未查询，实际模型"未记录"。
- 子 Agent：dv-scout = SWE-2，两次（diff 证据收集、定点代码核查）；dv-implementer = SWE-2，一次（带门槛的合并）。实际模型未记录。

## 任务记录
- 已修改：本文件 RESULT.md。远端操作：PR #111 已合入 main。子代理另写了 /tmp/pr111.diff（只读 diff 副本）。
- 已验证：上述 gh、git 只读命令；合并门槛 7 项全部通过；合并后状态 MERGED，分支保留。
- 已提交/已部署：已合并（GitHub 远端 merge commit 3b2f7be）；未部署。
- 未完成：第三节遗留 1–5，留待后续包处理。
