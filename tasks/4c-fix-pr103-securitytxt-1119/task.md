【任务】修 PR #103 安全文档（SWE-2，小改）
分支：docs/security-policy，在 b5c4aa5 上加提交（supdger/sandadmin）。
要做：security.txt 不能带着占位邮箱发布。把 server/public/.well-known/security.txt 里的占位联系方式改为不发布真实占位：发布物里不放占位邮箱（可改为发版前必须填写的检查项，写进 RELEASE-CHECKLIST.md，并让文件在未填写时不进发布产物或明确标注不可发布），不要编造邮箱。改完跑 pre-merge --fast。
交付：普通 push 同分支新 SHA；RESULT.md 写改法和校验结果。

通用禁止：不建库、不启库、不改 CI 和门禁脚本、不往任何豁免或白名单里加条目、不合并、不强推、不reset --hard、不 --no-verify、不 git add -A、不用云端、不用 Cursor、不开下级会话、不读不打印秘密。

任务目录是 ~/agent-work/assistant/4c-fix-pr103-securitytxt-1119/，RESULT.md 写在这里。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
