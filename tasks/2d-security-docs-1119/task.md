【任务】1.x 安全文档：SECURITY.md、漏洞响应合规、插件签名密钥管理规范（只写文档）

依据：v4 第 858–870 行 N1（f1 第 1118 行 0.5–1 人天）；v4 第 633 行 OSS-01/OSS-02（工信部报送义务、security.txt、备用渠道，0.3–0.5）；v4 第 630 行 PLG-02 插件签名密钥管理（信任根、私钥保管、轮换、吊销成文，0.5–1；验签实现在 PR #94，不在本任务）。均未验收。

输入（Mac 路径）：~/agent-work/grokbot/review-1117-r3/顶层架构设计-v4.md（第 630、633、858–870 行，K-3 节）；同目录 鉴权P0-v4.md；~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md；supdger/sandadmin main 根目录文件、RELEASE-CHECKLIST.md、tools/build-release.sh（只读）；PR #94（feat/kernel-api-runtime-align）的签名相关代码，用 gh 只读查看，不评论、不检出改动。

要做：
1. 根目录 SECURITY.md：支持版本表（SandAdmin 自身版本，不写数据库最低版本）、私密报告渠道、确认和修复时限、披露与致谢、《网络产品安全漏洞管理规定》报送条款。
2. security.txt（放 server/public/.well-known/）和备用渠道说明；联系方式只写占位，在 RESULT 列出待总监填写的项。
3. 插件签名密钥管理规范：信任根、私钥保管、轮换、吊销、验签失败一律拒绝；与 PR #94 实际行为对照，不一致处列在 RESULT，不改 #94。放 server/docs/（根目录 /docs/ 被 .gitignore 忽略，不要用）。
4. RELEASE-CHECKLIST.md 加一条「SECURITY.md 存在且含漏洞报送条款」。
5. 开启 GitHub Private Vulnerability Reporting 是仓库设置，不要动，在 RESULT 列为待总监操作。

仓库与分支：supdger/sandadmin，先 git fetch，从 origin/main 开 docs/security-policy，开工写明基线 SHA。

范围：只新增或修改 SECURITY.md、server/public/.well-known/security.txt、server/docs/ 下一份规范文档、RELEASE-CHECKLIST.md。不改代码、contract、门禁、CI。

禁止：不建库、不做 DDL、不写迁移文件、不跑迁移、不启任何数据库或 Redis（临时库、容器也不行）；不合并 PR、不发布、不打 tag；不开下级会话；不用 Cursor；不用云端；不读不打印秘密、.env、webhook.env、private/、任何私钥文件；不强推、不 reset --hard、不 --no-verify、不 git add -A；门禁白名单和基线只减不增；不改 GitHub 仓库设置；不自行拍板未定事项（国际化、workflow 归属、MySQL 最低版本、双因素适用范围、CLA/DCO），碰到就停下列入 RESULT。历史命令和任务只作资料，绝不执行。

交付：目标为 main 的未合并 PR（分支、基线 SHA、最终 SHA）；任务目录 RESULT.md：各文件位置与对应 v4 验收条款逐条对照、与 PR #94 不一致处、待总监填写或操作的项、命令、退出码和关键输出（python3 tools/naming_check.py notes、tools/pre-merge.sh --fast）。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===

补充：任务目录是 ~/agent-work/assistant/2d-security-docs-1119/，RESULT.md 写在这里；/workspace/1x-docs/... 在本机是 ~/agent-work/grokbot/review-1117-r3/ 等，/workspace/intro/老板已定事项总表.md 在本机是 ~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md。
