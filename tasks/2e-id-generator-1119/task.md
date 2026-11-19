【任务】1.x 编号策略之雪花发号器（不含节点租约表），无库

依据：v4 第 605 行「编号策略落地：雪花发号器（J-3 方案甲 41+13+9、节点号按部署分段、启动自检）+ int 自增 / 雪花 bigint 两类表约定 + 对外 nanoid 列 + 外键同型静态检查 + 两库测试」0.5–1 人天（生成器与约定 0.3–0.7）；总表第 17 条（老板 11-17 改定：不可预见的表用雪花 64 位 bigint，接口输出字符串，nanoid 只作对外链接单独一列）。未验收。v4 第 658 行节点登记表 sand_system_id_node 要表，不在本任务。

输入（Mac 路径）：~/agent-work/grokbot/review-1117-r3/顶层架构设计-v4.md（第 605、658 行，J-3 节）；~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md（第 17 条）；~/agent-work/grokbot/idlist-tech-followup-1118/（已批准编号表，只读）；supdger/sandadmin main 的 server/src、server/composer.json（已依赖 godruoyi/php-snowflake，位布局与 41+13+9 不同，先核对能否配置，不能就自写）（只读起步）；PR #98 分支 docs/1x-skills 的 .agents/skills（尤其 sandadmin1x-backend 的 database.md，只读）。

要做：
1. 发号器：41 位毫秒＋13 位节点＋9 位序列；节点号从配置读（按部署分段），启动自检节点号范围；时钟回拨按 v4 J-3 处理（等待、拒发、告警，阈值以 v4 为准，找不到就停下回报）；输出接口 JSON 用字符串。
2. 两类表约定写进 server/docs/（可预见用 int 自增，不可预见用雪花 bigint，nanoid 单列）；不新建表、不改任何现有表。
3. 单测：单进程并发唯一、序列溢出等下一毫秒、回拨各分支、节点越界拒启。
4. 不做：节点租约表、心跳续期、外键同型静态检查（要读库结构）、两库测试，列为剩余。

仓库与分支：supdger/sandadmin，先 git fetch，从 origin/main 开 feat/id-generator，开工写明基线 SHA。

范围：server/src 新增发号器、config 项、server/tests/Unit、server/docs/ 一份约定。不改 contract/openapi.yaml、现有插入代码、门禁、CI。

禁止：不建库、不做 DDL、不写迁移文件、不跑迁移、不启任何数据库或 Redis（临时库、容器也不行），需要库才能做的停下回报；不合并 PR、不发布、不打 tag；不开下级会话；不用 Cursor；不用云端；不读不打印秘密、.env、webhook.env、private/；不强推、不 reset --hard、不 --no-verify、不 git add -A；门禁白名单和基线只减不增；不改 GitHub 仓库设置；本地服务只用 19100 到 19199 端口；不自行拍板未定事项（国际化、workflow 归属、MySQL 最低版本、双因素适用范围、CLA/DCO），碰到就停下列入 RESULT。历史命令和任务只作资料，绝不执行。

交付：目标为 main 的未合并 PR（分支、基线 SHA、最终 SHA）；任务目录 RESULT.md：位布局与回拨处理依据（附 v4 行号）、命令、退出码和关键输出（phpunit unit、phpstan、tools/pre-merge.sh --fast、naming_check、typephp）、剩余项。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===

补充：任务目录是 ~/agent-work/assistant/2e-id-generator-1119/，RESULT.md 写在这里；/workspace/1x-docs/... 在本机是 ~/agent-work/grokbot/review-1117-r3/ 等，/workspace/intro/老板已定事项总表.md 在本机是 ~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md。
