# PR #98 — AGENTS.md 指向 LEGACY-ABSORBED.md

## 结果

- 仓库：supdger/sandadmin
- 分支：docs/1x-skills（已 push，普通 push，无强推）
- 旧头：`685b77178e443cb13ab7368ceaf79f20c67cab31`
- 新头：`1008135e84ebc1ef6d2d0c5521bb65abea96651d`
- 工作副本：`~/agent-work/assistant/1x-skills-1119/sandadmin`

## 改动

仅 `AGENTS.md`（+2 行）：在指向 `.agents/skills/COVERAGE.md` 的段落后新增一句：

> 读 delivery-loop、backend-structure、packaging、database 这几份同步 skill 时，以 `.agents/skills/LEGACY-ABSORBED.md` 对应小节为必守补充条款。

已核对 LEGACY-ABSORBED.md 各节原落点覆盖 delivery-loop、backend-structure、packaging（×2）、database，与句中所列一致。

## 校验

| 命令 | 退出码 | 关键输出 |
| --- | --- | --- |
| `bash tools/skill-sync.sh` | 0 | 4 个 skill 全部 `OK 与上游一致`（backend / frontend / plugin / ai-loop） |
| `git diff -U0 AGENTS.md \| python3 ~/agent-work/grokbot/devin-queue/naming_check.py notes --diff -` | 0 | 无输出（FAIL=0，WARN=0） |
| `bash tools/pre-merge.sh --fast` | 0 | 7/7 全过：契约一致、危险函数白名单 PASS、dev-only 引用 PASS（1424 文件）、TypePHP diff FAIL=0 WARN=0、PHPUnit 408 tests / 2613 assertions OK、PHPStan No errors |

## 说明

- 只改 AGENTS.md，未动 `~/.shared-skills`、未动其他文件、未合并、未强推、未 `--no-verify`、未 `git add -A`。
- 提交：`1008135 docs(AGENTS): 指向 LEGACY-ABSORBED.md 补充条款`
