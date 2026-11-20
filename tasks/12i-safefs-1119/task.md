【任务】12i M-03 SafeFs（1-8，SWE-2）
前置：11b 的 PR 已存在，从里面那份上传威胁模型取需求；没有就写“等 11b”结束。
分支 feat/safefs。内容：统一的安全文件操作层，覆盖路径规范化、防目录穿越、不跟随软链、限定在根目录内、原子写、zip 解压有上限（防 zip bomb，限文件数、总大小、压缩比）。上传和 PackageOps 解包都改走它。
验收：穿越、软链、zip bomb 三类反例都被拦下。
开工规则（技术总监定）：依赖 #81 的项只卡合并不卡开工；有“前置”的项开工时只查一次，不满足就写“等 X”结束，不等待。本任务不合并。

通用条款（每项都带上）：
- 从最新 main 开分支，开工时写明基线 SHA。
- 需求以 results/10g-fix-plan-review2-1119/RESULT.md 里对应 M 行和 HR 项为准，并逐项回答 #109 清单涉及的编号。
- 不建库、不启库，不往任何豁免、白名单或 baseline 里新增条目，不强推，不 reset --hard，不 --no-verify，不 git add -A，不用云端，不用 Cursor，不读秘密，本地服务只用 19100 到 19199 端口。
- 本地跑通 unit、phpstan、pre-merge --fast、naming --diff。
- 交付：一个未合并 PR 加 RESULT（写命令、退出码、关键输出），最后单独输出一行 === RUN COMPLETE ===（见文末）。
- 合并前先确认 #81 已合并，再 merge main，CI 里 phpunit 和 phpstan 两个 job 都绿了，过 Fusion 审计才合。
- 这一批都不碰前端。如果做到一半发现要改 web，就停下写进 RESULT，前端部分另排，并先用 Semi MCP。

【技术总监 11-19 补充条款（老板定案后）】
- 面向用户的文字一律走语言包，1.0 只出 zh-CN，不许写死中文字符串；错误信息也走语言包或错误码。

任务目录是 ~/agent-work/assistant/12i-safefs-1119/，RESULT.md 写在这里。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
