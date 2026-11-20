【任务】12j HR5-b systemd 模板和 kill -9 脚本（1-13，SWE-2）
前置：12a 已合并；没合并就写“等 12a”结束。
分支 feat/systemd-selfheal。内容：仓内 systemd 单元模板，Restart=always，用 StartLimit 做崩溃循环熔断；kill -9 验收脚本，目标 3 秒内自动拉起。只交付仓内模板和测试用例，不部署。
验收：在本机用 19100 到 19199 之间的端口起 webman，kill -9 一个 worker 后 3 秒内拉起，脚本记录耗时；跑完停掉进程，确认端口已释放。
开工规则（技术总监定）：依赖 #81 的项只卡合并不卡开工；有“前置”的项开工时只查一次，不满足就写“等 X”结束，不等待。本任务不合并。

通用条款（每项都带上）：
- 从最新 main 开分支，开工时写明基线 SHA。
- 需求以 results/10g-fix-plan-review2-1119/RESULT.md 里对应 M 行和 HR 项为准，并逐项回答 #109 清单涉及的编号。
- 不建库、不启库，不往任何豁免、白名单或 baseline 里新增条目，不强推，不 reset --hard，不 --no-verify，不 git add -A，不用云端，不用 Cursor，不读秘密，本地服务只用 19100 到 19199 端口。
- 本地跑通 unit、phpstan、pre-merge --fast、naming --diff。
- 交付：一个未合并 PR 加 RESULT（写命令、退出码、关键输出），最后单独输出一行 === RUN COMPLETE ===（见文末）。
- 合并前先确认 #81 已合并，再 merge main，CI 里 phpunit 和 phpstan 两个 job 都绿了，过 Fusion 审计才合。
- 这一批都不碰前端。如果做到一半发现要改 web，就停下写进 RESULT，前端部分另排，并先用 Semi MCP。

任务目录是 ~/agent-work/assistant/12j-systemd-selfheal-1119/，RESULT.md 写在这里。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
