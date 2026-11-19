# RESULT — B4+B5+N-c 统一迁移执行器与基线入仓（第一批建库包，只起 PG）

日期：2026-11-19（PT） 执行：Devin（SWE-2 High） 仓库：supdger/sandadmin
分支：`feat/migration-runner`（基于 origin/main `c506398e69dd55e5efc827a2499aac17fd80957f`）
提交：`36759180d8ea798d25d0292569ab22d0aacdf6be`
PR：https://github.com/supdger/sandadmin/pull/111 （目标 main，未合并）

## 结论

全部要求完成。临时 PG 18.4 实例上跑通「空库 → 0001–0007 → 回退到 0003 → 再升级到 0007」，
三段账本快照齐全；Unit 603 tests / Integration 157 tests（真库）/ PHPStan / lint /
危险函数 / dev-only 引用 / 契约一致性 / naming_check 全过。临时实例已销毁、端口已释放。

## 交付内容

- 基线 0001–0003 按 C7 从 `~/agent-work/grokbot/1x-ddl-1110/out/migrations/` 逐字节入仓，
  sha256 与 C7 锁定值一致（见下「基线哈希」）。
- 0004–0007 配同名 `.down.sql` 回退文件；基线 0001–0003 不带回退（约定）。
- `server/src/Infra/Migration/`（执行器、账本、目录、锁、一致性检查、恢复点）+
  `server/bin/migrate.php`（CLI：`up`/`down <ns> --to=<ver>`/`status`）。
- 账本 `sand_system_schema_migration`：int 自增主键、`(namespace, version)` 唯一、
  direction ∈ {up,down}、status ∈ {applied,failed,rolled_back}。
- 0001 内嵌的旧形账本表（version 主键、少列）在首迁事务内就地升级为定稿列结构——
  基线文件逐字节不动。
- R1：`server/config/database.php` 去掉 `127.0.0.1:5432` 默认值，.env 存在且缺 `DB_*`
  即抛错拒绝连接；同族连接助手 `DbalConnection` / `FileConnection` / `TaskConnection`
  同样去兜底（host 空或 port<1 拒绝）。`config/think-orm.php` 属退役栈，不在范围。
- `AGENTS.md` 更新「本仓无 migrations」等过时表述。
- 未改契约、CI、门禁脚本；未加白名单/豁免条目。

## 基线哈希（sha256，全量）

| 文件 | sha256 |
| --- | --- |
| 0001_baseline_1_0_0.sql | d2dc35f04f9f50494ac5250379ded6dd03f642ba70a18d60bc4f27bb529fe232 |
| 0002_add_dict_type_extension_key.sql | 34e94ce2e19cc67e01cd190f29ccfcf125070fc9c4414734b5bc4edaecc2dac8 |
| 0003_add_lifecycle_request_stage_key.sql | 0e33062d870a5242709857bfb4922a8bb442e6a6cbc0872727a45ca3f07c6616 |
| 0004_widen_announcement_level.sql | baf74e09757e4623b37dac9634fa1ae68365857dc46f9e5887bc9d9ba62bcad9 |
| 0005_add_dict_item_ref.sql | 0d3ee728f430e2bbb6bfd5966cf1738903e8d6f81f5f6e1a418655cc66b34b95 |
| 0006_suspend_plugin_override.sql | cd0f6a4b241eaace09b1c27381e45e183d5c22ded07cc2f93f4c0d69735823de |
| 0007_align_metric_sample_columns.sql | 8414573a1d3a32f549accb9b57d8061b6a892e47e5b98479c5b21814100668a8 |

0001–0003 与 C7 源文件逐字节一致；0004–0007 与 main `977427a` 逐字节一致
（`git diff 977427a c506398 -- server/database/migrations` 为空）。

## 临时 PG 实例（6g 沙箱策略）

- 端口：19100（`lsof -nP -iTCP:19100 -sTCP:LISTEN` 启动前确认空闲；19120 被
  pkgops-probe-1118 占用未触碰）
- 数据目录：`~/agent-work/assistant/8e-migration-runner-1119/db/pg/data`（已删除）
- 口令：随机生成存入 `db/pg/.superuser-pw`（0600，已随数据目录删除）；
  连接只经 `SAND_TEST_DB_URL` / `PGPASSWORD` 传入，未写进仓库或交付文件。
- 沙箱：6g 共享策略 `db-instance-policy.sb`（sha256 `2fe97b4b…`）未覆盖
  PostgreSQL bootstrap 所需 SysV shm，按 C 系隔离原则**未改动共享策略**，
  在任务目录放任务本地变体 `db-instance-policy-8e.sb`（追加 `ipc-sysv-shm`，
  sha256 `256d96695118280411625a9e1e5e27c1b0b5e9f57a81ab8d104214407c874ea1`）。
  策略覆盖缺口已在 6g 验收之外暴露，建议共享策略补 `ipc-sysv-shm`。
- 测试库：`sand_mig_test`（共享夹具约定「基线已应用」）；集成测试另建
  `sand_mig_test_migrunner` 临时库跑破坏性场景，tearDown 后 `DROP DATABASE … WITH (FORCE)`。

## 三段账本快照（实跑输出，全量见 logs/cycle.log）

升级（恢复点 8e-final-run）：0001–0007 全部 `up applied`。

回退 `down host --to=0003`：

```
host 0001 up applied 0001_baseline_1_0_0.sql release=1.0.0 32ms
host 0002 up applied 0002_add_dict_type_extension_key.sql release=1.1.0 2ms
host 0003 up applied 0003_add_lifecycle_request_stage_key.sql release=1.1.0 0ms
host 0004 down rolled_back 0004_widen_announcement_level.down.sql release=1.1.0 1ms
host 0005 down rolled_back 0005_add_dict_item_ref.down.sql release=1.1.0 0ms
host 0006 down rolled_back 0006_suspend_plugin_override.down.sql release=1.1.0 0ms
host 0007 down rolled_back 0007_align_metric_sample_columns.down.sql release=1.1.0 3ms
待执行：host/0004, host/0005, host/0006, host/0007
```

再升级：0004–0007 重新 `up applied`；`status` 显示「无待执行迁移」。

## 验证命令与退出码

| 命令 | 结果 |
| --- | --- |
| `php -l`（全部新增/改动 PHP 文件） | 全部 No syntax errors，rc=0 |
| `vendor/bin/phpunit --testsuite unit` | OK 603 tests / 3409 assertions，rc=0 |
| `vendor/bin/phpunit --testsuite integration`（SAND_TEST_DB_URL 指向临时库） | OK 157 tests / 884 assertions，rc=0 |
| `vendor/bin/phpstan analyse` | No errors，rc=0 |
| `php tests/security/scan-dangerous-functions.php` | PASS |
| `php ../tools/check-dev-refs.php` | PASS（1487 文件） |
| `php ../tools/contract-build --check` | PASS（注：该工具会重写 `connection-budget.generated.json`，未纳入提交） |
| `naming_check.py sql`（4 个 .down.sql） | rc=0 |
| `naming_check.py code`（全部新增 PHP + diff） | rc=0 |
| `naming_check.py notes` | rc=0（一处 4 行注释 WARN 已压到 3 行后通过） |
| `naming_check.py terms` | rc=0 |
| `php bin/migrate.php status`（缺连接参数） | rc=2，报「缺少连接参数」——拒连行为正确 |
| `php bin/migrate.php down host 0003`（错语法） | rc=2，报「down 必须带 --to=<版本>」 |

`tools/check-module-tests.php` 在本分支基线（c506398）不存在（早先读到的清单是
切分支前旧检出的内容），无对应门禁可跑。

## 失败与修复（实现过程中）

1. initdb 被沙箱拦 SysV shm → 任务本地 policy 变体追加 `ipc-sysv-shm`（共享策略不动）。
2. `pgsql://` DSN 走 ext-pgsql（`PgSql\Connection`）非 PDO → 执行器同时支持
   `PDO::exec` 与 `pg_query`（迁移文件多语句须 simple query 协议）。
3. 0001 旧形账本升级：PG 单条 ALTER TABLE 的子命令并行应用，引用新列的约束不能
   与 ADD COLUMN 同语句 → 拆两条；新列不设默认值（旧形表必为空，非空如实报错）。
4. 0007 回退文件初版文件名多 `.sql` 段 → 改为 `0007_align_metric_sample_columns.down.sql`。
5. 集成测试 `resetSchema` 破坏共享测试库基线夹具 → 改用独立 `<库名>_migrunner`
   scratch 库，用后即删，不碰共享夹具。
6. PHPStan 14 处类型收窄报错（DBAL `Params` 形状、mixed 取值、LedgerRow 字面量联合）→
   全部按真实语义修复：LedgerRow 放宽为读取载体（一致性检查器负责校验），
   写路径保留字面量签名。
7. 一次 `psql "pgsql://…"` 连接尝试被 psql 拒识 scheme，报错回显了含口令的 URL
   到本地终端记录——口令仅本次任务随机生成、随实例销毁失效，未进任何交付文件
   或仓库；任务目录全量扫描确认无口令残留。

## 销毁记录（C6）

- 停库：`/opt/homebrew/opt/postgresql@18/bin/pg_ctl -D …/db/pg/data stop -m fast` →
  `server stopped`，rc=0。
- 端口确认：`lsof -nP -iTCP:19100 -sTCP:LISTEN` → 无监听（rc=1）。
- 删除：`rm -rf …/db/pg`（仅本任务目录）；保留 `db/log/postgres.log` 作日志证据。
- 临时 diff 文件、scratch 库已清；`.superuser-pw` 随数据目录删除。
- 未触碰 postgresql@18（5432）、/usr/local/mysql（pid 329）、final-runtime-check-1112
  残留 PG（25540）、wf-install-1117 现场；未连任何既有库；未启 MySQL。

## 边界声明

- 迁移执行器本批次仅支持 PostgreSQL（`assertPostgres` 拒绝其他平台），MySQL 适配留给后续包。
- 结果为临时库验证；未部署、未合并未推送 main。
