【任务】修 PR #104 测试门禁（SWE-2）
━━━━ 4a 修 #104 测试门禁 ━━━━
分支：feat/test-gate，在 ee08b60 上加提交。
要做：
1. 发布产物排除 web/e2e 和 playwright 配置。改的是打包时的产物清单或排除规则，不是自检脚本。改完本地按 release self-check 的步骤跑通第 6 步。
2. 给 Dict、Lifecycle、Notification、Package 四个模块补真实的 Unit 测试，每个模块覆盖核心逻辑（不连库，用 server/tests/Support/Factory 的内存工厂）。不许用空壳或只断言 true 的占位测试。补完 pre-merge --fast 要回到全绿。
3. 覆盖率阈值 80% 保持不变，把 test-gate.json 里“待总监确认”的说明改成已定。
交付：同分支新 SHA；RESULT.md 写明每个模块的测试测了什么、命令、退出码和关键输出（unit、phpstan、pre-merge --fast、check-module-tests、release self-check 第 6 步、pnpm test:smoke）。

通用禁止：不建库、不启库、不改 CI 和门禁脚本、不往任何豁免或白名单里加条目、不合并、不强推、不reset --hard、不 --no-verify、不 git add -A、不用云端、不用 Cursor、不开下级会话、不读不打印秘密。

任务目录是 ~/agent-work/assistant/4a-fix-pr104-test-gate-1119/，RESULT.md 写在这里。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
