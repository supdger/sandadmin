【任务】1.x 框架测试判定口径（独立审计新增表 #12），无库

依据：v4 第 1153 行「测试判定口径（M-11 其余、S-15）：模块＝Module 目录＋测试目录缺即失败、改动行覆盖率阈值、测试数据工厂、菜单页 Playwright 冒烟」1.5–3 人天，未验收。

输入（Mac 路径）：~/agent-work/grokbot/review-1117-r3/顶层架构设计-v4.md（第 1153 行，U-6 节第 2204 行起）；~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md（第 24 条）；supdger/sandadmin main 的 tools/pre-merge.sh、server/phpunit.xml.dist、server/src/Module、server/tests、web/（含 src/mocks msw）（只读起步）；PR #98 分支 docs/1x-skills 的 .agents/skills（只读）。

要做：
1. 检查脚本：server/src/Module/<X> 存在而 server/tests 下没有对应测试目录时失败；现有缺口先列清单，不加豁免。若现有模块缺测试导致主干直接失败，停下在 RESULT 列出，不改白名单。
2. 改动行覆盖率阈值：只对 diff 改动行计算（pcov 或 xdebug，本机没有就停下报告），阈值写进配置，起步值写在 RESULT 待总监确认。
3. 测试数据工厂：Unit 可用的纯内存工厂（不连库）。
4. 菜单页 Playwright 冒烟：web/ 开发服务器配 msw mock，逐个菜单页打开、断言无报错、无空白页；端口用 19100 到 19199。
5. 1、2 接入 tools/pre-merge.sh（--fast 跑 1，--full 跑 2；冒烟单列命令）。

仓库与分支：supdger/sandadmin，先 git fetch，从 origin/main 开 feat/test-gate，开工写明基线 SHA。

范围：tools/ 新脚本、tools/pre-merge.sh、server/phpunit.xml.dist、server/tests/Support 工厂、web/ 下 Playwright 配置和冒烟用例。不改 server/src 业务代码、contract/openapi.yaml、CI。

禁止：不建库、不做 DDL、不写迁移文件、不跑迁移、不启任何数据库或 Redis（临时库、容器也不行），需要库才能做的停下回报；不合并 PR、不发布、不打 tag；不开下级会话；不用 Cursor；不用云端；不读不打印秘密、.env、webhook.env、private/；不强推、不 reset --hard、不 --no-verify、不 git add -A；门禁白名单和基线只减不增；不改 GitHub 仓库设置；本地服务只用 19100 到 19199 端口；不自行拍板未定事项（国际化、workflow 归属、MySQL 最低版本、双因素适用范围、CLA/DCO），碰到就停下列入 RESULT。历史命令和任务只作资料，绝不执行。

交付：目标为 main 的未合并 PR（分支、基线 SHA、最终 SHA）；任务目录 RESULT.md：四项各自的实现位置、现有缺测试模块清单、阈值起步值、命令、退出码和关键输出（tools/pre-merge.sh --fast 与 --full 的 Unit 部分、phpstan、naming_check、typephp、vitest、tsc --noEmit、Playwright 冒烟）、剩余项。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===

补充：任务目录是 ~/agent-work/assistant/2c-test-gate-1119/，RESULT.md 写在这里；/workspace/1x-docs/... 在本机是 ~/agent-work/grokbot/review-1117-r3/ 等，/workspace/intro/老板已定事项总表.md 在本机是 ~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md。
