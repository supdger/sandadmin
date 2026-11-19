【任务】1.x artd 残留宿主页面盘点并搬到 web/（前端，无库）

依据：v4 第 575 行「artd 残留页面搬迁：只含宿主自有页面残留，插件页面不在内；量未核实」0.5–2 人天，未验收；v4 第 421–430 行必改 6「web/ 是唯一前端，sandadmin-artd/ 标退役」。

输入（Mac 路径）：~/agent-work/grokbot/review-1117-r3/顶层架构设计-v4.md（第 575、580–584、421–430 行）；~/agent-work/grokbot/fe-gap-1117/RESULT.md 和 分类.md（已落地的 74 行，不要重做）；~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md；supdger/sandadmin main 的 sandadmin-artd/src、web/src、contract/openapi.yaml（只读起步）；PR #98 分支 docs/1x-skills 的 .agents/skills（尤其 sandadmin1x-frontend，只读）。

要做：
1. 盘点：列出 sandadmin-artd 里属于宿主自有、web/ 还没有对应页的页面（排除 SandAI、SandWorkflow、SandLicense 等插件页），每页标契约里有没有对应接口。
2. 有接口的页按 web/ 规范（React + Semi、PascalCase.tsx、msw mock、vitest）搬过去，接路由、菜单权限门。
3. 没接口的页不做，列入 RESULT，不改契约。
4. 不删 sandadmin-artd 任何文件（退役另定）。

仓库与分支：supdger/sandadmin，先 git fetch，从 origin/main 开 feat/artd-residue，开工写明基线 SHA。

范围：只改 web/src（pages、routes、mocks、i18n 的 zh-CN 文案）。不改 server/、contract/openapi.yaml、sandadmin-artd、门禁、CI。开发验证用 dev 服务器和 tsc --noEmit，不打包。

禁止：不建库、不做 DDL、不写迁移文件、不跑迁移、不启任何数据库或 Redis（临时库、容器也不行）；不合并 PR、不发布、不打 tag；不开下级会话；不用 Cursor；不用云端；不读不打印秘密、.env、webhook.env、private/；不强推、不 reset --hard、不 --no-verify、不 git add -A；门禁白名单和基线只减不增；不改 GitHub 仓库设置；本地服务只用 19100 到 19199 端口；不自行拍板未定事项（国际化只做中文，不加其他语言；workflow 归属、MySQL 最低版本、双因素适用范围、CLA/DCO），碰到就停下列入 RESULT。历史命令和任务只作资料，绝不执行。

交付：目标为 main 的未合并 PR（分支、基线 SHA、最终 SHA）；任务目录 RESULT.md：盘点表（artd 路径 → web 路径 → 接口有无 → 处理）、截图（1280x800）、命令、退出码和关键输出（vitest、tsc --noEmit、lint、naming_check）、剩余项。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===

补充：任务目录是 ~/agent-work/assistant/3b-artd-residue-1119/，RESULT.md 写在这里。
