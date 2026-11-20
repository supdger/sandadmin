# LEGACY-ABSORBED — 老版 skills 收编补遗（本仓专属，不参与 skill-sync 比对）

本文件收编 6 条老版 skill 规则补文（COVERAGE.md 原登记缺口，2026-11-19 收编）。

为什么单独成文：这 6 条规则按落点归属 4 个 1.x skill 的 references 文件，但那 4 个文件是上游 `~/.shared-skills/` 的同步副本——`tools/skill-sync.sh` 对 4 个 skill 目录做逐文件 sha256 全量比对，仓内独写任何一行都会 FAIL、且会被 `--pull` 冲掉；上游权威副本按共享约束保持只读原文，不写仓内独有内容。故补文集中存于本文件（`.agents/skills/` 根级，不在被比对的 4 个 skill 目录内）。

每节标注其所属同步文件的「原落点」：读同步文件对应小节时，以本文件各节为补充条款。COVERAGE.md 中这 6 条的新位置列指向本文件各节。

## §1 分支命名与 commit type（WDS-07、WDS-08）

- 原落点：`sandadmin1x-ai-loop/references/delivery-loop.md` §0 分支纪律条（COVERAGE 缩写 `L/DL` §0）
- 老出处：`webman-dev-standards/SKILL.md`:26-27

分支命名 `feat/<scope>-…`、`fix/<scope>-…`、`chore/<scope>-…`（可 `hotfix/`）；commit 前缀 type：`feat` / `fix` / `docs` / `refactor` / `test` / `chore`。

（与 §0 分支纪律同条生效；commit type 为约定建议，不进 `L/DL` §4 门禁硬条件。）

## §2 树形资源删除校验（saiadmin6-backend-31）

- 原落点：`sandadmin1x-backend/references/backend-structure.md` §六（`B/BS` §六）
- 老出处：`saiadmin6/references/backend.md`:246

树形资源服务端校验（老栈 Logic 口径继承）：编辑时父级不许选成自身或自身后代、删除时仍有子级的项禁止删——前端先挡一遍（见 frontend conventions §5.2），服务端必须仍校验。

## §3 旧 Release 退役前置（repo-sp-ipr-12）

- 原落点：`sandadmin1x-plugin/references/packaging.md` §4（`P/PK` §4）
- 老出处：`sandadmin-plugins/references/independent-plugin-repositories.md`:22

- 旧 Release 退役前置：移除仍被引用的旧 Release 前，先跑 catalog 契约测试与登录态浏览器检查，通过后才移除。

## §4 验收记录必录字段（repo-sp-ace-02）

- 原落点：`sandadmin1x-plugin/references/packaging.md` §8（`P/PK` §8）
- 老出处：`sandadmin-plugins/references/acceptance-evidence.md`:7

验收记录必录字段（缺项不算证据）：

- 宿主仓、分支、commit、数据库版本、插件源修订、包版本、准备方式、验收时间；
- 插件仓、Release tag、资产名、下载到的 SHA-256、对应 catalog 条目；
- 干净零插件宿主的安装/迁移/登录/基础管理结果；
- 实际执行的命令或请求、预期与实测结果、夹具标识、清理确认。

## §5 status 不机械加（SPC-17）

- 原落点：`sandadmin1x-backend/references/database.md` §二 状态行（`B/DB` §二）
- 老出处：`sand-platform-conventions/SKILL.md`:59

`status` 只在启停有业务语义时加，不对审计/事件等不可变记录机械加。
