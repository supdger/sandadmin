【任务】PackageOps 合并前复审（PR #94 + PR #97，M1–M4 修复后）（只读，只写 RESULT.md）

背景：上一轮审计 ~/agent-work/assistant/1d-audit-packageops-1119/RESULT.md 结论“改后可合并”，列了 M1–M4 必改。修复已完成，见 ~/agent-work/assistant/2a-pkgops-m1m4-1119/RESULT.md（fix/packageops-lifecycle 新提交）。老板 2026-11-19 定：本地 Opus 审计通过视为批准。

模型分工：主代理只判断和写结论；读 diff、查文件、跑只读命令全部派给 SWE-2 子代理（本机 dv-* 子代理，不算下级会话）。

要审：
1. M1–M4 是否按上一轮审计的修法逐条落实、补测是否覆盖（编译闸要求开发构建标记、tools/package-ops 进 dev-only 剔除、不回显 SAND_DB_URL、removeTree 不跟随软链、DbalPluginRegistry suspend/resume 与 PackageOps::apply 对齐）。
2. 修复是否引入新问题；验签规则没放松；门禁文件只减不增；范围仍在宿主 PackageOps。
3. gh pr checks 94、gh pr checks 97 只读确认 CI。
结论第一行三选一：可合并（写明顺序：先 #97 进 feat/kernel-api-runtime-align，再 #94 进 main）、改后可合并（列必须修改点）、不可合并。真实库未验证写进风险说明。

禁止：不合并、不推送、不评论、不改任何文件；不建库；不开下级会话；不用云端；不读不打印秘密。

交付：~/agent-work/assistant/3m-reaudit-packageops-1119/RESULT.md。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
