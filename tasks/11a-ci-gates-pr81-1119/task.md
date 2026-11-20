【任务】11a HR3-CI + M-27（1-1、1-1b，SWE-2，不建库）━━━━
载体：#81（feat/merge-gates，head 1bc282f）。先 merge main，不强推，不另开平行 PR。
现状：main 上只有 dependency-audit.yml（composer audit、frontend prod audit、release self-check，PHP 8.2），没有任何 PHPUnit 或 PHPStan job。
要做：
1. 只读核实 #81 现有的 job 和 main 上已有的 job，列出重复项和冲突项。
2. 固定四个 job 名：phpunit（无库套件，连库用例分组排除）、phpstan（level max）、dependency-audit（沿用现有 job，名字对齐即可）、security-regression（跑 #107 的安全头断言和 CSRF/CORS 单测）。快档要和 pre-merge.sh --fast 跑同一套命令。
3. M-27：composer.json 的 PHP 约束改成 >=8.4，platform 设为 8.4.x，CI 全部用 8.4。现有 8.2 的 job 一起升。
4. #81 的慢档（三库矩阵、e2e 槽位）这次不进必过，保留成非必过。
5. strict-types-baseline.txt 的 46 条我准许作为存量登记，但只准减不准增。PHPStan 不许新建 baseline：level max 不过就先修代码。
6. 发版产物附 SBOM 和构建 provenance，产物名和 job 名写进 RELEASE-CHECKLIST.md。
验收：开一个故意引入 PHPStan 错误的临时 PR，CI 要判红，演示完关掉；无库 phpunit 在 CI 上为绿；job 名和下面 HR3-SET 的清单一字不差；发版产物里有 SBOM 和 provenance。
交付：#81 的新 SHA；RESULT 写清 job 清单、命令、退出码、红 PR 的链接。

注意：本任务只做到开出/更新 #81 并完成验收演示，不合并、不改仓库设置；合并和分支保护（HR3-SET）在 Fusion 审计通过后另排任务执行。

通用条款（技术总监定）：不建库、不启库，不往任何豁免、白名单或 baseline 里新增条目，不强推，不 reset --hard，不 --no-verify，不 git add -A，不用云端，不用 Cursor，不开下级会话，不读秘密。不合并（合并前另排 Fusion 审计）。

【技术总监 11-19 补充条款（老板定案后）】
- 慢档三库矩阵去掉 mysql:8.0，保留 mysql:8.4；PG 档改成两档：一档 15（最低版本），一档 16 或更新。发版自检加一条：生产包里不得出现免登录入口（dev-login 路由、DevPrincipal、开发期 mock 登录），出现就判红；故意放进一个免登录入口演示一次被拦。
- 面向用户的文字一律走语言包，1.0 只出 zh-CN，不许写死中文字符串；错误信息也走语言包或错误码。

任务目录是 ~/agent-work/assistant/11a-ci-gates-pr81-1119/，RESULT.md 写在这里。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
