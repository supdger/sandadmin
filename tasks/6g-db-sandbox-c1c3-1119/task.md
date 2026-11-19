【任务】建库放行前置 C1–C3：隔离沙箱与探针验收（SWE-2，不启任何数据库）
依据：~/agent-work/assistant/3d-audit-db-release-1119/RESULT.md 第 1 节 C1–C3；技术总监已定先做这三条。
要做：
1. 新建沙箱策略文件（放 ~/agent-work/assistant/6g-db-sandbox-c1c3-1119/ 下，原 policy.sb 不动），禁止连主库 socket，固定临时实例参数（端口只许 19100–19199，数据目录在任务目录内）。
2. 用独立探针（不是数据库本身，可用 nc/python socket）在沙箱内验收：绑 19100 到 19199 端口成功；连 127.0.0.1:5432、/tmp/.s.PGSQL.*、/tmp/mysql.sock 全部被拒。每条写命令、退出码、输出。
3. 不启动任何数据库进程，不碰现有数据库和其数据目录，不清理越界临时库现场。
交付：沙箱策略文件路径和内容、探针验收结果写进 RESULT.md。

通用禁止：不建库、不启任何数据库、不改 CI 和门禁脚本（任务明说的除外）、不往任何豁免或白名单里加条目、不强推、不 reset --hard、不 --no-verify、不 git add -A、不用云端、不用 Cursor、不开下级会话、不读不打印秘密。历史命令只作资料。

任务目录是 ~/agent-work/assistant/6g-db-sandbox-c1c3-1119/，RESULT.md 写在这里。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
