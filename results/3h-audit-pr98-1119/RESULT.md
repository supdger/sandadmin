结论：改后可合并（PR #98 需先改 COVERAGE.md 的 4 处，改完复核即可合并；范围、CI、~/.shared-skills 三项均通过）

# PR #98 合并前独立审计（只读）

- 仓库：supdger/sandadmin，本地 `~/agent-work/assistant/1x-skills-1119/sandadmin`
- PR：`docs/1x-skills` → `main`，head `1008135e84ebc1ef6d2d0c5521bb65abea96651d`，OPEN / MERGEABLE / CLEAN，reviewDecision 为空
- 审计日期：2026-11-19

## 0. 前置条件 — 通过
- `~/agent-work/assistant/3g-pr98-agents-pointer-1119/status.log` 中有：`[Thu Nov 19 10:08:07 PST 2026] done 3g-pr98-agents-pointer-1119`
- PR head 的 `AGENTS.md:74` 已含指向句：「读 delivery-loop、backend-structure、packaging、database 这几份同步 skill 时，以 `.agents/skills/LEGACY-ABSORBED.md` 对应小节为必守补充条款。」被引用文件在 head 中存在（blob `354b357`）。

## 1. 改动范围 — 通过
`gh pr diff 98 --name-only` 与 `git diff --stat origin/main...origin/docs/1x-skills` 结果一致，只有 3 个文件：
- `.agents/skills/COVERAGE.md`（新增，674 行）
- `.agents/skills/LEGACY-ABSORBED.md`（新增，49 行）
- `AGENTS.md`（+6/−1：补登 `web/`（React 19 + Semi）目录、标注 `sandadmin-artd/` 退役，加 COVERAGE / LEGACY-ABSORBED 两个指向句）

没有超出范围的文件：server/src、contract、门禁脚本、`.github/`、CI 配置、composer/package 都没动。本 PR 不新增 skill 目录，4 个 `sandadmin1x-*` 目录在 main 上已经存在。

## 2. 与老板已定事项总表（A）、顶层架构设计-v4（B）的一致性 — 不通过（必须改）
AGENTS.md 的两处改动与 B（v4 :33⑤、:110-111、:198-199、:507）一致，没有冲突。问题都在 COVERAGE.md：

### 必须修改
1. **R-1 被写成已定（自行拍板了未拍板事项）**
   - `COVERAGE.md:26` 把「R-1 三动作须绑 TOTP」放进状态为「已定-按 v4 K-2①（B4/R-1 落实）」的那一行。
   - v4 :383 写的是「独立审计 R-1【待老板确认】」，v4 :33⑬ 也把 R-1 列为【待老板确认 R-x】。`拍板处理.md` 里没有 R-1 条目，所以没有已定依据。
   - 要改成：把 R-1 从已定行拆出来，单列一行，状态写「未拍板-待老板确认」。相关 skill 落点（`B/SA` §四、`F/PU` §四）不能写成「按定稿写」。
2. **R-1 的动作数写错**：COVERAGE 写「三动作」，v4 :383 和审计原文（审计-遗漏.md:165）都是「两个动作/开关」（签发 MCP 写令牌、打开网页提交通道）。改为「两个」。
3. **PHP 版本下限与 v4 冲突**
   - `COVERAGE.md:30` 把「PHP≥8.4」列为草案中「被推翻口径」；`COVERAGE.md:442`（WDS-05）把「PHP≥8.2 见 AGENTS.md」当作现行口径。
   - v4 :1637（SCM-10）规定 PHP ≥8.4（1.x 内规划升 8.5）。
   - 要改成：从 :30 的「被推翻口径」里删掉 PHP≥8.4；:442 改为以 v4 的 PHP ≥8.4 为准。`AGENTS.md:19` 的「PHP ≥8.2」和 `AGENTS.md:57` 的「Node ≥20.19」（v4 是 Node ≥24 LTS）是旧写法，本 PR 没改这两行，至少要在 COVERAGE 的残留口径行登记为待改。
4. **漏登记 R-6**：v4 :33⑬ 标【待老板确认】的 R-6（开发期 MCP 最小版范围），COVERAGE 全文都没有。要在「冲突与未拍板事项」表里补一行，状态写「未拍板-待老板确认」。

### 知悉即可（不阻断合并）
- 国际化（A :89 未拍板）：`COVERAGE.md:27` 已如实标「未拍板存疑」。但上游同步的 `B/BS` §三、`F/CV` §一 写成了「1.0 只中文」的定论（不在本 PR diff 内）。建议在后续同步时给这两处加「待老板确认」注记。
- 其他未拍板项都只做了登记、没有拍板：MySQL 最低版本（:25，按 E-21，与 v4 收口一致）、审计模型 Fable vs Opus（:29）、工作流进框架还是插件（:28）、Semi 继承式扩展（:34）。
- A、B 里都找不到出处的新约定在 LEGACY-ABSORBED.md §1–§5：分支/commit 命名、树形资源校验、旧 Release 退役前置、验收记录字段、`status` 字段。这些属于总表 :76「老 skill 为底稿不能丢」的兜底范围，与 A、B 都不冲突。另有一点：§1 列的分支前缀 `feat|fix|chore` 不包括本 PR 用的 `docs/`，建议后续把 `docs/` 加进去。

## 3. ~/.shared-skills 未被改动 — 通过
- 基线：`~/agent-work/assistant/restore-1119/diff-after.txt`。按同样方法（`git show 444bcd6:.agents/skills/<f>` 对 `~/.shared-skills/<f>`）重跑，4 个文件全部 identical。
- `find ~/.shared-skills -type f -newer diff-after.txt` 结果为 0/292。
- 限制：其余 288 个文件没有逐字节基线，只靠 mtime 佐证。`~/.shared-skills` 不是 git 仓库。

## 4. CI / PR 检查 — 通过
`gh pr checks 98` 6/6 通过：composer audit ×2、frontend prod audit ×2、release self-check ×2（run 44584539011、44584550774）。

## 结论说明
改动范围、CI、shared-skills 三项通过。第 2 项违反了「未拍板事项只登记、没有自行拍板」：R-1 被写成已定，另有 PHP 版本冲突和漏登记的 R-6。这些都是 COVERAGE.md 里的文字改动，所以判为「改后可合并」：修改点 1–4 完成并复核后可以合并，视为批准。

## 本审计未做
- 只读审计：没有合并、推送、评论 PR、改仓库文件、建库，也没用云端。
- 上游 `sandadmin1x-*` skill 正文不在本 PR diff 内，只按 COVERAGE 的转述核对，没有逐字核对原文。
