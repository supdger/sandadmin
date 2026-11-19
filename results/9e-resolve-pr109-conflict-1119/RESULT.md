# RESULT — 解决 PR #109 与 main 的冲突

仓库：`~/agent-work/assistant/3j-design-standard-1119/sandadmin`（github.com/supdger/sandadmin）
分支：`docs/1x-design-standard`（PR #109，原 head `469caddb`）

## 冲突文件

- `AGENTS.md` —— 唯一冲突文件。main 与本 PR 同时在「AI 开发必读 skill」四条列表后、
  「配套工具」段前追加了段落，产生内容冲突。

## 解决方式

- `git merge origin/main`（未 rebase、未强推），merge 提交 `8306382`。
- 冲突块保留双方内容：先放 main 合入的两段（`.agents/skills/COVERAGE.md` 登记说明、
  `LEGACY-ABSORBED.md` 必守补充条款），再补回本 PR 的段落
  （`sandadmin1x-design-standard` 清单要求，注明不在 skill-sync 比对四目录内）。
- `.agents/skills/` 下 skill-sync 比对的四个目录（backend/frontend/plugin/ai-loop）
  未做任何改动；`git diff origin/main HEAD -- <四目录>` 为空。

## 附带修复（门禁所需）

- `tools/pre-merge.sh --fast` 首次运行在「契约一致性」步失败：
  main 上 `server/config/connection-budget.generated.json` 自 `320de4b` 起滞留旧源哈希
  `a8cf8b7c…`，与 main 当前 `contract/openapi.yaml`（其余产物均已是新哈希 `2833307d…`）不一致。
  属 main 自身遗留，与本 PR 无关。
- 按产物头注释指示运行 `php tools/contract-build` 重新生成，仅源哈希一行变化；
  单独提交 `bc81663`（chore(contract)），未改 CI / 门禁脚本 / 白名单。

## 检查结果

| 检查 | 结果 |
| --- | --- |
| `tools/skill-sync.sh` | PASS：4 个同步 skill 与上游 `~/.shared-skills/` 全部一致 |
| `tools/naming_check.py notes --diff <(git diff origin/main...HEAD)` | PASS：exit 0，无 FAIL/WARN |
| `tools/pre-merge.sh --fast` | PASS：PHP 语法、契约一致性、危险函数、dev-only 引用、TypePHP diff 扫描、PHPUnit unit（583 tests / 3369 assertions）、PHPStan 0 errors 全过 |
| 备注 | PHPStan 在默认 `memory_limit=128M` 下 OOM 崩溃，经 `PHPRC` 临时 php.ini（memory_limit=2G）重跑通过，属本机环境限制，非代码问题 |

## SHA

- merge 提交：`8306382`
- 分支新 head（已普通 push 到 origin）：`bc8166318c5813e11ec79b7f9c1df9411e12403a`

## 结论

冲突已解决，三关全过，分支已推送。未合并 PR（按要求）。
