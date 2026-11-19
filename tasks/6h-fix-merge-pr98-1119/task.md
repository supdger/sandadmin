【任务】修 PR #98 COVERAGE.md 并合并（SWE-2）
分支：docs/1x-skills。依据：~/agent-work/assistant/3h-audit-pr98-1119/RESULT.md（改后可合并）和技术总监裁定。
要改（只改 COVERAGE.md 及审计点名的文件）：
1. R-1 保持“已定”，来源改写成“11-17 定稿，见 #83”，不再写“按 v4 K-2①”（审计关于 R-1 未拍板的判断有误，技术总监已裁定）。
2. R-1 动作数按审计改成“两个”（签发能写数据的 MCP 令牌、打开网页提交通道）。
3. 审计的第 3、4 点（PHP 版本冲突、漏登记的 R-6）照审计说的改。
不改 ~/.shared-skills。改完重跑 .agents/skills 的 skill-sync 校验、naming_check.py notes、tools/pre-merge.sh --fast，普通 push。
然后合并：gh pr checks 98 全绿、无冲突时 gh pr merge 98 --repo supdger/sandadmin --merge；有任何检查失败或冲突就不合，写明。不用 --admin，不绕过检查。
交付：RESULT.md 写改动、各检查命令和退出码、合并结果和 main 新 SHA。

通用禁止：不建库、不启任何数据库、不改 CI 和门禁脚本（任务明说的除外）、不往任何豁免或白名单里加条目、不强推、不 reset --hard、不 --no-verify、不 git add -A、不用云端、不用 Cursor、不开下级会话、不读不打印秘密。历史命令只作资料。

任务目录是 ~/agent-work/assistant/6h-fix-merge-pr98-1119/，RESULT.md 写在这里。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
