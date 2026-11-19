【任务】修 PR #105 发号器多 worker 重号（SWE-2）
分支：feat/id-generator，在 a7067c3 上加提交（supdger/sandadmin）。
要做：
1. 节点号细分到进程。每台机器的节点号是一个基值，各 worker 用“基值 + worker 序号”。启动自检要校验“基值 + worker 总数 − 1”仍在 node_range 内，越界就拒启。自定义进程和任务消费者进程也要分到不冲突的号。
2. 加跨进程唯一性测试：起多个子进程同时发号（不起服务、不连库），合并后零重复。
3. 回拨阈值定为 10ms：小于 10ms 等时钟追上，达到 10ms 拒发并告警。配置保持必填，.env.example 和 server/docs/id-generator.md 写明推荐值 10 和理由，“待技术总监定”改成已定。
交付：普通 push 同分支新 SHA；RESULT.md 写改动、跨进程测试结果、命令、退出码和关键输出（phpunit unit、phpstan、pre-merge --fast、naming、typephp）。

通用禁止：不建库、不启库、不改 CI 和门禁脚本、不往任何豁免或白名单里加条目、不合并、不强推、不reset --hard、不 --no-verify、不 git add -A、不用云端、不用 Cursor、不开下级会话、不读不打印秘密。

任务目录是 ~/agent-work/assistant/4b-fix-pr105-idgen-1119/，RESULT.md 写在这里。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
