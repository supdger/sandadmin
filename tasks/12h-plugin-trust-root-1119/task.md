【任务】12h HR1 信任根定稿和构建期判定（1-7）
前置：11b 的 PR 已存在（看 docs/threat-model 分支），不需要合并；没有就写“等 11b”结束。
分两步：
- a 用 Fusion 模型 fusion-claude-opus-5-5-medium-sidekick-swe-2-medium：基于 11b 的 PackageOps 威胁模型，定稿信任根，写进文档。内容包括谁持有签名私钥、公钥怎么随版本分发、怎么吊销和轮换，并写明“插件等于完全信任”。
- b 用 SWE-2：实现构建期的签名标记判定。保持 Ed25519，先验签后解包，编译版禁止安装未签名包，signature_verified 不许写假值。
分支 feat/plugin-trust-root。
模型分工：主代理只做设计判断和结论；读代码、写代码、跑命令、git 操作全派给 SWE-2 子代理（本机 dv-* 子代理，不算下级会话）。
开工规则（技术总监定）：依赖 #81 的项只卡合并不卡开工；有“前置”的项开工时只查一次，不满足就写“等 X”结束，不等待。本任务不合并。

通用条款（每项都带上）：
- 从最新 main 开分支，开工时写明基线 SHA。
- 需求以 results/10g-fix-plan-review2-1119/RESULT.md 里对应 M 行和 HR 项为准，并逐项回答 #109 清单涉及的编号。
- 不建库、不启库，不往任何豁免、白名单或 baseline 里新增条目，不强推，不 reset --hard，不 --no-verify，不 git add -A，不用云端，不用 Cursor，不读秘密，本地服务只用 19100 到 19199 端口。
- 本地跑通 unit、phpstan、pre-merge --fast、naming --diff。
- 交付：一个未合并 PR 加 RESULT（写命令、退出码、关键输出），最后单独输出一行 === RUN COMPLETE ===（见文末）。
- 合并前先确认 #81 已合并，再 merge main，CI 里 phpunit 和 phpstan 两个 job 都绿了，过 Fusion 审计才合。
- 这一批都不碰前端。如果做到一半发现要改 web，就停下写进 RESULT，前端部分另排，并先用 Semi MCP。

任务目录是 ~/agent-work/assistant/12h-plugin-trust-root-1119/，RESULT.md 写在这里。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
