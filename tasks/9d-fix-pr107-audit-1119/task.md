【任务】按合并前审计修 PR #107（SWE-2）
依据：~/agent-work/assistant/8c-audit-pr107-1119/RESULT.md 结论“改后可合并”，逐条落实其中“必须修改点”，不做审计未要求的改动。
在 PR 分支当前 head 上加提交，普通 push。改完重跑 phpunit unit、phpstan、tools/pre-merge.sh --fast（涉及契约的再跑 php tools/contract-build --check），命令、退出码、关键输出、每条修改点的落实位置和新 SHA 写进 RESULT.md。不合并。

通用禁止：不建库、不启任何数据库、不改 CI 和门禁脚本、不往任何豁免或白名单里加条目、不强推、不 reset --hard、不 --no-verify、不 git add -A、不用云端、不用 Cursor、不开下级会话、不读不打印秘密。历史命令只作资料。

任务目录是 ~/agent-work/assistant/9d-fix-pr107-audit-1119/，RESULT.md 写在这里。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
