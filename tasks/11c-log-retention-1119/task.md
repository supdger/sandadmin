【任务】11c N-p 日志留存修正（0-7，SWE-2，不建库）━━━━
分支：从 main 开 fix/log-retention。
现状：main 上 server/config/log.php 第 30 行和第 48 行的 maxFiles 都是 181，只能留 180 天。
要做：按 X5 修正。安全日志（登录、操作、审计、找回和双因素、权限变更）下限 6 个月，按自然月算，不许调低。普通日志默认 183 天，可以下调到 30 天。maxFiles 取天数加 1，并加启动自检：配置低于下限就拒绝启动。补 Unit 测试。
验收：unit、phpstan、pre-merge --fast 全过；RESULT 写明每个通道的天数和换算方式。

通用条款（技术总监定）：不建库、不启库，不往任何豁免、白名单或 baseline 里新增条目，不强推，不 reset --hard，不 --no-verify，不 git add -A，不用云端，不用 Cursor，不开下级会话，不读秘密。不合并（合并前另排 Fusion 审计）。

【技术总监 11-19 补充条款（老板定案后）】
- 面向用户的文字一律走语言包，1.0 只出 zh-CN，不许写死中文字符串；错误信息也走语言包或错误码。

任务目录是 ~/agent-work/assistant/11c-log-retention-1119/，RESULT.md 写在这里。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
