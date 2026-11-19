【任务】PR #98 在 AGENTS.md 加一句指向 LEGACY-ABSORBED.md（SWE-2，只改一个文件）

仓库与分支：supdger/sandadmin，docs/1x-skills（头 685b771），用 ~/agent-work/assistant/1x-skills-1119/sandadmin 工作副本，先 git fetch 确认头。

要做：在 AGENTS.md 第 72 行附近（指向 COVERAGE 的那段旁边）加一句：“读 delivery-loop、backend-structure、packaging、database 这几份同步 skill 时，以 .agents/skills/LEGACY-ABSORBED.md 对应小节为必守补充条款。” 只改 AGENTS.md，不改别的文件。重跑 skill-sync 校验、naming_check.py notes、tools/pre-merge.sh --fast，贴命令、退出码、关键输出。逐个 git add，普通 push 到同分支。

禁止：不改 ~/.shared-skills；不改其他文件；不合并；不强推、不 reset --hard、不 --no-verify、不 git add -A；不建库；不开下级会话；不用云端；不读不打印秘密。

交付：~/agent-work/assistant/3g-pr98-agents-pointer-1119/RESULT.md 写新 SHA 和校验结果。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
