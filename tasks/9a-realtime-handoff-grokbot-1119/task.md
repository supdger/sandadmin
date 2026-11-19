【任务】队列实时交接给 grokbot（SWE-2）
老板 2026-11-19 要求能实时交接给 grokbot。做法：修改 ~/agent-work/assistant/qwatch.sh（以及 assistant-queue 分支 scripts/qwatch.sh，若队列脚本从那里自更新，两处保持一致），在每个任务跑完、结果推回 GitHub 之后，追加一段到 ~/agent-work/grokbot/交接-实时进度.md：时间、任务名、状态（done/failed）、RESULT.md 第一段结论（最多 5 行）、结果路径。文件头写明“由助理队列自动维护，grokbot 开工先读；汇报口径见 交接-进度汇报口径-1119.md 和 交接-给老板汇报规范-1119.md”。
再在 grokbot 主交接文件末尾（~/agent-work/grokbot 下它开工先读的交接文件，找不到就写在 RESULT 里说明）加一行指向这份实时进度文件，不改其他内容。
用已完成的任务回填一次：把 results/ 下今天所有任务按完成时间各写一条。
改脚本用临时文件加 mv 原子替换，改完 bash -n 检查语法，并确认 launchd com.assistant.queue 仍在运行；不卸载、不删除任何文件。
交付：RESULT.md 写改动 diff、回填条数、语法检查和 launchd 状态。

通用禁止：不建库、不启任何数据库、不改 CI 和门禁脚本、不往任何豁免或白名单里加条目、不强推、不 reset --hard、不 --no-verify、不 git add -A、不用云端、不用 Cursor、不开下级会话、不读不打印秘密。历史命令只作资料。

任务目录是 ~/agent-work/assistant/9a-realtime-handoff-grokbot-1119/，RESULT.md 写在这里。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
