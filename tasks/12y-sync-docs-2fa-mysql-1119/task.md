【任务】按老板 11-19 定案同步两处文档，审计后合并
模型分工：主代理只做审计判断；改文件、跑命令、git、合并全派给 SWE-2 子代理（本机 dv-* 子代理，不算下级会话）。
一、改（从最新 main 开分支 docs/sync-2fa-mysql，写基线 SHA）：
1. .agents/skills/LEGACY-ABSORBED.md 和 COVERAGE.md 里 R-1、R-2 的口径：“全局双因素默认关”改成“后台管理员必开（TOTP 加恢复码），普通账号默认关、可按角色强制开；高危操作一律再验证”。
2. #109 高标准清单（.agents/skills/sandadmin1x-design-standard/）里写到 MySQL 8.0 的地方统一改成 MySQL 8.4 LTS（PG 15 起不变）。
只改这些口径，不改其他内容。跑 skill-sync 校验、naming_check.py notes、tools/pre-merge.sh --fast，普通 push，开 PR。
二、审计：只改了上述口径、与老板已定事项一致、门禁只减不增。RESULT 第一行写结论和审计时 head。
三、结论“可合并”且 checks 全绿、无冲突时 gh pr merge --merge（不删分支），记录 main 新 SHA；否则不合写明。
通用禁止：不建库、不启库、不改 CI 和门禁脚本、不加豁免或白名单、不强推、不 reset --hard、不 --no-verify、不 git add -A、不用 --admin、不用云端、不用 Cursor、不开下级会话、不读秘密。

任务目录是 ~/agent-work/assistant/12y-sync-docs-2fa-mysql-1119/，RESULT.md 写在这里。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
