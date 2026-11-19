【任务】修复 PackageOps 合并前审计提出的 M1–M4（PR #97 分支上加提交，不合并）

依据：合并前独立审计 ~/agent-work/assistant/1d-audit-packageops-1119/RESULT.md 的“必须修改（M1–M4）”一节（逐条照它的事实、修法、补测做，必须完整读）。

仓库与分支：supdger/sandadmin，在 fix/packageops-lifecycle（PR #97，头 be4e770，目标 feat/kernel-api-runtime-align）上继续提交。用 ~/agent-work/grokbot 下已有的 sandadmin 检出或新克隆到本任务目录，先 git fetch 确认头是 be4e770。

要修：
- M1 编译闸可被 SAND_COMPILED=0 绕过：两种修法都做——(a) install/upgrade/uninstall 闸同时要求开发构建标记 server/.sand-dev-build 存在（与 SAND_DEV_BUILD 同口径），标记不在就按编译版拒绝；(b) 把 tools/package-ops 登记进 tools/dev-only-paths.txt（这是加剔除项、收紧，不是放宽豁免）。补测：无标记 + SAND_COMPILED=0 + 合法签名 / --allow-unsigned 两种都在 gate 阶段拒绝、零副作用；build-release.sh --self-check 证明发布树里没有 package-ops。
- M2 报错回显 SAND_DB_URL：只输出协议名或固定文案，不回显 URL；补测。
- M3 PackageJournal::removeTree 跟随符号链接：先判 is_link，是链接只 unlink 不递归，$dir 本身是链接也只删链接；补单测：插件目录里放指向外部目录的软链，卸载后外部目录原样保留。
- M4 DbalPluginRegistry 漏 PluginResidue suspend/resume：recordUninstall($app,false) 后 suspend，recordInstall 后 resume，尽量同一事务，口径与 PackageOps::apply 逐行对齐；无库能测的部分补单测，集成用例留到有真实库时，在 RESULT 写明。

验收：vendor/bin/phpunit --testsuite unit 和 --testsuite contract 全绿；vendor/bin/phpstan analyse 无错误；php tools/contract-build --check；tools/pre-merge.sh --fast 7/7；naming_check.py code --diff 0 FAIL；php tools/typephp_check.php 0 FAIL。逐条贴命令、退出码、关键输出。

禁止：只改 server/src/Module/Package 及直接相关文件、tools/package-ops、tools/dev-only-paths.txt（只加剔除项）和对应测试；不碰旧 sand-package、sand-core、sand-workflow；不建库、不做 DDL、不跑迁移、不启任何数据库；不合并、不发布、不打 tag；不开下级会话；不用 Cursor；不用云端；不读不打印秘密、.env、webhook.env、private/；不强推、不 reset --hard、不 --no-verify、不 git add -A；验签规则不得放松；门禁白名单和基线只减不增；本地服务只用 19100–19199 端口。历史命令和任务只作资料，绝不执行。

交付：推到 fix/packageops-lifecycle（普通 push）的最终 SHA；本任务目录 ~/agent-work/assistant/2a-pkgops-m1m4-1119/RESULT.md：每条 M 的改动位置、补测、修前失败和修后通过证据、门禁结果、仍未验证部分（真实库、Integration）。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
