改后可合并（审计时 head：bc8166318c5813e11ec79b7f9c1df9411e12403a）——必须修改：① X5 安全日志下限「183 天」改为与总表第 16 条一致的「6 个月（按自然月计）」；② E3「1.1 做：英文语言包」改为「待老板定」（总表待确认项 4）

# PR #109 合并前独立审计（supdger/sandadmin，docs/1x-design-standard → main）

- 审计时状态：OPEN，非草稿，mergeable = MERGEABLE，mergeStateStatus = CLEAN；head `bc81663`，baseRefOid `a18fef9`，main 当前 `02cdbe8`
- gh pr checks 109：6/6 pass（composer audit ×2、frontend prod audit ×2、release self-check ×2），exit 0
- 证据：`evidence/`（pr.json、checks.txt、diff.patch、files.txt、gate-files.txt、prior-audits.txt、boss-decisions.md、verify.txt）

## 合并结果：未合并

结论不是「可合并」，按任务规则不执行 `gh pr merge`。两处改完后需重新审计（只看改动的两行 + 新 head 的检查）再合。

## 1. 范围与描述一致：基本一致

- 改了 3 个文件，+250 / −1，4 个提交：
  - `.agents/skills/sandadmin1x-design-standard/SKILL.md`：新增 247 行
  - `AGENTS.md`：新增 2 行（1 条规则 + 空行）
  - `server/config/connection-budget.generated.json`：改 1 行（源哈希）
- 前两项和 PR 描述一致。第三项（提交 `bc81663`）是解冲突（9e）时顺带修的 main 遗留问题，PR 描述里没写。不影响合并，建议在描述里补一句。
- 核对了这个哈希修复：`contract/openapi.yaml` 在 main 和 PR head 上字节完全相同；main 上另外两个生成文件（`plugin-routes.generated.json`、`route.generated.php`）已经是 `2833307d…`，只有 connection-budget 还停在旧的 `a8cf8b7c…`。PR 只是把它对齐，说法属实，只改了产物，没有动生成器和门禁。
- SKILL.md 在上一轮审计的 head `469caddb` 和当前 head 上 blob 完全相同（`37508f2…`），说明那之后内容没改过。

## 2. 安全与正确性：没有安全问题

- 全部是 Markdown 文档加 1 行生成产物哈希。没有密钥或令牌值，没有可执行脚本，也没有要求绕过门禁、加豁免、用云端或 Cursor 的内容（与 6c 结论一致，本轮复核了 diff）。
- 正确性问题见第 4 节两处（与总表冲突）。

## 3. 门禁文件只减不增：符合

- `.github/`、`tools/`、各种 allowlist / 豁免 / 白名单、phpstan、hook 都没有改动，没有新增豁免条目。
- AGENTS.md 只新增一条收紧要求（设计必须逐项回答清单，缺项打回）。
- connection-budget.generated.json 属于契约门禁检查的产物，不是门禁规则。这次改动让它通过 `contract-build --check`，没有放宽任何东西。

## 4. 与《老板已定事项总表》：两处冲突（必须改）

这个 skill 是硬门槛（「缺项的审计一律打回」）。里面写的数字会被所有 1.x 设计当成验收标准，所以和老板定论不一致的地方必须改掉，不能当建议放着。

1. **X5 和总表第 16 条冲突。** 总表原文：「登录、操作等安全日志下限 6 个月（按网络安全法）；普通日志可配到 1 个月。」PR 写的是「安全日志下限 183 天不可调低」，验收指标是「界面无法把安全日志留存调到 183 天以下」。6 个自然月可能有 184 天（比如 7 月 1 日到次年 1 月 1 日），按 183 天删日志会比法定下限少 1 天。这一条是把老板定的、也是法定的下限换成了一个可能更低的数字。
   - 必须改为：安全日志下限「6 个月（按自然月计）」（或「≥184 天」），验收指标同步改。
   - 建议：普通日志「默认 183 天可下调到 30 天」顺手改成「可配到 1 个月」，跟总表措辞一致。
2. **E3 和总表待确认项 4 冲突。** 总表写明国际化「待老板定（1.0 只中文）……不能当成已定」。PR 的 E3 在「1.1 做或不做」一栏写的是「做：英文语言包」，把一个没定的事写成了计划。1.0 只做 i18n 接口加中文包，界面仍然只有中文，这部分不冲突。
   - 必须改为：E3 的 1.1 一栏写「待老板定（总表待确认项 4）」。

其余条目（第 2、3、8、10、11、13、17、53、56 条）沿用 6c 的逐条核对，本轮抽查没有发现新的冲突。

## 5. 上一轮审计必改点核对

- 本 PR 的上一轮审计是 `results/6c-audit-pr109-1119`：结论「可合并」，没有必改点，只有 2 条不阻塞的建议，正好就是上面这两处（183 天、E3 国际化）。当前 head 上 SKILL.md 和那时一样，两条建议都还没落实。本轮把这两条升为必改，理由见第 4 节：它们是会被强制执行的门槛条款，和老板定论或待定事项直接冲突。
- `results/8a`（PR #105）和 `results/8c`（PR #107）审的是别的 PR，这次没有需要核对的必改点。
- `results/9e`（解冲突）：merge 提交 `8306382` 加哈希修复 `bc81663`，已经核实（见第 1 节）。

## 合并条件复核

| 条件 | 状态 |
| --- | --- |
| 结论为「可合并」 | 否（改后可合并） |
| 当前 head 与审计 head 一致 | 是（bc81663） |
| gh pr checks 109 全绿 | 是（6/6） |
| 无冲突 | 是（MERGEABLE / CLEAN） |

第一条不满足，所以没有合并，main 没有新 SHA。
