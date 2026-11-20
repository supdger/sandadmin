已合并——PR #109（supdger/sandadmin，docs/1x-design-standard → main）按 10f 审计「改后可合并」落实两处必改后合入，merge commit `e325e0ef0c32bfa9e3237acd6b383f588fb9951a`（main 新 SHA），分支未删。

## 修改点落实

审计 head `bc81663` 的 2 条必须修改，全部落在 `.agents/skills/sandadmin1x-design-standard/SKILL.md`（本仓自有 skill，不在 skill-sync 比对目录内）：

1. **X5 安全日志下限（第 95 行）**：
   - 「下限 183 天不可调低」→「下限 6 个月（按自然月计）不可调低」，与《老板已定事项总表》第 16 条（「登录、操作等安全日志下限 6 个月（按网络安全法）」）一致；
   - 验收指标「界面无法把安全日志留存调到 183 天以下」→「……调到 6 个月（按自然月计）以下」（同步改）。
   - 审计仅列为「建议」的普通日志措辞（183 天→「可配到 1 个月」）按任务「不做审计未要求的改动」未动。
2. **E3 国际化（第 122 行）**：「1.1 做或不做」栏「做：英文语言包」→「待老板定（总表待确认项 4）」。

除上述两格外未做任何内容改动。提交：`2e641bf` docs(design-standard): 按合并前审计修正 X5 留存下限与 E3 国际化表述。

## 途中插曲：main 前进导致的冲突（非审计项）

推送 `2e641bf` 后 PR 变 CONFLICTING：审计后 main 从 `b8cfcfa` 之前又合入 #105、#107、#111、#104，唯一冲突文件为生成产物 `server/config/connection-budget.generated.json`（仅「源哈希」一行）。按 bc81663 同款处理方式解决：`git merge origin/main` → `php tools/contract-build` 重新生成 → `--check` PASS → 合并提交 `c92246d`。合并后 PR 对 main 的净 diff 只剩 SKILL.md（+247，新增文件）与 AGENTS.md（+2），产物与 main 一致不再出现在 diff 中。

## 检查结果（合并后树上）

| 检查 | 结果 |
| --- | --- |
| `tools/skill-sync.sh` | OK 4/4（backend/frontend/plugin/ai-loop 与上游一致） |
| `naming_check.py notes --diff`（对 origin/main） | 干净，0 FAIL / 0 WARN |
| `tools/pre-merge.sh --fast` | 8/8 全过（模块测试目录、PHP 语法、契约一致性 PASS、危险函数白名单 PASS、dev-only 门禁 PASS、TypePHP diff FAIL=0、PHPUnit 737 tests / 6877 assertions OK、PHPStan No errors） |

插曲：首次 pre-merge 误报 `server/config/database.php:29-30` `$resolve()`——原因是本地 `main` 引用停在 `977427a`（落后于 origin/main `b8cfcfa`），门禁 diff 基线过期把 main 自带行算成分支新增。`git fetch origin main:main` 快进本地 main 后重跑全过；未改 `database.php` 或门禁脚本。

## 新 head 与合并

- 推送后 head：`c92246d012a2eaae779743e2e1964ea81a4e595c`（= `2e641bf` 修复提交 + `c92246d` 解冲突合并提交）
- 合并前状态：`mergeable=MERGEABLE`，`mergeStateStatus=CLEAN`，`gh pr checks` 6/6 pass（composer audit ×2、frontend prod audit ×2、release self-check ×2）
- 执行：`gh pr merge 109 --repo supdger/sandadmin --merge`，exit 0，未删分支（`docs/1x-design-standard` 仍存在）
- 结果：PR #109 state=MERGED，mergedAt 2026-11-20T01:32:23Z
- **main 新 SHA：`e325e0ef0c32bfa9e3237acd6b383f588fb9951a`**

## 未做事项

- 审计「建议」项（X5 普通日志措辞）未改，见上。
- 未在 PR 描述补 connection-budget 说明（审计原话为「建议在描述里补一句」，非必改）。
