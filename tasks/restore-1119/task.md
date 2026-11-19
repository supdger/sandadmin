【任务】恢复 ~/.shared-skills 只读原文，并把 6 段补文挪进 1.x 专属 skill（只改文档）

背景：上一轮任务（~/agent-work/assistant/skillfix-1119/，RESULT.md 和 run.log 在那里）为了让 skill-sync 校验通过，把 6 段补文同时写进了 Mac 上 ~/.shared-skills 的 4 个文件。共享约束规定 ~/.shared-skills 是只读原文，不得改写。

要做：
1. 先把 ~/.shared-skills 这 4 个文件当前内容和上一轮写入前的内容做 diff，存到本任务目录 diff-before.txt。只恢复上一轮写入的部分，别的不动。恢复后再存一份 diff-after.txt，证明与写入前一致。
2. 在 ~/agent-work/assistant/1x-skills-1119/sandadmin 的 docs/1x-skills 分支上（先 git fetch，头应为 b7bc4c5 或其后续），把这 6 段补文挪到不参与 skill-sync 同步比对的 1.x 专属 skill 文件里，原文件里只留一行指向新位置；COVERAGE.md 那 6 行的位置跟着改。
3. 不许改 skill-sync 脚本或白名单，不许再写 ~/.shared-skills。挪不出去、非改共享副本不可，就停下，在 RESULT.md 写明原因，不要自行处理。
4. 重跑 skill-sync 校验和 tools/pre-merge.sh --fast，贴命令、退出码、关键输出（要连库就停下写明）。
5. 逐个 git add 文件，提交并普通 push 到 docs/1x-skills，不合并。

禁止：不改 server/src、contract/openapi.yaml、门禁文件和 CI；不建库、不做 DDL、不跑迁移、不启任何数据库；不合并、不发布、不打 tag；不开下级会话；不用 Cursor；不用 Claude；不用云端；不读不打印秘密、.env、webhook.env、private/；不强推、不 reset --hard、不 --no-verify、不 git add -A。

交付：本任务目录 ~/agent-work/assistant/restore-1119/RESULT.md：恢复了哪些文件、diff 证据、补文新位置、最终 SHA、校验结果。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
