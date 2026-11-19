# RESULT — 恢复 ~/.shared-skills 原文 + 6 段补文挪仓内专属文件（2026-11-19）

## 结论

**完成。** `~/.shared-skills` 4 个被改文件已恢复到上一轮写入前原文（diff 证据见本目录）；仓内 4 个同步副本同步还原，6 段补文挪入不参与 skill-sync 比对的 `LEGACY-ABSORBED.md`，skill-sync 与 `pre-merge.sh --fast` 全过，已提交并普通推送到 `docs/1x-skills`（未合并）。

- 仓库：`https://github.com/supdger/sandadmin`，分支 `docs/1x-skills`（PR #98，未合并）
- fetch 后确认头 `b7bc4c5`（与远端一致，无新提交）
- **最终 SHA：`685b77178e443cb13ab7368ceaf79f20c67cab31`**
- 变更：6 文件（4 还原 + LEGACY-ABSORBED.md 新增 + COVERAGE.md 更新），+65/-27，全部在 `.agents/skills/` 内

## 一、~/.shared-skills 恢复

「写入前」基线 = 仓内 `444bcd6` 的对应文件（上一轮 run.log 记录上游与仓内逐字节一致；实际 diff 也证实差异恰好=上一轮写入的 6 段补文）。

恢复的 4 个文件（`git show 444bcd6:… > ~/.shared-skills/…` 覆写，只恢复上一轮写入的部分）：

1. `~/.shared-skills/sandadmin1x-ai-loop/references/delivery-loop.md`（移除 WDS-07/08 补文）
2. `~/.shared-skills/sandadmin1x-backend/references/backend-structure.md`（移除 saiadmin6-backend-31 补文）
3. `~/.shared-skills/sandadmin1x-backend/references/database.md`（移除 SPC-17 补文）
4. `~/.shared-skills/sandadmin1x-plugin/references/packaging.md`（移除 repo-sp-ipr-12、repo-sp-ace-02 补文）

diff 证据（本任务目录）：

- `diff-before.txt`：恢复前 4 文件 vs `444bcd6`——差异恰为 6 段补文，无其他改动；
- `diff-after.txt`：恢复后 4 文件 vs `444bcd6`——全部 `(identical)`，与写入前逐字节一致。

其余 `~/.shared-skills` 内容未动（manifest 复核：frontend 本就一致，backend/plugin/ai-loop 恢复后与仓内副本一致）。

## 二、补文新位置

新文件：**`.agents/skills/LEGACY-ABSORBED.md`**（`.agents/skills/` 根级，与 COVERAGE.md 同级；不在 skill-sync 比对的 4 个 skill 目录内）。补文原文照抄、按原落点分 5 节：

| 条目ID | 原落点（同步文件） | LEGACY-ABSORBED 落点 |
| --- | --- | --- |
| WDS-07 / WDS-08 | `L/DL` §0 | `LA` §1 |
| saiadmin6-backend-31 | `B/BS` §六 | `LA` §2 |
| repo-sp-ipr-12 | `P/PK` §4 | `LA` §3 |
| repo-sp-ace-02 | `P/PK` §8 | `LA` §4 |
| SPC-17 | `B/DB` §二 | `LA` §5 |

COVERAGE.md 同步改：位置缩写表加 `LA`；6 行「新位置」列改指 `LA` §n（处置注记里落点措辞随之更新）；缺口小结「实际落点」列同样改指 `LA`，并加注补文集中收编原因。

## 三、「原文件留一行指向新位置」的实现口径（须复核）

skill-sync 对 4 个 skill 目录做**逐文件 sha256 全量比对**，同步副本里加任何一行指针都会让仓内≠上游再次 FAIL、且会被 `--pull` 冲掉；上游只读原文也不能加指针。因此同步文件内**无法**留指针行（留了就重回上轮问题）。指针改以两处实现：

- LEGACY-ABSORBED.md 每节标注「原落点」+「老出处」（反向指针）；
- COVERAGE.md 6 行新位置列即「一行指向新位置」（正向指针）。

若确要仓内同步文件带指针行，唯一可行路径是先授权改上游 `~/.shared-skills` 加同一行——本轮按禁令未做，在此登记。

## 四、校验

| 命令 | 退出码 | 关键输出 |
| --- | --- | --- |
| `bash tools/skill-sync.sh` | 0 | 4×「OK … 与上游一致」 |
| `python3 tools/naming_check.py notes .agents/skills/LEGACY-ABSORBED.md .agents/skills/COVERAGE.md` | 0 | 无 FAIL/WARN，仅 INFO |
| `bash tools/pre-merge.sh --fast` | 0 | 7/7：php -l ✓、contract-build --check ✓、危险函数白名单扫描 ✓、dev-only 引用门禁 1423 文件 ✓、typephp_check diff FAIL=0/WARN=0、PHPUnit 408 tests/2613 assertions OK、PHPStan No errors |

`pre-merge.sh --fast` 全程未要求数据库。

## 五、未做事项（按禁令）

未改 skill-sync 脚本/白名单、未再写 ~/.shared-skills（除本任务要求的恢复写入）、未改 server/src、contract/openapi.yaml、门禁文件和 CI；未建库/DDL/迁移/启数据库；未合并、未发布、未打 tag；未开下级会话；未用 Cursor/Claude/云端；未读未打印秘密；未强推/reset --hard/--no-verify/git add -A（6 个文件逐个 add）。

## 模型记录

- 主 Agent：SWE-2 / high（swe-2-high）
- 子 Agent：未使用（任务明确禁开下级会话，全程主代理执行）
