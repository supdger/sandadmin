【任务】PR #102 合并前独立审计（只读，只写 RESULT.md）
背景：老板定本地 Opus 审计通过视为批准。PR #102 密钥管理与 Crypto（feat/crypto-keyprovider） 已由技术总监验收通过，CI 全绿。
模型分工：主代理只判断写结论；读 diff、跑只读命令全派给 SWE-2 子代理（本机 dv-* 子代理，不算下级会话）。
要审：范围是否只在该功能模块和测试；安全问题（秘密处理、权限、注入、日志泄露）；门禁文件只减不增；与 ~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md 不冲突；gh pr checks 102 只读确认。
结论第一行三选一：可合并、改后可合并（列必须修改点）、不可合并。不合并、不推送、不评论、不改文件。

通用禁止：不建库、不启库、不改 CI 和门禁脚本、不往任何豁免或白名单里加条目、不合并、不强推、不reset --hard、不 --no-verify、不 git add -A、不用云端、不用 Cursor、不开下级会话、不读不打印秘密。

任务目录是 ~/agent-work/assistant/4d-audit-pr102-1119/，RESULT.md 写在这里。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
