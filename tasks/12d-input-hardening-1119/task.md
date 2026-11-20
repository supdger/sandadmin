【任务】12d M-08 三条禁令和 trusted_proxies（1-10，SWE-2）
分支 feat/input-hardening。内容：做 10g M-08 行里的三条禁令（通用输出编码、反序列化限制、批量赋值白名单），落成门禁或运行期校验；加 trusted_proxies 配置，只认名单里代理给的 X-Forwarded-For 等转发头，生产环境没配就忽略转发头。
验收：每条禁令有一个反例测试被拦下；用不可信来源伪造 XFF 拿不到伪造的 IP。
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

任务目录是 ~/agent-work/assistant/12d-input-hardening-1119/，RESULT.md 写在这里。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
