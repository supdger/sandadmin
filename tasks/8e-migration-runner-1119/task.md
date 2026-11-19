【任务】B4+B5+N-c 统一迁移执行器与基线入仓（第一批建库包，只起 PG，SWE-2）
依据：技术总监 2026-11-19 定稿；建库放行审计 ~/agent-work/assistant/3d-audit-db-release-1119/RESULT.md（有条件放行），C1–C3 已由 ~/agent-work/assistant/6g-db-sandbox-c1c3-1119/ 验收通过。临时 PG 实例必须在 6g 的沙箱策略里启动，端口只用 19100–19199，数据目录在本任务目录内，任务结束销毁并记录。
仓库与分支：supdger/sandadmin，从 main 开 feat/migration-runner，开工时写明基线 SHA。
账本列结构（技术总监定，表名 sand_system_schema_migration，属可预见数量，用 int 自增主键）：id、namespace（host 或插件名）、version、filename、checksum（sha256）、direction（up 或 down）、status（applied、failed、rolled_back）、release_version、applied_by、applied_time、execution_ms。唯一键为 (namespace, version)。
要做：
1. 统一迁移执行器：按 namespace 和 version 顺序执行；执行前校验 checksum，已执行文件被改动的拒绝执行；每份迁移在单独事务里执行。
2. 迁移锁：用 PG advisory lock，同一时刻只允许一个执行者；拿不到锁就报错退出，不排队等待。
3. 半成品库检测：账本里有 failed 记录，或者表结构和账本对不上，就拒绝继续执行，并给出修复指引。
4. 回退脚本约定：每份迁移配一个同名的 down 文件，为 0004 到 0007 补上回退文件；基线 0001 到 0003 不带回退。
5. 基线入仓：C8 由技术总监定为 0001 到 0003 视为未发布草稿，按 C7 的 sha256 锁定入仓，入仓后校验哈希一致。
6. 在临时 PG 实例里跑通“空库 → 0001 到 0007 → 回退到 0003 → 再升级到 0007”，并跑需要真库的 Integration 测试。
7. R1：server/config/database.php 去掉 127.0.0.1:5432 的默认值，环境变量缺失时拒绝连接。
范围：迁移执行器、账本、迁移文件、database.php 和对应测试。不改契约、CI 和门禁脚本。
固定条款（3d 审计 C4–C7、C9，全部必须遵守）：
| C4 | 数据目录只能放在 `~/agent-work/<工具>-<任务>-<日期>/db/<pg\|mysql>/`。超级用户口令随机生成，存进任务目录内一个权限 0600 的文件，不许打印、不许写进仓库或日志、不许拷出任务目录。连接参数只能通过 `SAND_TEST_DB_URL` / `DB_*` 环境变量显式传入，并且指向本任务的端口。 | secret-hygiene；database.php:25-52 |
| C5 | 端口分配：启动前用 `lsof -nP -iTCP:<port> -sTCP:LISTEN` 确认端口空闲，被占用就换下一个，不许杀占用者。目前 19120 被 pkgops-probe-1118 的 php 进程（pid 27151）占着。每个任务在自己的 RESULT 里写明用了哪些端口。 | E 报告第二节 |
| C6 | 任务结束时销毁实例：先停实例（`pg_ctl -D <自己的目录> stop -m fast` / `mysqladmin --socket=<自己的 socket> shutdown`），确认端口已释放，再删除本任务自己建的 db 目录。如需保留证据，只保留日志和导出的 schema 文本。销毁命令只能指向本任务目录，不许出现通配、他人目录或全局清理。任务异常中止时，在 RESULT 里写明残留在哪，留给下一轮清理，不许跨任务去清别人的现场。 | 任务要求第 2 项；task-hygiene |
| C7 | 基线来源固定为 `~/agent-work/grokbot/1x-ddl-1110/out/migrations/` 里的 0001（sha256 前缀 `d2dc35f0`，37 张表）、0002（`34e94ce2`）、0003（`0e33062d`）；0004–0007 取 sandadmin main `977427a`。往仓库或临时库拷贝时，在 RESULT 里记下完整 sha256，和源文件逐字节一致。`out.bak-*` 下的历史快照不能用。 | E 报告第一节；B 报告 1.6 |
| C9 | 每个工作包的提示词必须写明：只用本任务的临时实例；不碰 postgresql@18（5432，数据目录 `/opt/homebrew/var/postgresql@18`）；不碰 `/usr/local/mysql`（pid 329）；不碰 final-runtime-check-1112 的残留 PG（pid 3553，端口 25540）；不启动、不清理 wf-install-1117 现场；不 push main、不合并、不发布；不用云端；不开下级会话。 | 任务要求；已定事项 |
禁止：不连老板机器上任何已有数据库（5432、3306 及其 socket），不启 MySQL，不碰已有数据目录和越界临时库现场；不合并。
交付：目标为 main 的未合并 PR（分支和最终 SHA）。RESULT.md 写清楚：命令、退出码、关键输出；升级、回退、再升级三段的账本快照；临时实例的端口、数据目录和销毁记录。结果只算临时库验证。

通用禁止：不改 CI 和门禁脚本（任务明说的除外）、不往任何豁免或白名单里加条目、不强推、不 reset --hard、不 --no-verify、不 git add -A、不用云端、不用 Cursor、不开下级会话、不读不打印秘密。历史命令只作资料。

任务目录是 ~/agent-work/assistant/8e-migration-runner-1119/，RESULT.md 写在这里。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
