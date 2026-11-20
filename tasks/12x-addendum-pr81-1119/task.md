【任务】#81 按老板 11-19 定案追加一轮（SWE-2）
背景：11a 已跑完（结果见 ~/agent-work/assistant/11a-ci-gates-pr81-1119/RESULT.md），技术总监在老板定案后追加条款。在 #81 分支（feat/merge-gates）当前 head 上加提交：
1. 慢档三库矩阵去掉 mysql:8.0，保留 mysql:8.4；PG 档改成两档：一档 15（最低版本），一档 16 或更新。
2. 发版自检加一条：生产包里不得出现免登录入口（dev-login 路由、DevPrincipal、开发期 mock 登录），出现就判红；开一个故意放进免登录入口的临时 PR 演示被拦，演示完关掉。
3. 面向用户的文字一律走语言包，1.0 只出 zh-CN，不写死中文字符串。
4. 更新 #81 的 PR 描述到现状（现在还写着 mysql:8.0、46 条存量、已删掉的慢档审计 job；strict-types 存量按文件实际 29 行）。
5. baseline 闸在“BASE ref 存在但 pre-merge.sh blob 缺失”时会放行，改成判失败，并补一条测试。
重跑 unit、phpstan、tools/pre-merge.sh --fast，普通 push，RESULT 写命令、退出码、新 SHA 和演示 PR 链接。不合并。
通用禁止：不建库、不启库、不往豁免、白名单或 baseline 新增条目、不强推、不 reset --hard、不 --no-verify、不 git add -A、不用云端、不用 Cursor、不开下级会话、不读秘密。

任务目录是 ~/agent-work/assistant/12x-addendum-pr81-1119/，RESULT.md 写在这里。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
