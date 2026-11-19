# COVERAGE — 老版 skills → 1.x 开发规范对照表

本表回答一个问题：`~/.shared-skills/` 下的老版开发规范，每一条在 SandAdmin 1.x 里是被收编了、被取代了、废弃了，还是根本不适用。

- 生成日期：2026-11-19；对照基线：origin/main `977427a`（2026-11-18）。
- 效力顺序（冲突时从高到低）：老板已定事项总表 > v4 放行稿（顶层架构设计-v4、鉴权P0-v4）> 拍板处理.md > 继承表 > 老 skill 原文。拍板处理.md 中「待总监」的 23 项已由技术总监 2026-11-17 采纳建议定稿。
- 处置词表：
  - `已收编`：老规则在现行仓内文件有对应条款，「@」后给位置；括号注明改写口径。
  - `被取代`：老规则方向仍在但口径被新决策替代（注明替代物与来源）。
  - `废弃`：老规则被明确废弃（原因+来源）。
  - `不适用`：老规则绑老栈/老仓语境，与 1.x 无关（原因）。
  - `保留仓外`：规则仍有效，但属全局/通用技能范畴，不进 1.x 仓 skill（原因）。
  - `缺口`：规则应落文但现行仓内找不到落点（含继承表判继承/修改而落点未命中的条目）。
- 位置缩写（均指 `.agents/skills/` 下）：
  - `B/` = `sandadmin1x-backend/`：`SK`=SKILL.md、`BS`=references/backend-structure.md、`DB`=references/database.md、`SA`=references/security-auth.md、`TP`=references/typephp.md
  - `F/` = `sandadmin1x-frontend/`：`SK`=SKILL.md、`CV`=references/conventions.md、`PU`=references/permission-ui.md
  - `P/` = `sandadmin1x-plugin/`：`SK`=SKILL.md、`CT`=references/contract.md、`PK`=references/packaging.md
  - `L/` = `sandadmin1x-ai-loop/`：`SK`=SKILL.md、`DL`=references/delivery-loop.md、`MCP`=references/mcp.md、`PS`=references/project-selfdesc.md
  - `LA` = `LEGACY-ABSORBED.md`（本仓专属补遗，不参与 skill-sync 比对）

## 冲突与未拍板事项

| # | 主题 | 各方口径（出处） | 现行 skill 写法+位置 | 状态 |
| --- | --- | --- | --- | --- |
| 1 | MySQL 最低版本 | 老板原话「MySQL 8.0+」（总表 :13、待确认 :86）；v4 曾写「8.0.16+/8.4 LTS」；拍板处理 E-21 定「8.0+ 原话、8.4 LTS 只推荐、8.0.16 作废」；R-4「8.0 已于 2026-04 停官方支持、安装警告不拦」；R-8 三库矩阵 | `B/DB` 适用范围行与 §四末行按定稿写（8.0+ 下限、8.4 LTS 推荐、8.0.x 检出警告） | 已定-按拍板处理 E-21（总表曾列待确认，以拍板为准） |
| 2 | 双因素适用范围 | 总表三个历史口径（:23 已推翻 / :54 产品用户短信 / :24 框架 TOTP）→ v4 K-2① 两层定稿（平台后台=TOTP+恢复码、短信只找回；产品用户=短信走 SandIAM）+ 拍板 B4 1.0 实装 | `B/SA` §四 TOTP 行 + `F/PU` §四按定稿写 | 已定-按 v4 K-2①（B4 落实） |
| 3 | R-1 高危开关绑 TOTP | 审计原义为「签发能写数据的 MCP 令牌、打开网页提交通道」两个高危开关须绑 TOTP（v4 :383、audit-fable-1117 遗漏）；v4 :33⑬ 曾列【待老板确认】，同稿 :1068/:2176 记【老板 2026-11-17 已定 R-1，按建议】；v4 :1068 末注「开通生产 MCP 服务」是否并入待问，:2120/:2185 已按三动作并入 | `B/SA` §四 + `L/MCP` §二已按三动作写（含开通生产 MCP 服务） | 已定-11-17 定稿（见 #83；:33⑬ 待确认标为定稿前残留）；第三动作并入范围 v4 内口径不一致，登记备查 |
| 4 | 开发期 MCP 最小版范围（R-6） | v4 :33⑬ 曾列【待老板确认 R-x】；同稿 f1 第 24 行记「老板 11-17 定做」、U-3 节末记【老板 2026-11-17 已定 R-6，按建议】——开发构建加开发专用工具（查路由/契约、看最近错误与慢请求、跑指定测试、跑门禁脚本），不做代码生成、`SAND_DEV_BUILD` 编译期剔除 | `L/MCP` §二已按定稿写 | 已定-老板 2026-11-17（11-17 定稿，见 #83；:33⑬ 待确认标为定稿前残留） |
| 5 | 国际化 | 总表 :89 记「未见拍板」；v4 B-30/T-9 写「1.0 只中文+留口+插件 i18n 收集点+硬编码门禁」，来源为「助理 11-17 转达」 | `B/BS` §三（message 走语言包 key，T-9，1.0 只中文）+ `F/CV` §一（文案走语言包键，1.0 只出中文包） | 未拍板存疑-按 v4 执行，老板拍板记录缺位 |
| 6 | 工作流（sand-workflow）进框架还是插件 | 总表 :90「当前按插件在做，不要写成老板已定必须集成进框架」 | `B/BS` §二「内核不做」列工作流归插件；与「按插件做」现状一致 | 未拍板-待老板 |
| 7 | 独立架构审计模型 Fable vs Opus | 总表 :88/:60「工具 Fable→赛米改 Opus，见待确认 3」 | 无 skill 落点（对 skill 写法无直接影响） | 未拍板-登记即可 |
| 8 | 1x-AGENTS.md 草案 vs 现行 AGENTS | 草案（`grokbot/1x-gap-review/1x-AGENTS.md`）含被推翻口径：全表 BIGINT 雪花主键禁自增、JWT（lcobucci `Auth\TokenService`）、`plugin.manifest.php` 清单、厚 AGENTS 形态；草案 PHP≥8.4 与 v4 SCM-10 现行口径一致，不属被推翻 | 现行 `AGENTS.md` + `L/PS` §一 薄入口口径；编号两类口径见 `B/DB` §三 | 已定-草案不采纳（口径被拍板 A1、manifest.json C6、服务端会话等取代），登记说明 |
| 9 | AGENTS.md 写老现实 vs skill 写 1.x 目标 | 现行 AGENTS.md 目录清单只提 `sandadmin-artd/`（Vue3）未提 `web/`；`L/PS` §一 必写项要求「`web/` 是唯一前端、`sandadmin-artd/` 标退役」 | 本 PR 在目录清单补 `web/`（React 19 + Semi）并给 `sandadmin-artd/` 加退役括注；首句「Vue3 管理端前端工程」、命名节「Vue3/TS 前端」、skill 简介行「Vue3/TS」仍为老口径；另 `AGENTS.md` :19「PHP ≥8.2」、:57「Node ≥20.19」低于 v4 SCM-10（PHP ≥8.4、Node ≥24 LTS）同属旧口径，本 PR 按最小改范围不动，登记待改 | 已定-按 `L/PS` §一 必写项补齐事实描述；其余老口径残留登记待统一修订 |
| 10 | 插件路由前缀 `/api/admin/v1/plugin/<name>/` | 继承表 saiadmin6-frontend-03 注「随 C7 待总监定」；拍板处理 C7 判「已定（总监 11-17，采纳建议）」 | `P/CT` §5、`B/BS` §五、`F/CV` §4.2 均已写死该前缀 | 已定-按拍板处理 C7（继承表注为历史遗留措辞，落判已生效） |
| 11 | 继承表判「继承」但现行落点缺失/冲突 | 聚合仓拆仓与退役步骤（repo-sp-ipr-06/07/09、ipr-16~21）继承表判继承；现行 `P/PK` §9 明示「聚合仓迁移/退役流程属 0.x 历史材料，不进 1.x 契约」 | 本表按现行 skill 口径改判「不适用」，逐条见 A4 节 | 已定-以现行 skill（P/PK§9）为准，属继承表之后的口径演进 |
| 12 | Semi 官方手册的继承式扩展建议 | `~/.shared-skills/semi-ui-skills/BEST_PRACTICES.md` 推荐 class 组件继承 Semi 组件；1.x 组件规范为函数组件 + `PascalCase.tsx` | `F/CV` §二/§六（函数式组件、`<组件名>Props`） | 未拍板-手册留仓外使用，继承式扩展与 1.x 组件惯例兼容性未评审，登记备查 |

## A. 底稿 298 条对照

语料：`skill-1x-1117/work/底稿逐条清单.md`（298 条）；处置底本：`skill-fix-1117/继承表.md`（第二版，继承 169 / 修改 88 / 废弃 41 / 待定 0）。逐条沿用条目 ID；继承表判「继承/修改」的条目抽查过现行落点，对不上的如实标「缺口」。

### A1. repos 版 `sandadmin-plugins/SKILL.md`（54 条）

| 条目ID | 老出处 | 老规则要点 | 处置 | 新位置/来源 |
| --- | --- | --- | --- | --- |
| repo-sp-01 | SKILL.md:2 | frontmatter `name: sandadmin-plugins` | 已收编（skill 名换代） | `P/SK` frontmatter `name: sandadmin1x-plugin` |
| repo-sp-02 | SKILL.md:3 | description：PG、独立插件 Release、catalog、demo、生命周期验收 | 已收编（按双库/签名/manifest 改写） | `P/SK` frontmatter description |
| repo-sp-03 | SKILL.md:6-8 | 零业务插件框架可用；插件经独立仓+catalog+单独验收交付 | 已收编 | `P/SK` 引言 + `P/PK` §8 步 1（干净零插件宿主） |
| repo-sp-04 | SKILL.md:12 | 编辑前对每个变更能力及权威仓库做分类 | 已收编 | `P/PK` §7 步 1 冻结边界 + `P/CT` §2 权威仓库 |
| repo-sp-05 | SKILL.md:13 | 源码/Release/catalog/demo/安装验收是独立带证据阶段 | 已收编 | `P/PK` §7 交付流程 + §8 证据分级 |
| repo-sp-06 | SKILL.md:14 | 汇报区分 changed/checked/verified/committed/deployed | 已收编 | `L/DL` 证据分级节 + `P/SK` 汇报口径 |
| repo-sp-07 | SKILL.md:15 | 保留无关工作区改动；未授权不 commit/push/迁移/重载/删文件 | 已收编（改动范围条款；授权细目属全局授权规则不进 skill） | `L/DL` §0「改动范围只含本任务该动的文件」 |
| repo-sp-08 | SKILL.md:19 | 实质改动前读仓库治理文档；先看分支与工作区状态 | 已收编 | `L/SK`「接到任务先做的事」+ `L/DL` §0 |
| repo-sp-09 | SKILL.md:21 | 选名/前缀/路由/权限先查 sand-platform-conventions；6.x 细节用 saiadmin6 | 被取代（参照对象换 1.x 自有契约；saiadmin6 不再引用） | `P/CT` §5 命名与表前缀 + 仓库《命名与注释规范》 |
| repo-sp-10 | SKILL.md:27 | 归属：通用管理/安装入口/扩展契约/兼容矩阵 → SandAdmin | 已收编 | `P/CT` §8 生命周期归内核 + `P/PK` §1 catalog |
| repo-sp-11 | SKILL.md:28 | 归属：插件领域表/路由/菜单/权限/UI/生命周期 → 插件独立仓 | 已收编 | `P/CT` §2（「生命周期 SQL」更新为 migrations+manifest） |
| repo-sp-12 | SKILL.md:29 | 归属：catalog schema/可信仓库/包校验/安装入口 → SandAdmin | 已收编 | `P/PK` §4 catalog 条目 + §6 安装闸 |
| repo-sp-13 | SKILL.md:30 | 已安装 `server/plugin/*` 副本非权威源 | 已收编 | `P/CT` §2 + `B/BS` §一（server/plugin 是 Composer 产物目录） |
| repo-sp-14 | SKILL.md:31 | 归属：身份/授权/环境/访问审计 → SandIAM | 被取代（宿主身份/权限/审计归内核；SandIAM 只管产品侧） | `P/CT` §6 身份与权限 |
| repo-sp-15 | SKILL.md:32 | 归属：provider/模型路由/AI 任务/溯源 → SandAI | 已收编（作边界句：AI 能力属插件不在框架） | `B/BS` §二 内核不做清单 |
| repo-sp-16 | SKILL.md:34 | 拒绝 SandAdmin 对具名业务插件的运行时依赖 | 已收编 | `P/CT` §1 |
| repo-sp-17 | SKILL.md:40 | 建/接管仓库保留上游 license、notice、兼容标识 | 已收编（仓治理事实已落实） | `AGENTS.md` 许可证节 + 根 LICENSE/NOTICE |
| repo-sp-18 | SKILL.md:41 | ready 前先证明零业务插件 PG 基线可安装/迁移/登录/操作 | 已收编（PG→双库口径） | `P/PK` §8 步 1 + `P/CT` §3 `databases` |
| repo-sp-19 | SKILL.md:42 | 每插件独立仓；记录权威、Release 版本、同步方向 | 已收编 | `P/CT` §2 |
| repo-sp-20 | SKILL.md:43 | 维护兼容矩阵 | 已收编（并入 kernel_api_min/max） | `P/CT` §3 + `P/PK` §7 步 7 |
| repo-sp-21 | SKILL.md:45 | SandAdmin 仓不产出/不拥有插件业务代码 | 已收编 | `P/CT` §1 |
| repo-sp-22 | SKILL.md:49 | 插件独立仓名单；SandAdmin 拥有 catalog 与安装器 | 已收编 | `P/CT` §2 + `P/PK` §4 |
| repo-sp-23 | SKILL.md:51 | 拆仓/发 ZIP/改 catalog/退役聚合仓读 independent-plugin-repositories.md | 已收编（通用部分并入；聚合仓退役部分不适用） | `P/PK` §1/§7（§9 明示聚合仓流程属 0.x） |
| repo-sp-24 | SKILL.md:53 | catalog 条目字段；浏览器不提交任意 URL | 已收编（加 Ed25519 签名字段） | `P/PK` §4 |
| repo-sp-25 | SKILL.md:54 | Release ZIP 从已提交修订构建；根含元数据/生命周期 SQL/README/payload | 已收编（生命周期 SQL→migrations/；补 lockfile+SBOM） | `P/PK` §1/§2 |
| repo-sp-26 | SKILL.md:55 | 下载/校验/暂存/已装/升级/清理/卸载是不同状态 | 已收编 | `P/CT` §8 |
| repo-sp-27 | SKILL.md:56 | 有注册无目录 = installation-files-missing | 已收编 | `P/CT` §8 异常态 |
| repo-sp-28 | SKILL.md:57 | 聚合仓/demo 副本不是第二开发源 | 已收编 | `P/CT` §2（退役流程本身不适用，见 `P/PK` §9） |
| repo-sp-29 | SKILL.md:61 | 交付步骤 1 冻结边界 | 已收编 | `P/PK` §7 步 1 |
| repo-sp-30 | SKILL.md:62 | 交付步骤 2 源头实现 | 已收编（install/update/uninstall.sql→migrations/；补 lockfile+SBOM） | `P/PK` §7 步 2 + §2 |
| repo-sp-31 | SKILL.md:63 | 交付步骤 3 验证包（PG 方言检查） | 已收编（PG→双库方言检查+静态门禁） | `P/PK` §7 步 3 + `L/DL` §2 |
| repo-sp-32 | SKILL.md:64 | 交付步骤 4 提交发版回验 digest | 已收编（加签名验证） | `P/PK` §7 步 4 + §3 |
| repo-sp-33 | SKILL.md:65 | 交付步骤 5 更新 catalog；legacy fallback 纪律 | 已收编（fallback 条款废弃，见 `P/PK` §9 末行） | `P/PK` §7 步 5 + §4 |
| repo-sp-34 | SKILL.md:66 | 交付步骤 6 登录态 UI 验证；浏览器显示不算安装证据 | 已收编 | `P/PK` §8（界面观察≠安装证据） |
| repo-sp-35 | SKILL.md:67 | 交付步骤 7 备隔离 demo | 已收编 | `P/PK` §8 |
| repo-sp-36 | SKILL.md:68 | 交付步骤 8 隔离安装验收 | 已收编（单库→双库验收矩阵按 `databases` 声明） | `P/PK` §8 + `P/CT` §3 |
| repo-sp-37 | SKILL.md:69 | 交付步骤 9 单独提交 SandAdmin 证据 | 已收编 | `P/PK` §7 步 7 |
| repo-sp-38 | SKILL.md:73 | 发布/安装前用 SandAI 守门 | 废弃 | 老板规矩 1：插件侧守门不进框架 skill（继承表） |
| repo-sp-39 | SKILL.md:75 | SandIAM↔SandAI 端口契约 | 废弃 | 插件间契约不进框架 skill（继承表） |
| repo-sp-40 | SKILL.md:76 | 缺身份/无效凭证 fail closed，不得匿名兜底 | 已收编（语境换内核会话/权限码） | `B/SA` §一 + `B/BS` §八 fail-closed |
| repo-sp-41 | SKILL.md:77 | 不因冒烟过就装 SandIAM/迁移数据 | 废弃 | 插件侧安装顺序不进框架 skill（继承表） |
| repo-sp-42 | SKILL.md:78 | 只有实际安装演练出证据才算运行验收 | 已收编 | `P/PK` §8 证据分级 |
| repo-sp-43 | SKILL.md:82 | 结论六选一标签+附证据 | 已收编 | `L/DL` 证据分级 + `P/PK` §8 |
| repo-sp-44 | SKILL.md:84 | 过期副本/autoload 映射跑旧类 → 重同步、核版本、重载 | 已收编（生效模型条款承接：不做热重载，经重启/发布生效） | `P/CT` §8 生效模型 |
| repo-sp-45 | SKILL.md:85 | 同版本空 update.sql 不是升级证据 | 已收编（口径改：同版本空迁移不算升级） | `P/CT` §8 升级 + `P/PK` §8 步 4 |
| repo-sp-46 | SKILL.md:86 | Release 资产+SHA-256 只证包身份 | 已收编 | `P/PK` §8 证据分级 |
| repo-sp-47 | SKILL.md:87 | 构建/转换/包测试成功不是安装/运行声明 | 已收编 | `P/PK` §8 + `P/SK` 汇报口径 |
| repo-sp-48 | SKILL.md:88 | 跨插件契约交 UI 前先冻结 | 已收编 | `P/CT` §10 插件间契约冻结 + `P/PK` §7 步 1 |
| repo-sp-49 | SKILL.md:89 | 业务决策留业务应用；SandAI 可溯源可复核 | 废弃 | SandAI 治理原则属插件/业务侧（老板规矩 1，继承表） |
| repo-sp-50 | SKILL.md:93 | 禁把多权威源当同级一起改 | 已收编 | `P/CT` §2 |
| repo-sp-51 | SKILL.md:94 | 禁为走捷径在宿主建插件表/菜单/路由/权限 | 已收编 | `P/SK` 约定 3 + `P/CT` §11 条 1/11 |
| repo-sp-52 | SKILL.md:95 | 禁从宿主发插件 Release、浏览器拼包 URL | 已收编 | `P/PK` §4 |
| repo-sp-53 | SKILL.md:96 | 禁包冒烟当生命周期验收、验收当发布授权 | 已收编 | `P/PK` §8 证据分级 |
| repo-sp-54 | SKILL.md:97 | 禁编造凭证/会话/授权/验收结果/修订 | 已收编 | `L/DL` 证据分级 + `P/PK` §8 |

### A2. repos 版 `sandadmin-plugins/agents/openai.yaml`（2 条）

| 条目ID | 老出处 | 老规则要点 | 处置 | 新位置/来源 |
| --- | --- | --- | --- | --- |
| repo-sp-yaml-01 | openai.yaml:1-3 | interface 展示元数据 display_name/short_description | 不适用（openai.yaml 展示元数据形态未带入 1.x 仓 skill） | 等效物为 frontmatter description，见 `L/PS` §二 |
| repo-sp-yaml-02 | openai.yaml:4 | default_prompt 模板 | 不适用（同上） | `L/PS` §二 |

### A3. repos 版 `references/acceptance-evidence.md`（23 条）

| 条目ID | 老出处 | 老规则要点 | 处置 | 新位置/来源 |
| --- | --- | --- | --- | --- |
| repo-sp-ace-01 | acceptance-evidence.md:3 | 使用时机：权威源码已提交+demo 已备好 | 已收编 | `P/PK` §8 |
| repo-sp-ace-02 | acceptance-evidence.md:7 | 必录：仓/分支/commit/库版本/插件修订/包版本/准备方式/时间 | 已收编（最小集落 `P/PK` §8；必录字段清单落 `LA`，库版本按双库写「数据库版本」） | `LA` §4 |
| repo-sp-ace-03 | acceptance-evidence.md:8 | 必录：插件仓/tag/资产名/SHA-256/catalog 条目 | 已收编（补签名校验结果） | `P/PK` §4/§7 步 5 |
| repo-sp-ace-04 | acceptance-evidence.md:9 | 必录：干净零业务插件实例的安装/迁移/登录/基础管理 | 已收编 | `P/PK` §8 步 1 |
| repo-sp-ace-05 | acceptance-evidence.md:10 | 必录：精确命令、预期与实观、fixture、清理确认 | 已收编 | `P/PK` §8 |
| repo-sp-ace-06 | acceptance-evidence.md:14 | 步骤 1：catalog 把插件解析到独立仓 | 已收编 | `P/PK` §4 |
| repo-sp-ace-07 | acceptance-evidence.md:15 | 步骤 2：重新下载资产比对 SHA-256 | 已收编（比对项加验签） | `P/PK` §3/§7 步 4 |
| repo-sp-ace-08 | acceptance-evidence.md:16 | 步骤 3：ZIP 根含元数据/README/生命周期 SQL/payload | 已收编（生命周期 SQL→migrations/；补 lockfile+SBOM） | `P/PK` §2 |
| repo-sp-ace-09 | acceptance-evidence.md:17 | 步骤 4：登录态浏览器验证卡片/状态/版本/README 渲染 | 已收编 | `P/PK` §8 |
| repo-sp-ace-10 | acceptance-evidence.md:18 | 步骤 5：记录 UI 提供哪种动作；观察不得转生命周期声明 | 已收编 | `P/PK` §8 |
| repo-sp-ace-11 | acceptance-evidence.md:22 | 生命周期步骤 1：隔离 demo 安装 | 已收编 | `P/PK` §8 步 2 |
| repo-sp-ace-12 | acceptance-evidence.md:23 | 步骤 2：加载状态/菜单/权限/一条核心业务路径 | 已收编 | `P/PK` §8 步 2-3 |
| repo-sp-ace-13 | acceptance-evidence.md:24 | 步骤 3：真实旧版本升级到目标版本 | 已收编 | `P/PK` §8 步 4 |
| repo-sp-ace-14 | acceptance-evidence.md:25 | 步骤 4：卸载插件 | 已收编 | `P/PK` §8 步 5 |
| repo-sp-ace-15 | acceptance-evidence.md:26 | 步骤 5：插件表/配置/任务/文件/注册记录达到清理状态 | 已收编（卸载数据处理三选由内核执行） | `P/CT` §8 卸载 + `P/PK` §8 步 5 |
| repo-sp-ace-16 | acceptance-evidence.md:27 | 步骤 6：卸载后 SandAdmin 仍可用 | 已收编 | `P/PK` §8 步 6 |
| repo-sp-ace-17 | acceptance-evidence.md:31 | 声明边界：Static | 已收编 | `P/PK` §8 证据分级表 |
| repo-sp-ace-18 | acceptance-evidence.md:32 | 声明边界：Package | 已收编 | `P/PK` §8 证据分级表 |
| repo-sp-ace-19 | acceptance-evidence.md:33 | 声明边界：Catalog | 已收编 | `P/PK` §8 证据分级表 |
| repo-sp-ace-20 | acceptance-evidence.md:34 | 声明边界：Installation | 已收编 | `P/PK` §8 证据分级表 |
| repo-sp-ace-21 | acceptance-evidence.md:35 | 声明边界：Deployment | 已收编 | `P/PK` §8 证据分级表 |
| repo-sp-ace-22 | acceptance-evidence.md:36 | 声明边界：Production | 已收编 | `P/PK` §8 证据分级表 |
| repo-sp-ace-23 | acceptance-evidence.md:38 | 保留失败尝试与残留清理证据 | 已收编 | `P/PK` §8 末段 |

### A4. repos 版 `references/independent-plugin-repositories.md`（21 条）

| 条目ID | 老出处 | 老规则要点 | 处置 | 新位置/来源 |
| --- | --- | --- | --- | --- |
| repo-sp-ipr-01 | ipr:3 | 使用时机：拆仓/Release/catalog 变更/退役聚合仓 | 已收编（发包与 catalog 部分；退役部分不适用） | `P/PK` §1/§7 + §9 |
| repo-sp-ipr-02 | ipr:7 | 归属：supdger/sandadmin 拥有框架/catalog/下载规则/包校验/生命周期入口 | 已收编 | `P/PK` §1/§4 + `P/CT` §8 |
| repo-sp-ipr-03 | ipr:8 | 归属：每个 sand-* 仓拥有完整源码/元数据/生命周期 SQL/payload/文档/测试/tag/资产 | 已收编（生命周期 SQL→migrations/；补 lockfile+SBOM） | `P/CT` §2 + `P/PK` §2 |
| repo-sp-ipr-04 | ipr:9 | 归属：已安装目录与 demo 副本永远不是并行权威源 | 已收编 | `P/CT` §2 |
| repo-sp-ipr-05 | ipr:10 | 归属：全部插件有独立仓后，旧聚合仓只是迁移证据 | 已收编（独立仓=权威源的口径保留；退役流程不适用） | `P/CT` §2 + `P/PK` §9 |
| repo-sp-ipr-06 | ipr:14-16 | 拆分步骤 1：从干净已提交修订拆分保留历史 | 不适用（聚合仓拆仓属 0.x 历史材料） | `P/PK` §9 摘要表 |
| repo-sp-ipr-07 | ipr:17 | 拆分步骤 2：加迁移记录 | 不适用（同上） | `P/PK` §9 |
| repo-sp-ipr-08 | ipr:18 | 拆分步骤 3：确认独立仓含 README/info.ini/config.json/生命周期 SQL/payload | 废弃（老包结构清单） | 新包清单见 `P/PK` §2（manifest.json+migrations/） |
| repo-sp-ipr-09 | ipr:19 | 拆分步骤 4：仓库专属归属措辞，移除绝对路径 | 不适用（聚合仓步骤） | `P/PK` §9 |
| repo-sp-ipr-10 | ipr:20 | 步骤 5：已提交修订构建 ZIP、发布、回验 SHA-256 | 已收编（回验加签名） | `P/PK` §3/§7 步 4 |
| repo-sp-ipr-11 | ipr:21 | 步骤 6：资产存在后才更新 catalog | 已收编（加签名字段+kernel_api 区间） | `P/PK` §4/§7 步 5 |
| repo-sp-ipr-12 | ipr:22 | 步骤 7：契约测试+浏览器检查后才移除旧 Release | 已收编（退役前置检查落文于 `LA`） | `LA` §3 |
| repo-sp-ipr-13 | ipr:26 | catalog 可变但包由仓库/tag/资产/版本/digest 钉死；须验证实际加载修订 | 已收编 | `P/PK` §4 |
| repo-sp-ipr-14 | ipr:28 | 前端只选应用和版本；服务端取 catalog/校验/推导/下载/验 digest 交安装器 | 已收编（校验环节加验签） | `P/PK` §4 |
| repo-sp-ipr-15 | ipr:30 | 下载/读 README 不得执行 SQL、部署文件、建注册记录 | 已收编 | `P/PK` §4 |
| repo-sp-ipr-16 | ipr:34-36 | 退役步骤 1：确认 catalog 只引用独立仓 | 不适用（聚合仓退役属 0.x 历史材料） | `P/PK` §9 |
| repo-sp-ipr-17 | ipr:37 | 退役步骤 2：确认独立仓含历史与资产 | 不适用（同上） | `P/PK` §9 |
| repo-sp-ipr-18 | ipr:38 | 退役步骤 3：盘点未提交工作再删远端 | 不适用（同上；通用「不丢未提交工作」属全局授权纪律） | `P/PK` §9 |
| repo-sp-ipr-19 | ipr:39 | 退役步骤 4：替代资产回验后才删重复聚合 Release | 不适用（同上） | `P/PK` §9 |
| repo-sp-ipr-20 | ipr:40 | 退役步骤 5：优先归档，删除须明确授权 | 不适用（同上；删除授权属全局授权纪律） | `P/PK` §9 |
| repo-sp-ipr-21 | ipr:42 | 归档/删除远端不保留未提交本地工作 | 不适用（同上，属通用纪律） | `P/PK` §9 |

### A5. repos 版 `references/sandai-iam-gate.md`（13 条）

| 条目ID | 老出处 | 老规则要点 | 处置 | 新位置/来源 |
| --- | --- | --- | --- | --- |
| repo-sp-sig-01 | sig:3 | 使用时机：改 SandAI/SandIAM/身份适配器/安装顺序 | 废弃 | 插件侧工作不进框架 skill（老板规矩 1，继承表） |
| repo-sp-sig-02 | sig:7 | 归属：SandIAM 管身份/授权/审计 | 已收编（改边界句） | `P/CT` §6：宿主身份归内核、产品侧归 SandIAM |
| repo-sp-sig-03 | sig:8 | 归属：SandAI 管 provider/模型/检索/AI 任务/审计 | 已收编（作边界句） | `B/BS` §二 内核不做清单 |
| repo-sp-sig-04 | sig:9 | 归属：业务应用管自己的记录/可见性/状态流转/人工审批 | 已收编（通用边界原则） | `P/CT` §3/§11：插件数据归插件前缀，不碰内核表 |
| repo-sp-sig-05 | sig:13 | SandAI 不得跨库直读 sand_iam_* | 废弃 | 插件间数据边界属插件侧契约（继承表） |
| repo-sp-sig-06 | sig:14 | 身份上下文校验失败 fail closed | 已收编（语境换内核） | `B/SA` §一 + `B/BS` §八 |
| repo-sp-sig-07 | sig:15 | 密钥 write-only；复制包≠已运行安装 | 已收编（「复制≠安装」保留；write-only 属 SandIAM 细节废弃） | `P/PK` §8 证据分级 |
| repo-sp-sig-08 | sig:19 | 有序验收 1：确认权威 SandAI 包与修订 | 废弃 | 插件侧安装顺序（继承表） |
| repo-sp-sig-09 | sig:20 | 步骤 2：完成 SandAI 安装并证明包未改动 | 废弃 | 同上 |
| repo-sp-sig-10 | sig:21 | 步骤 3：之后才装/迁 SandIAM | 废弃 | 同上 |
| repo-sp-sig-11 | sig:22 | 步骤 4：真实 SandAI API 演练允许/拒绝/审计 | 废弃 | 同上（通用「真演练才算数」已另有条款） |
| repo-sp-sig-12 | sig:23 | 步骤 5：fixture/临时凭证/审计 fixture 清理确认 | 废弃 | 插件验收流程细节（继承表） |
| repo-sp-sig-13 | sig:25 | 不得从健康检查/包冒烟/仅安装验收推断最后一步 | 已收编 | `P/PK` §8 证据分级 |

### B1. `~/.shared-skills/saiadmin/SKILL.md`（16 条）

| 条目ID | 老出处 | 老规则要点 | 处置 | 新位置/来源 |
| --- | --- | --- | --- | --- |
| saiadmin-01 | SKILL.md:2 | frontmatter `name: saiadmin` | 不适用（老 skill 名绑 SaiAdmin 语境） | 1.x skill 命名见 `L/PS` §二 |
| saiadmin-02 | SKILL.md:3-5 | description：明确要求才套 kickoff/review-first | 不适用（触发语境属 pet_retail/UI 工作） | 各 SKILL.md frontmatter |
| saiadmin-03 | SKILL.md:12 | 用 kickoff 模板；已有 OpenSpec/文档记录时补缺口不另出计划 | 保留仓外（通用工作流约定，非 1.x 仓规范） | — |
| saiadmin-04 | SKILL.md:13 | 路由：纯 UI 跳后端标准；6.x 插件用 saiadmin6；webman-dev-standards 只管其范围 | 不适用（路由目标属老栈） | 1.x 分工见各 SKILL.md 分工表 |
| saiadmin-05 | SKILL.md:14 | 模板不中止已授权实现；只读/review-first 保持只读 | 保留仓外（通用授权边界） | — |
| saiadmin-06 | SKILL.md:18 | 未明确授权不改后端代码 | 保留仓外（通用授权边界） | — |
| saiadmin-07 | SKILL.md:19 | 「先审/先别写/给 diff」保持只读给 patch | 保留仓外（通用授权边界） | — |
| saiadmin-08 | SKILL.md:23 | 填模板优先复用仓库已有 kickoff 文档 | 保留仓外（通用方法） | — |
| saiadmin-09 | SKILL.md:25-56 | kickoff 模板全文 | 保留仓外（通用模板） | — |
| saiadmin-10 | SKILL.md:60-63 | Flutter/Dart 命令优先 FVM | 不适用（1.x 无 Flutter；React+Semi） | `F/SK` 技术栈节 |
| saiadmin-11 | SKILL.md:64 | FVM 不可用回退 flutter/dart | 不适用（同上） | — |
| saiadmin-12 | SKILL.md:68-70 | 最小验证-Flutter：analyze+页面冒烟 | 不适用（Flutter；页面冒烟思路已另写 React 版） | `F/SK` 验证要求节（人工真点冒烟） |
| saiadmin-13 | SKILL.md:71-72 | 最小验证-权限：有权限/无权限两视角 | 已收编 | `F/SK` 验证要求节末条 |
| saiadmin-14 | SKILL.md:73-74 | 最小验证-后端：列变更文件与理由 | 已收编（汇报口径） | `L/DL` 证据分级节 |
| saiadmin-15 | SKILL.md:75 | 后端验证：给最小验证路径 | 已收编 | `L/DL` §1 测试节 |
| saiadmin-16 | SKILL.md:76 | 涉一致性须声明事务边界与回滚策略 | 已收编 | `B/BS` §六 控制器要点 + `B/DB` §一 事务条 |

### B2. `~/.shared-skills/saiadmin6/SKILL.md`（15 条）

| 条目ID | 老出处 | 老规则要点 | 处置 | 新位置/来源 |
| --- | --- | --- | --- | --- |
| saiadmin6-01 | SKILL.md:2 | frontmatter `name: saiadmin6` | 被取代 | `P/SK` frontmatter `sandadmin1x-plugin` |
| saiadmin6-02 | SKILL.md:3-5 | description：6.x 插件建/改时触发 | 被取代 | `P/SK` description（1.x 插件全生命周期） |
| saiadmin6-03 | SKILL.md:10 | 引言：6.x 插件标准结构与流程 | 被取代 | `P/SK` 引言 + `P/CT` §2 |
| saiadmin6-04 | SKILL.md:14-16 | 业务拒绝统一 throw ApiException('中文',400) | 被取代（旧 ApiException 随 sand-core 脱钩；新口径=自带 render 契约异常 `Sandadmin\Infra\Contract\ApiException(message, code)`） | `B/BS` §四（拍板 C11） |
| saiadmin6-05 | SKILL.md:17 | 不另起无 render 的异常 | 被取代（原则由新契约承接） | `B/BS` §四（禁裸 RuntimeException） |
| saiadmin6-06 | SKILL.md:18 | 插件外应用自建同类异常 | 废弃 | 随老 ApiException 机制废弃（继承表） |
| saiadmin6-07 | SKILL.md:19 | config/exception.php 指向 saiadmin Handler | 废弃 | 依赖宿主 Handler，脱钩（继承表） |
| saiadmin6-08 | SKILL.md:21 | 异常细节见 backend.md | 废弃 | 所指章节随老栈废弃；新口径 `B/BS` §四 |
| saiadmin6-09 | SKILL.md:25-27 | 插件两部分：server/plugin/<name> + artd views | 被取代 | `P/CT` §2（独立仓 `server/`+`web/`）+ §9 构建期集成 |
| saiadmin6-10 | SKILL.md:31-35 | 插件表必须遵循标准结构 | 已收编（指向换成 1.x 契约） | `P/CT` §5 + `B/DB` §二 |
| saiadmin6-11 | SKILL.md:37 | Sand 插件以 sand-platform-conventions 为准（PG、sand_<domain>_*、SandIAM 归集） | 被取代（PG→双库；命名按 1.x 词表；SandIAM 只留边界句） | `P/CT` §5/§6 + `B/DB` |
| saiadmin6-12 | SKILL.md:41-47 | `php webman sai:plugin <name>` 自动建插件 | 废弃 | 老板规矩 8/拍板 A5：不做代码生成器 |
| saiadmin6-13 | SKILL.md:48-52 | 该命令自动产出目录/config/controller | 废弃 | 随生成器废弃 |
| saiadmin6-14 | SKILL.md:54-57 | 手动建 artd views/plugin 目录 | 废弃 | artd 退役；插件前端构建期集成 `P/CT` §9 |
| saiadmin6-15 | SKILL.md:61-65 | 开发标准入口：database/backend/frontend 三 references | 已收编（入口结构保留，指向换新） | `P/SK` 先分清表（contract.md/packaging.md） |

### B3. `~/.shared-skills/saiadmin6/references/backend.md`（32 条）

| 条目ID | 老出处 | 老规则要点 | 处置 | 新位置/来源 |
| --- | --- | --- | --- | --- |
| saiadmin6-backend-01 | backend.md:5 | 插件借用 saiadmin ApiException | 被取代 | `B/BS` §四（契约异常，C11） |
| saiadmin6-backend-02 | backend.md:9 | 业务拒绝 → ApiException('提示',400) | 被取代 | `B/BS` §四（`ApiException(message, code)`，错误码登记制） |
| saiadmin6-backend-03 | backend.md:10 | 插件外应用自建同类异常 | 废弃 | 随机制废弃（继承表） |
| saiadmin6-backend-04 | backend.md:11 | 中间件拒绝与 CheckLogin 一致抛 ApiException | 被取代（CheckLogin 脱钩） | `B/BS` §四（中间件业务拒绝抛契约异常） |
| saiadmin6-backend-05 | backend.md:13 | ApiException 自带 render，无 render 被吞 | 被取代（原因随老机制废弃；原则承接） | `B/BS` §四 |
| saiadmin6-backend-06 | backend.md:15-22 | config/exception.php 加固模板 | 废弃 | 依赖宿主 Handler（继承表） |
| saiadmin6-backend-07 | backend.md:24-32 | throw ApiException 用法示例 | 废弃 | — |
| saiadmin6-backend-08 | backend.md:34 | 业务拒绝显式传 400 | 废弃 | 绑 ApiException 细节；1.x 错误码登记制 `B/BS` §三 |
| saiadmin6-backend-09 | backend.md:36-43 | 目录 app/admin、app/api、app/model、app/validate | 被取代 | `P/CT` §2 插件包结构（server/+web/+migrations/） |
| saiadmin6-backend-10 | backend.md:48-50 | Model 继承 BaseModel 定义 table/pk | 被取代 | `P/CT` §7：不继承宿主基类，过渡期 think-orm 自立、长期 DBAL |
| saiadmin6-backend-11 | backend.md:53 | 复杂查询用 search<Field>Attr | 废弃 | searcher 绑 think-orm+BaseLogic 老栈 |
| saiadmin6-backend-12 | backend.md:54 | 简单 = 查询不加 searcher | 废弃 | BaseLogic 脱钩 |
| saiadmin6-backend-13 | backend.md:55 | JSON/复杂类型用 Casting/Accessor | 被取代 | `P/CT` §7 过渡/长期数据访问口径 |
| saiadmin6-backend-14 | backend.md:56 | 关联定义 belongsTo/hasMany | 被取代 | 同上 |
| saiadmin6-backend-15 | backend.md:58-90 | Model 完整样板 | 被取代 | 样板按 1.x 重写方向（`P/CT` §7） |
| saiadmin6-backend-16 | backend.md:93-95 | Validate 继承 BaseValidate | 被取代 | `B/BS` §六 带类型输入对象 + `P/CT` §7 |
| saiadmin6-backend-17 | backend.md:97-118 | Validate 样板（$rule/$message/$scene） | 被取代 | 场景化校验概念保留于 `B/BS` §六 输入对象 |
| saiadmin6-backend-18 | backend.md:124 | Pattern A 场景：扁平列表 CRUD | 已收编（场景分类通用） | `F/CV` §5.1 列表页骨架 |
| saiadmin6-backend-19 | backend.md:127 | index 调 logic->search()+getList() | 被取代 | `B/DB` §一 仓储层 + `F/CV` §5.1（列表=条件+分页形态保留） |
| saiadmin6-backend-20 | backend.md:128 | `#[Permission('名称','码')]` 两参数注解 | 已收编（改单参数 `#[Permission('<域>:<资源>:<动作>')]`，显示名归菜单扩展点） | `B/BS` §五/§六（拍板 C8 已定） |
| saiadmin6-backend-21 | backend.md:129 | `use plugin\saiadmin\service\Permission` | 废弃 | sand-core 脱钩（继承表） |
| saiadmin6-backend-22 | backend.md:131-191 | Pattern A Controller 五动作样板 | 被取代 | `B/BS` §六 骨架（契约信封+新权限声明） |
| saiadmin6-backend-23 | backend.md:193-195 | Logic 继承 BaseLogic，标准 add/edit/destroy | 被取代 | 1.x 无统一 Logic 层；`B/BS` §六 模块服务（示例用 search/save） |
| saiadmin6-backend-24 | backend.md:197-213 | Logic 样板 + override 时自管事务 | 被取代（事务纪律保留） | `B/DB` §一 事务条 + `B/BS` §六 |
| saiadmin6-backend-25 | backend.md:219 | Pattern B 场景：层级数据 | 已收编 | `F/CV` §5.1/§5.2 树列表与父级选择 |
| saiadmin6-backend-26 | backend.md:222 | index 改调 logic->tree($where) | 被取代 | `F/CV` §5.2 `?tree=true` 接口约定 |
| saiadmin6-backend-27 | backend.md:223 | Pattern B 标准 5 个权限注解 | 已收编（同 backend-20，单参数） | `B/BS` §五（拍板 C8） |
| saiadmin6-backend-28 | backend.md:225-241 | Pattern B Controller 样板 | 被取代 | 样板重写（`B/BS` §六 + `F/CV` §5） |
| saiadmin6-backend-29 | backend.md:244 | tree 用 Helper::makeTree | 被取代 | `P/CT` §7（SDK/自实现；Helper 归属改） |
| saiadmin6-backend-30 | backend.md:245 | edit 校验禁止父级设为自身 | 已收编 | `F/CV` §5.2（禁选自身或后代，前端挡+后端验） |
| saiadmin6-backend-31 | backend.md:246 | destroy 校验禁止删有子级的项 | 已收编（服务端树校验条：禁删有子级+父级不选自身/后代，前端先挡后端仍验；落文于 `LA`） | `LA` §2 |
| saiadmin6-backend-32 | backend.md:248-299 | Pattern B Logic 样板（tree=true 投影 value/label） | 被取代 | `F/CV` §5.2 + OpenAPI 契约登记 |

### B4. `~/.shared-skills/saiadmin6/references/frontend.md`（16 条）

| 条目ID | 老出处 | 老规则要点 | 处置 | 新位置/来源 |
| --- | --- | --- | --- | --- |
| saiadmin6-frontend-01 | frontend.md:3-10 | 目录结构 api/、<module>/、index.vue、modules/ | 被取代 | `F/CV` §二 目录 + `P/CT` §9 `plugin-web/` 草案 |
| saiadmin6-frontend-02 | frontend.md:14-89 | API 定义样板：export 对象 + request 泛型 | 已收编（统一 API 层概念保留，TS/React 重写） | `F/CV` §4.2 |
| saiadmin6-frontend-03 | frontend.md:28-29 | 插件接口 `/app/<plugin_name>/admin/...` 前缀 | 已收编（前缀改 `/api/admin/v1/plugin/<name>/...`） | `F/CV` §4.2 + `P/CT` §5（拍板 C7 已定） |
| saiadmin6-frontend-04 | frontend.md:81-87 | Tree 模型 listTree：`index?tree=true` | 已收编（参数与响应形状按 OpenAPI 登记） | `F/CV` §5.2 |
| saiadmin6-frontend-05 | frontend.md:91-116 | sa-* 预置组件清单 21 个 | 废弃 | Vue/Element 栈；`F/SK` 技术栈禁第二 UI 库 |
| saiadmin6-frontend-06 | frontend.md:117 | sa-* 全局注册直接用 | 废弃 | 同上 |
| saiadmin6-frontend-07 | frontend.md:119-120 | table-search.vue 严格遵循 stub | 已收编（照样板写的要求保留，样板改 React+Semi） | `F/CV` §5.1 |
| saiadmin6-frontend-08 | frontend.md:122 | 搜索字段超 3 个启用展开/收起 | 已收编 | `F/CV` §5.1 |
| saiadmin6-frontend-09 | frontend.md:124-126 | :showExpand / v-show 配置 | 被取代 | `F/CV` §5.1（实现随 React 重写） |
| saiadmin6-frontend-10 | frontend.md:128-227 | table-search.vue 完整样板 | 被取代 | `F/CV` §5.1（契约思路保留） |
| saiadmin6-frontend-11 | frontend.md:233-234 | 普通列表 index.vue 遵循 stub | 被取代 | `F/CV` §5.1 |
| saiadmin6-frontend-12 | frontend.md:236-326 | 普通列表完整样板 | 被取代（骨架概念保留） | `F/CV` §5.1 |
| saiadmin6-frontend-13 | frontend.md:328-329 | edit-dialog.vue 遵循 stub | 被取代 | `F/CV` §5.2 |
| saiadmin6-frontend-14 | frontend.md:331-417 | edit-dialog 完整样板 | 被取代（「回填+校验+分保存更新+反馈」契约保留） | `F/CV` §5.2 |
| saiadmin6-frontend-15 | frontend.md:423-459 | 树列表增量：展开/收起+toggleExpand | 被取代 | `F/CV` §5.1/§5.2 |
| saiadmin6-frontend-16 | frontend.md:462-495 | 树 edit-dialog：el-tree-select 绑 parent_id 包 Root 节点 | 已收编（Semi TreeSelect；包根节点技巧保留） | `F/CV` §5.2 |

### B5. `~/.shared-skills/saiadmin6/references/database.md`（16 条）

| 条目ID | 老出处 | 老规则要点 | 处置 | 新位置/来源 |
| --- | --- | --- | --- | --- |
| saiadmin6-database-01 | database.md:3 | 覆盖声明：legacy 默认值，Sand 以平台约定为准 | 被取代 | `P/CT` §5 + `B/DB`（1.x 数据契约为准） |
| saiadmin6-database-02 | database.md:6-7 | 每表 `id` int(11) AUTO_INCREMENT | 被取代 | `B/DB` §三 两类编号（拍板 A1） |
| saiadmin6-database-03 | database.md:9-19 | 必备字段 status/created_by/updated_by/三件套 | 已收编（字段集合保留，类型按双库） | `B/DB` §二 |
| saiadmin6-database-04 | database.md:21-36 | 建表示例 sa_example_table | 被取代 | `B/DB` §七 双库示例 |
| saiadmin6-database-05 | database.md:38-47 | 字段语义说明 | 已收编 | `B/DB` §二 |
| saiadmin6-database-06 | database.md:51 | 命名 sand_<plugin>_* / sand_<plugin>:module:action / sand-<plugin> / Sand<Plugin> | 被取代（权限域段=词干全称含 sand_ 前缀） | `P/CT` §5（拍板 E-8） |
| saiadmin6-database-07 | database.md:52 | Sand 插件表绝不用 sa_* | 被取代（方向保留：插件前缀 + 不碰 sand_system_*；sa_* 表述过时） | `P/CT` §5/§11 条 1 + `B/DB` §二 |
| saiadmin6-database-08 | database.md:53 | 身份/授权/审计归 SandIAM | 被取代 | `P/CT` §6 边界句 |
| saiadmin6-database-09 | database.md:54 | 字段 snake_case；术语全链路一致 | 已收编 | `B/DB` §二 + 命名规范 |
| saiadmin6-database-10 | database.md:55 | 支持注释的库所有字段带 COMMENT | 已收编 | `B/DB` §二 |
| saiadmin6-database-11 | database.md:56 | 新 Sand 表用 PG 基线 | 被取代 | `B/DB` §四 双库基线 |
| saiadmin6-database-12 | database.md:60-63 | 图片字段 varchar(1000) | 被取代 | `B/DB` §二：附件/图片统一存文件记录 id（拍板 E-12 已定） |
| saiadmin6-database-13 | database.md:65-69 | 布尔 tinyint(1)，1=是/2=否 | 被取代 | `B/DB` §二：boolean 0/1 + CHECK 锁取值（拍板 E-2 已定） |
| saiadmin6-database-14 | database.md:71-79 | 菜单层级 type=1/2/3 | 废弃 | 菜单走内核扩展点声明，禁 SQL 插行 `P/CT` §4/§11 |
| saiadmin6-database-15 | database.md:81-101 | 菜单 INSERT 模板 | 废弃 | 同上 |
| saiadmin6-database-16 | database.md:103-117 | sa_system_menu 字段说明 | 废弃 | 老核心表不适用 1.x（菜单写程序与插件清单，`B/DB` §三注） |

### B6. `~/.shared-skills/php-webman/SKILL.md`（12 条）

| 条目ID | 老出处 | 老规则要点 | 处置 | 新位置/来源 |
| --- | --- | --- | --- | --- |
| webman-01 | SKILL.md:2 | frontmatter name | 保留仓外（全局 Webman 技能本体） | — |
| webman-02 | SKILL.md:3 | description | 保留仓外 | — |
| webman-03 | SKILL.md:8 | 先确认版本/ORM/契约/同类实现；沿用项目分层 | 已收编 | `B/BS` 开篇（确认仓库真实结构）+ `F/SK` 动手前必读 |
| webman-04 | SKILL.md:8 | 不机械加 Service/Repository/万能 Base | 已收编 | `B/BS` §一 分层规矩 |
| webman-05 | SKILL.md:10 | 类型明确、初始化进生命周期、状态不进共享可变属性 | 已收编 | `B/TP` §一/§二/§四 |
| webman-06 | SKILL.md:11 | 编译友好写法由 php-aot-coding 承载 | 已收编 | `B/TP`（TypePHP 硬规矩） |
| webman-07 | SKILL.md:12 | 技能路由（saiadmin6/webman-dev-standards/thirdparty） | 不适用（路由目标随老栈更新） | 1.x 分工见各 SKILL.md 分工表 |
| webman-08 | SKILL.md:13 | 生命周期/连接池/容量用 webman-production-baseline | 保留仓外（生产运行专项，见 B-4 节） | — |
| webman-09 | SKILL.md:17 | 示例按章节搜索勿整篇读 | 保留仓外（全局示例库使用纪律） | — |
| webman-10 | SKILL.md:17 | 示例不决定项目依赖/契约，不授权执行命令 | 保留仓外 | — |
| webman-11 | SKILL.md:19-25 | 任务→章节路由表 | 保留仓外（skill 内部机制） | — |
| webman-12 | SKILL.md:27 | 审查调用链/状态归属；源码检查不当运行/编译通过 | 已收编 | `L/DL` 证据分级 + `P/SK` 汇报口径 |

### B7. `~/.shared-skills/php-webman/README.md`（8 条）

| 条目ID | 老出处 | 老规则要点 | 处置 | 新位置/来源 |
| --- | --- | --- | --- | --- |
| webman-readme-01 | README.md:1-3 | 定位 | 保留仓外 | — |
| webman-readme-02 | README.md:7-15 | 安装方式一（全局 skills 目录） | 保留仓外 | — |
| webman-readme-03 | README.md:17-22 | 安装方式二（项目级 .claude/skills/） | 保留仓外 | 1.x 仓内等价形态 `.agents/skills/`，见 `L/PS` §二 |
| webman-readme-04 | README.md:26-37 | 用法 | 保留仓外 | — |
| webman-readme-05 | README.md:39-45 | 模板清单（所列文件实际不存在） | 保留仓外（全局 skill 维护项，拍板 D3） | — |
| webman-readme-06 | README.md:47-50 | 示例清单（同样不存在） | 保留仓外（同上） | — |
| webman-readme-07 | README.md:52-58 | 特性列表 | 保留仓外 | — |
| webman-readme-08 | README.md:60-63 | 链接 | 保留仓外 | — |

### B8. `~/.shared-skills/php-webman/references/framework-examples.md`（29 条）

| 条目ID | 老出处 | 老规则要点 | 处置 | 新位置/来源 |
| --- | --- | --- | --- | --- |
| webman-ex-01 | ex:3-5 | 定位 | 保留仓外 | — |
| webman-ex-02 | ex:9-30 | §1 项目结构样板 | 不适用（1.x 结构不同） | `B/BS` §一 |
| webman-ex-03 | ex:32-158 | §2 控制器样板 {code,msg,data} | 被取代 | `B/BS` §三 契约信封；五动作骨架保留 |
| webman-ex-04 | ex:163-248 | §3 Eloquent 模型样板（含 *_at） | 被取代 | `B/DB` §一（Eloquent/think-orm 退役方向）+ §二 *_time |
| webman-ex-05 | ex:251-295 | §4 token header 认证中间件 | 被取代 | `B/SA` §二 Cookie 服务端会话 + `B/BS` §五 AuthMiddleware |
| webman-ex-06 | ex:297-328 | §4 全量 CORS 中间件 | 被取代 | `B/BS` §五 Origin 精确相等、不任意带凭据 |
| webman-ex-07 | ex:330-368 | §5 路由样板 | 保留仓外（webman Route API 机制示例） | 1.x 手写 `config/route.php` 见 `B/BS` §五 |
| webman-ex-08 | ex:370-448 | §6 WebSocket 服务样板 | 保留仓外 | — |
| webman-ex-09 | ex:450-461 | §6 process.php 注册样板 | 保留仓外 | — |
| webman-ex-10 | ex:463-503 | §7 Timer::add 定时任务样板 | 被取代 | `P/CT` §4 定时任务扩展点（清单声明 handler，内核调度） |
| webman-ex-11 | ex:505-531 | §7 Redis 消息队列消费者 | 保留仓外（机制示例） | — |
| webman-ex-12 | ex:533-550 | §8 异步 HTTP 客户端 | 保留仓外 | — |
| webman-ex-13 | ex:552-565 | §8 异步 MySQL 查询 | 保留仓外 | — |
| webman-ex-14 | ex:567-599 | §9 support\Redis 操作 | 保留仓外 | — |
| webman-ex-15 | ex:601-612 | §10 容器服务注册 | 保留仓外 | — |
| webman-ex-16 | ex:614-631 | §10 容器使用 | 保留仓外 | — |
| webman-ex-17 | ex:633-662 | 常用命令清单 | 保留仓外 | — |
| webman-ex-18 | ex:664-669 | 容量/连接池/压测必用 webman-production-baseline | 保留仓外（见 B-4 节） | — |
| webman-ex-19 | ex:671-672 | 缓存按项目约定；一致性属基线审查 | 保留仓外 | — |
| webman-ex-20 | ex:676-696 | 服务层样板（与 webman-04 张力） | 保留仓外（全局 skill 维护项，拍板 D5） | `B/BS` §一 已有自洽口径 |
| webman-ex-21 | ex:700-704 | 推荐 tinywan/exception-handler | 废弃 | 1.x 用自有契约信封 handler（继承表） |
| webman-ex-22 | ex:705-731 | 自定义异常 Handler（json code/msg） | 被取代 | `B/BS` §三/§四 |
| webman-ex-23 | ex:733-742 | support\Log 日志样板 | 保留仓外 | — |
| webman-ex-24 | ex:746-753 | 参数绑定防 SQL 注入 | 已收编 | `B/DB` §一 |
| webman-ex-25 | ex:755-761 | XSS 输出转义 | 已收编 | `F/SK` XSS 条（dangerouslySetInnerHTML+消毒） |
| webman-ex-26 | ex:763-768 | CSRF 中间件 | 已收编 | `B/BS` §五 CSRF 条 |
| webman-ex-27 | ex:770-778 | 触发场景清单 | 保留仓外 | — |
| webman-ex-28 | ex:780-789 | 三个使用示例对话 | 保留仓外 | — |
| webman-ex-29 | ex:791-793 | 相关 skill thirdparty-api-integration | 保留仓外（见 B-6 节） | — |

### B9. `~/.shared-skills/php-aot-coding/SKILL.md`（8 条）

| 条目ID | 老出处 | 老规则要点 | 处置 | 新位置/来源 |
| --- | --- | --- | --- | --- |
| aot-01 | SKILL.md:2 | frontmatter name | 保留仓外（全局规范本体） | — |
| aot-02 | SKILL.md:3 | description | 保留仓外 | — |
| aot-03 | SKILL.md:8 | 本机所有 PHP 项目适用 | 保留仓外（全局触发面） | 1.x 等价物 `B/TP` |
| aot-04 | SKILL.md:10 | 显式方法/稳定类型/状态不进共享属性 | 已收编 | `B/TP` §一/§二/§四 |
| aot-05 | SKILL.md:11 | 禁变量变量/魔术静态调用/动态 include/eval/闭包重绑定/跨类型复用/switch 不结束 | 已收编 | `B/TP` §三 |
| aot-06 | SKILL.md:12 | 沿用框架机制，不重写不批量迁 | 已收编 | `B/TP` §五 末段（改到哪合规到哪） |
| aot-07 | SKILL.md:13 | 有编译目标按矩阵；无目标仅写法约定 | 已收编 | `B/TP` §五（能编译也能不编译）+ `L/DL` §3 |
| aot-08 | SKILL.md:15-17 | 新工具接入两步 | 保留仓外（全局分发机制） | — |

### B10. `~/.shared-skills/php-aot-coding/README.md`（5 条）

| 条目ID | 老出处 | 老规则要点 | 处置 | 新位置/来源 |
| --- | --- | --- | --- | --- |
| aot-readme-01 | README.md:1-3 | 定位 | 保留仓外 | — |
| aot-readme-02 | README.md:5 | 正文单源维护 | 保留仓外 | — |
| aot-readme-03 | README.md:6 | 镜像目录清单（绑本机分发路径） | 保留仓外（全局分发机制；1.x 同步用 `tools/skill-sync.sh`） | `L/PS` §二 + `tools/skill-sync.sh` |
| aot-readme-04 | README.md:7 | 入口指针已挂位置 | 保留仓外 | — |
| aot-readme-05 | README.md:8 | 新工具接入指引 | 保留仓外 | — |

### B11. `~/.shared-skills/php-aot-coding/references/aot.md`（9 条）

| 条目ID | 老出处 | 老规则要点 | 处置 | 新位置/来源 |
| --- | --- | --- | --- | --- |
| aot-ref-01 | aot.md:3 | 总原则：显式方法/稳定类型/不重写框架 | 已收编 | `B/TP` 全文件 |
| aot-ref-02 | aot.md:7 | TypePHP 目标先查锁定版本矩阵 | 保留仓外（编译目标工程细项属全局规范） | — |
| aot-ref-03 | aot.md:8 | 声明文件只放声明；初始化进引导 | 已收编 | `B/TP` §一 |
| aot-ref-04 | aot.md:9 | 禁动态调用类写法；框架动态行为走适配层 | 已收编 | `B/TP` §三 |
| aot-ref-05 | aot.md:10 | 变量不跨类型复用；回调/引用显式；switch 显式结束 | 已收编 | `B/TP` §二 |
| aot-ref-06 | aot.md:11 | 不靠 ignore 退回解释执行；不改 vendor | 已收编（禁 ignore 部分） | `B/TP` §三；`.typephp/build/` 产物细项属编译工程留仓外 |
| aot-ref-07 | aot.md:12 | 扩展/资源进构建打包清单 | 保留仓外（编译打包细项） | — |
| aot-ref-08 | aot.md:14 | 验证按影响面；编译/依赖/发版候选查全量 | 保留仓外（编译验收细项；普通影响面验证见 `L/DL`） | — |
| aot-ref-09 | aot.md:16 | TrueAsync 准备=隔离状态/IO，不是预采用 API | 保留仓外（TrueAsync 预备属全局规范；1.x 现状不开协程 `B/BS` §八） | — |

### B12. `~/.shared-skills/agents-md/SKILL.md`（16 条）

| 条目ID | 老出处 | 老规则要点 | 处置 | 新位置/来源 |
| --- | --- | --- | --- | --- |
| agentsmd-01 | SKILL.md:2 | frontmatter name | 保留仓外（全局 skill 本体） | — |
| agentsmd-02 | SKILL.md:3-6 | description | 保留仓外 | — |
| agentsmd-03 | SKILL.md:11 | AGENTS.md 只做路由 | 已收编 | `L/PS` §一 |
| agentsmd-04 | SKILL.md:15 | 保持短（≤60 行优先） | 已收编 | `L/PS` §一 |
| agentsmd-05 | SKILL.md:16 | 只写路由 | 已收编 | `L/PS` §一 |
| agentsmd-06 | SKILL.md:17 | 不粘完整规范/长 runbook | 已收编 | `L/PS` §一 |
| agentsmd-07 | SKILL.md:18 | 与 project-agent.mdc 对齐 | 不适用（本仓无 project-agent.mdc 约定面） | — |
| agentsmd-08 | SKILL.md:19 | 中文仓库优先中文节标题 | 已收编 | `L/PS` §一 |
| agentsmd-09 | SKILL.md:23 | 步骤 1：读 README/mdc/docs 索引 | 保留仓外（通用写作流程） | — |
| agentsmd-10 | SKILL.md:24 | 步骤 2：识别文档地图 | 保留仓外 | — |
| agentsmd-11 | SKILL.md:25 | 步骤 3：按模板写/更新 | 保留仓外 | — |
| agentsmd-12 | SKILL.md:26 | 步骤 4：可选在 mdc 加一行 | 不适用（同 agentsmd-07） | — |
| agentsmd-13 | SKILL.md:28-56 | AGENTS.md 模板 | 保留仓外（模板随全局 skill；1.x 必写项已落 `L/PS` §一） | — |
| agentsmd-14 | SKILL.md:60 | 反模式：写成第二份 playbook | 已收编 | `L/PS` §一 |
| agentsmd-15 | SKILL.md:61 | 反模式：罗列 skill 不写何时读 | 已收编 | `L/PS` §一 |
| agentsmd-16 | SKILL.md:62 | 反模式：写不存在的路径 | 已收编 | `L/PS` §一 |

### C. 共享版 vs 仓库版 `sandadmin-plugins/SKILL.md` 差异（3 条）

| 条目ID | 老出处 | 老规则要点 | 处置 | 新位置/来源 |
| --- | --- | --- | --- | --- |
| diff-01 | 仓库版:3 ↔ 共享版:3 | description 增「Composer 基础包发布+消费验证」场景 | 被取代 | 拍板 E-13 已定：不发公共 Composer 基础包；description 按 1.x 重写（`P/SK`） |
| diff-02 | 共享版:49 | 插件兼容范围以 `/Users/code/project/...` 本机绝对路径为权威 | 废弃 | 绝对路径不可继承；权威 = manifest `kernel_api_min/max`（`P/CT` §3） |
| diff-03 | 共享版:61-70 | 「Composer foundation package consumption」6 步 | 废弃 | 拍板 E-13 已定：插件 SDK 随宿主内核版本提供（`P/CT` §10；`P/PK` §9 末行） |

## B. 补盘 skills 逐条对照

对 `~/.shared-skills/` 下 298 条之外的 6 个相关 skill 逐条抽取处置（编号自排）。

### B-1. `webman-dev-standards/SKILL.md`（WDS-01~28）

本 skill 面向 sandpet 系 business-server/pet_retail（Webman+ThinkORM+SaiAdmin 插件体系），多数条目绑该仓语境。

| 条目ID | 老出处 | 老规则要点 | 处置 | 新位置/来源 |
| --- | --- | --- | --- | --- |
| WDS-01 | SKILL.md:1-6 | frontmatter：business-server/pet_retail 触发 | 不适用（sandpet 仓触发面） | — |
| WDS-02 | SKILL.md:11 | Webman+ThinkORM+SaiAdmin 栈禁当 Laravel | 不适用（sandpet 栈说明；1.x 栈见 `B/SK` description） | — |
| WDS-03 | SKILL.md:13 | 按动作选读章节；仓内契约优先 | 已收编 | `L/SK` 接到任务加载表 + `B/SK` 上手顺序 + `F/SK` 动手前必读 |
| WDS-04 | SKILL.md:13 | 外围规则（sandpet-*）存在才读 | 不适用（sandpet rules 引用面） | — |
| WDS-05 | SKILL.md:15-21 | PHP≥8.1/Webman2/MySQL8.0+/Asia/Shanghai/start.php | 被取代（PHP ≥8.4 以 v4 SCM-10 为准，1.x 生命周期内规划升 8.5；`AGENTS.md` :19 仍写 ≥8.2，属旧口径登记待改；MySQL 8.0+ 见 `B/DB` 适用范围；时区/start.php 属 sandpet 运行参数，1.x 时间统一 UTC `B/DB` §四） | `B/DB` + `AGENTS.md` |
| WDS-06 | SKILL.md:23-26 | 保护分支禁直推，功能分支+PR | 已收编 | `L/SK` 总纲 + `L/DL` §0 |
| WDS-07 | SKILL.md:26 | 分支命名 feat\|fix\|chore/<scope>（可 hotfix/） | 已收编（落文于 `LA`） | `LA` §1 |
| WDS-08 | SKILL.md:27 | Commit type feat/fix/docs/refactor/test/chore | 已收编（与分支命名同条收编于 `LA`，不进 `L/DL` §4 门禁硬条件） | `LA` §1 |
| WDS-09 | SKILL.md:28 | PR 前跑 security-baseline 脚本（仓有才跑） | 已收编（等价门禁） | `L/DL` §2 供应链条（audit+gitleaks） |
| WDS-10 | SKILL.md:32 | strict_types | 已收编 | `B/TP` §一 |
| WDS-11 | SKILL.md:33 | 参数返回值类型 | 已收编 | `B/TP` §二 |
| WDS-12 | SKILL.md:34 | StudlyCase/camelCase；Logic 不用 save | 不适用（Logic 命名属 sandpet 分层；1.x `B/BS` §六 示例服务方法用 save） | 通用大小写命名见命名规范 + `L/DL` §2 命名自查 |
| WDS-13 | SKILL.md:35-36 | 短数组/点拼接/PHPDoc | 保留仓外（PHP 风格细则；仓内口径 PSR-12 + `B/TP` + 命名规范注释节） | — |
| WDS-14 | SKILL.md:37 | 分层归 sandpet-backend | 不适用（sandpet 分层；1.x 分层 `B/BS` §一） | — |
| WDS-15 | SKILL.md:39-46 | sandpet_ 前缀+编号两类+_time 三件套 | 被取代（前缀换 `sand_<域>_*`；编号两类与 `_time` 口径同源 11-17） | `B/DB` §二/§三 + `P/CT` §5 |
| WDS-16 | SKILL.md:45 | _time 三件套禁 created_at | 已收编 | `B/DB` §二 |
| WDS-17 | SKILL.md:46 | 禁明文敏感；sa_* 插件表例外 | 已收编 | `B/SA` §五 密钥/脱敏 + `B/DB` §二（sa_* 历史表口径 AGENTS.md 本仓节） |
| WDS-18 | SKILL.md:48-51 | Neuron AI 条件条款 | 不适用（sandpet 可选组件；1.x AI 能力属 SandAI 插件 `B/BS` §二） | — |
| WDS-19 | SKILL.md:53-57 | 响应壳 {code:int,message,data} | 被取代 | `B/BS` §三 v1 契约信封（code 字符串、成功 "OK"） |
| WDS-20 | SKILL.md:57-59 | 插件 ApiException | 被取代 | `B/BS` §四 + `P/CT` §11 条 7（sand-core 脱钩） |
| WDS-21 | SKILL.md:60 | 端前缀+写接口幂等防重 | 已收编（前缀被取代；幂等/防重分见两处） | 前缀 `B/BS` §五；幂等键 `L/MCP` §二 + 提交防重 `F/CV` §5.2 |
| WDS-22 | SKILL.md:62-68 | JWT/bcrypt/禁裸 SQL/敏感配置/安全检查 | 混合处置：禁裸 SQL 已收编 `B/DB` §一；敏感配置 `B/SA` §五；安全扫描 `L/DL` §2；JWT/bcrypt 被取代 | `B/SA` §二（服务端会话+Argon2id） |
| WDS-23 | SKILL.md:70-75 | 测试纪律（关键链路优先、测库事务夹具） | 已收编 | `L/DL` §1（模块带测试、工厂夹具、双库矩阵） |
| WDS-24 | SKILL.md:77-82 | 部署清单+回滚思路 | 已收编 | `L/DL` §5 发版与回退 + `B/BS` §七 |
| WDS-25 | SKILL.md:84-89 | 生产运行时归 webman-production-baseline | 保留仓外（专项引用，见 B-4 节） | — |
| WDS-26 | SKILL.md:91-95 | 配置进 config/、密钥进 .env、禁硬编码 | 已收编 | `B/SA` §五（主密钥只进 env）+ `B/BS` §一（config 目录职责） |
| WDS-27 | SKILL.md:97-99 | 开发节奏 | 保留仓外（通用节奏描述；实质被 `L/DL` 覆盖） | — |
| WDS-28 | SKILL.md:101-111 | 相关入口路由表 | 不适用（指向 sandpet rules） | — |

### B-2. `typescript-vue-type-safety/SKILL.md`（TVTS-01~10）

Vue3+TS 语境的类型安全约束；其 TS 部分与 `F/CV` §三 高度同构。

| 条目ID | 老出处 | 老规则要点 | 处置 | 新位置/来源 |
| --- | --- | --- | --- | --- |
| TVTS-01 | SKILL.md:1-4 | frontmatter：Vue/TS 契约与组件 | 不适用（Vue 语境；1.x React） | — |
| TVTS-02 | SKILL.md:14-17 | 禁 as any/: any/Record<string,any>/@ts-ignore/eslint-disable | 已收编 | `F/CV` §三 |
| TVTS-03 | SKILL.md:20-22 | 外部数据 unknown+类型守卫/schema 校验 | 已收编 | `F/CV` §三 |
| TVTS-04 | SKILL.md:24-35 | 窄化示例 | 保留仓外（通用示例） | — |
| TVTS-05 | SKILL.md:37 | Vue defineProps 不用 as any | 被取代 | `F/CV` §三（React `<组件名>Props` 类型声明） |
| TVTS-06 | SKILL.md:38 | 第三方库缺类型补 d.ts/interface | 已收编 | `F/CV` §三 |
| TVTS-07 | SKILL.md:40-42 | strict+eslint 模板门禁 | 已收编（strict: true；模板路径不适用） | `F/CV` §三 + `F/SK` 技术栈 |
| TVTS-08 | SKILL.md:44-46 | 页面组合/局部草稿/通用组件稳定语义/插槽与事件 | 已收编（React 同义改写：children+onXxx） | `F/CV` §六 |
| TVTS-09 | SKILL.md:47-48 | props/emits/受控/初始化清理/父页刷新/现有 API 复用/竞态守卫 | 已收编 | `F/CV` §5.2/§六 |
| TVTS-10 | SKILL.md:49-51 | 三类失败区分/提交防重/权限显隐不代鉴权/不顺手重构 | 已收编 | `F/CV` §5.2/§六 + `F/SK` 权限显隐节 |

### B-3. `semi-ui-skills/`（SEMI-01~21）

Semi Design 官方组件使用手册（MCP 工具流程+最佳实践）。1.x 前端正是 React 19 + Semi，本手册作为**通用工具使用手册**继续有效，整体保留仓外；1.x 仓内规范只管「用 Semi 单栈、不引第二库」这一层口径（`F/SK` 技术栈节）。

| 条目ID | 老出处 | 老规则要点 | 处置 | 新位置/来源 |
| --- | --- | --- | --- | --- |
| SEMI-01 | SKILL.md:1-4 | frontmatter（name semi-design-guide） | 保留仓外（手册元数据） | — |
| SEMI-02 | SKILL.md:10-37 | 文件组成说明 | 保留仓外 | — |
| SEMI-03 | SKILL.md:18-24 | WORKFLOWS 内容概览 | 保留仓外 | — |
| SEMI-04 | SKILL.md:29-37 | BEST_PRACTICES 内容概览 | 保留仓外 | — |
| SEMI-05 | SKILL.md:41-45 | 快速导航表 | 保留仓外 | — |
| SEMI-06 | SKILL.md:47-53 | Semi 概述 | 保留仓外 | — |
| SEMI-07 | SKILL.md:55-67 | MCP 前置配置 | 保留仓外（本机 MCP 配置项） | — |
| SEMI-08 | WORKFLOWS.md:5-12 | 四个 MCP 工具功能表 | 保留仓外 | — |
| SEMI-09 | WORKFLOWS.md:16-77 | 基础查询四步流程 | 保留仓外 | — |
| SEMI-10 | WORKFLOWS.md:81-178 | 五个完整任务示例 | 保留仓外 | — |
| SEMI-11 | WORKFLOWS.md:182 | 生成后先构建/类型检查再预览、写可观察断言 | 保留仓外（通用验证纪律；1.x 口径 `F/SK` 验证要求） | — |
| SEMI-12 | WORKFLOWS.md:184-228 | Midscene 可选视觉语义验证 | 保留仓外（可选工具链，1.x 未采用） | — |
| SEMI-13 | WORKFLOWS.md:232-267 | 查询技巧（版本/fullCode/文件列表） | 保留仓外 | — |
| SEMI-14 | WORKFLOWS.md:271-290 | 错误排查流程 | 保留仓外 | — |
| SEMI-15 | BEST_PRACTICES.md:5-24 | 组件/图标按需 import | 保留仓外（插件前端依赖白名单见 `P/CT` §9/§11 条 10） | — |
| SEMI-16 | BEST_PRACTICES.md:25-42 | 主题定制查官方文档 | 保留仓外（1.x 主色已定 Semi 默认蓝 `F/SK` 技术栈） | — |
| SEMI-17 | BEST_PRACTICES.md:44-63 | React 19 兼容查询 | 保留仓外 | — |
| SEMI-18 | BEST_PRACTICES.md:64-160 | class 组件继承扩展 Semi 组件 | 保留仓外（与 1.x 函数组件惯例兼容性未评审，记冲突与未拍板第 12 项） | — |
| SEMI-19 | BEST_PRACTICES.md:161-183 | 注意事项：优先 props、只覆盖必要方法 | 保留仓外 | — |
| SEMI-20 | BEST_PRACTICES.md:186-196 | tailwind 文档查询 | 保留仓外（1.x Tailwind 已定 `F/CV` §一） | — |
| SEMI-21 | SKILL.md 全文 | 配合 Semi MCP 使用的定位 | 保留仓外（通用工具手册，与 1.x 栈一致可继续用） | — |

### B-4. `webman-production-baseline/`（WPB-01~21）

Webman/Workerman 生产运行基线（SLO/IO/事件循环/连接池/背压/容量/观测/联合验收）。属生产运行专项，整体保留仓外；与 1.x 重叠的少数口径（请求态隔离、锁内禁重活、计数落库）已收编。

| 条目ID | 老出处 | 老规则要点 | 处置 | 新位置/来源 |
| --- | --- | --- | --- | --- |
| WPB-01 | SKILL.md:1-4 | frontmatter | 保留仓外 | — |
| WPB-02 | SKILL.md:7-8 | 触发范围：生命周期/IO/池/流/容量；普通 CRUD 不触发 | 保留仓外（生产运行专项） | 常驻进程口径部分 `B/TP` §四 |
| WPB-03 | SKILL.md:10-19 | 章节路由表 | 保留仓外 | — |
| WPB-04 | SKILL.md:21 | 证据要求；不因局部改动启动无关全量测试 | 保留仓外 | — |
| WPB-05 | SKILL.md:23 | 验收模板使用 | 保留仓外 | — |
| WPB-06 | rules-v1:1-9 | 例外条款格式（scope/owner/expiry/risk/rollback/evidence） | 保留仓外 | — |
| WPB-07 | rules-v1:11-20 | 规则 1：先定 SLO 再优化 | 保留仓外 | — |
| WPB-08 | rules-v1:22-32 | 规则 2：IO 登记与阻塞隔离 | 保留仓外（锁/事务内重活禁令部分） | `B/DB` §一 事务条 |
| WPB-09 | rules-v1:34-43 | 规则 3：事件循环可用性 | 保留仓外 | — |
| WPB-10 | rules-v1:45-58 | 规则 4：连接池全局预算 | 保留仓外 | — |
| WPB-11 | rules-v1:60-69 | 规则 5：请求态隔离 | 已收编（口径已落；并发隔离验收属专项留仓外） | `B/TP` §四 + `B/BS` §一/§八 |
| WPB-12 | rules-v1:71-80 | 规则 6：流式背压 | 保留仓外 | MCP 流式复用 `StreamWriterInterface` 见 `L/MCP` §二 |
| WPB-13 | rules-v1:82-92 | 规则 7：CPU 重活/慢业务隔离 | 已收编（锁/事务内禁重活；大导入导出转后台任务） | `B/DB` §一 + `B/BS` §六 S-04 |
| WPB-14 | rules-v1:94-103 | 规则 8：先修数据库行为再谈缓存 | 保留仓外 | 游标分页/禁深 OFFSET 见 `B/DB` §四 S-02 |
| WPB-15 | rules-v1:105-113 | 规则 9：Worker 数由容量实验定 | 保留仓外 | — |
| WPB-16 | rules-v1:115-126 | 规则 10：常驻资源有界可归 | 保留仓外（计数/快照落库部分） | `B/TP` §四 |
| WPB-17 | rules-v1:128-137 | 规则 11：依赖级观测 | 保留仓外（日志脱敏/request_id 部分） | `B/SA` §五 + `B/BS` §三 |
| WPB-18 | rules-v1:139-150 | 规则 12：负载/长稳/故障/回退联合验收 | 保留仓外（回退演练属运维暂缓 P-8） | `L/DL` §5 |
| WPB-19 | rules-v1:152-161 | 部署卫生非核心项（opcache/autoload/reload） | 保留仓外 | — |
| WPB-20 | acceptance-template.md | 验收模板本体 | 保留仓外 | — |
| WPB-21 | agents/openai.yaml | 展示元数据 | 保留仓外 | — |

### B-5. `sand-platform-conventions/`（SPC-01~24）

面向 sand-* 业务插件生态的命名/归属/字段字典约定，与 `P/CT` §5 命名同源；其中含两处本机绝对路径不可继承。

| 条目ID | 老出处 | 老规则要点 | 处置 | 新位置/来源 |
| --- | --- | --- | --- | --- |
| SPC-01 | SKILL.md:1-4 | frontmatter：sand-* 插件命名/归属/契约 | 不适用（插件生态规范；1.x 插件契约 `P/CT`） | — |
| SPC-02 | SKILL.md:8-20 | 一词干命名映射（sand-*/Sand*/sand_*） | 已收编（一词干原则） | `P/CT` §5 |
| SPC-03 | SKILL.md:20 | 禁 sa_*、snake_case、术语一致、例外登记 | 已收编 | `P/CT` §5 + `B/DB` §二 + `naming-allow.txt` |
| SPC-04 | SKILL.md:22-30 | 域归属表（SandIAM/SandAI/SandWorkflow 各管什么） | 不适用（插件生态归属；框架侧只留边界句） | `P/CT` §6 + `B/BS` §二 |
| SPC-05 | SKILL.md:30 | 共享能力归拥有方，不复制表 | 不适用（插件间契约；「宿主数据经 SDK」见 `P/CT` §7） | — |
| SPC-06 | SKILL.md:32-38 | 应用到服务授权层级模型 | 不适用（SandIAM 服务授权模型属插件） | — |
| SPC-07 | SKILL.md:42 | 兼容范围权威链接 `/Users/code/project/...` | 废弃（本机绝对路径不可继承） | `P/CT` §3 kernel_api_min/max 为权威 |
| SPC-08 | SKILL.md:44 | server/app/* 运行时边界、app\ 命名空间 | 不适用（老宿主结构） | `B/BS` §一 1.x 目录职责 |
| SPC-09 | SKILL.md:45 | 插件本地 runtime 完整栈（server/plugin/sand-*） | 不适用（0.x 插件目录形态） | `P/CT` §2/§9 |
| SPC-10 | SKILL.md:46 | SandAI 单一产品单一安装结构 | 不适用（插件侧约定） | — |
| SPC-11 | SKILL.md:47 | 交付权威源 `/Users/code/project/sand_plugins/sand-ai/` | 废弃（本机绝对路径不可继承） | — |
| SPC-12 | SKILL.md:48 | 验证标准安装真实部署全部宣称文件 | 已收编（验收原则） | `P/PK` §8 |
| SPC-13 | SKILL.md:50-52 | 决策记录分类（rules/产品/架构/契约/任务板） | 保留仓外（通用文档分类方法） | — |
| SPC-14 | SKILL.md:54-56 | PG-only 基线 | 被取代 | `B/DB` 双库 |
| SPC-15 | SKILL.md:57 | bigint IDENTITY 主键、外键同型 | 被取代 | `B/DB` §三 两类编号（外键同型保留） |
| SPC-16 | SKILL.md:58 | _time 三件套 timestamp(0) | 已收编（三件套；时间精度细节按双库） | `B/DB` §二/§四 |
| SPC-17 | SKILL.md:59 | status 启停 1/2；不机械加 status | 已收编（启停口径在 `B/DB` §二；「不机械加」细节落 `LA`） | `LA` §5 |
| SPC-18 | SKILL.md:60 | 唯一约束/索引按身份与边界建 | 保留仓外（通用建模纪律；索引前缀 `B/DB` §二） | — |
| SPC-19 | SKILL.md:61 | 密钥 write-only（慢哈希/加密信封） | 保留仓外（插件侧 secret_hash/encrypted_config 字段约定；通用「密钥不进日志」已收编 `B/SA` §五） | — |
| SPC-20 | SKILL.md:63-81 | 字段字典（id/FK/code/name/status/state/边界列/actor/time/secret/policy） | 已收编（主体：id、FK、created_by、_time、status/state；org/application/environment 边界列不适用单租户；secret/policy 列名属插件字典未落文） | `B/DB` §二 + `P/CT` §5 |
| SPC-21 | SKILL.md:82 | *_grant 授权表、append-only 审计 | 保留仓外（插件建模约定；审计只增不删精神 `B/DB` §六） | — |
| SPC-22 | SKILL.md:84-86 | 契约前置登记（owner/stem/表/路由/权限/迁移） | 已收编 | `P/PK` §7 步 1 + `P/CT` §4 契约四要素 |
| SPC-23 | naming-registry.md | 插件域命名登记表 | 保留仓外（插件域登记；1.x 插件命名直接按 `P/CT` §5） | — |
| SPC-24 | agents/openai.yaml | 展示元数据 | 保留仓外 | — |

### B-6. `thirdparty-api-integration/SKILL.md`（TPAI-01~16）

通用 Webman 三方 API 集成模式（按能力分层+厂商 Client 隔离），语料场景是 pet_retail 用户级接口。

| 条目ID | 老出处 | 老规则要点 | 处置 | 新位置/来源 |
| --- | --- | --- | --- | --- |
| TPAI-01 | SKILL.md:1-7 | frontmatter：用户级三方集成 | 不适用（pet_retail 场景；1.x 宿主出网统一入口 `B/BS` §八） | — |
| TPAI-02 | SKILL.md:11-22 | 术语表（IdentityVerification 等，禁 real_name_*） | 不适用（pet_retail 业务命名） | — |
| TPAI-03 | SKILL.md:24-52 | 身份核验全栈命名迁移表+SQL 示例 | 不适用（pet_retail 迁移任务） | — |
| TPAI-04 | SKILL.md:54-68 | 分层按能力不按厂商；Third 仅 RPC 门面 | 保留仓外（通用集成模式，可给插件借鉴，非 1.x 仓规范） | — |
| TPAI-05 | SKILL.md:70-92 | 推荐目录 app/integration/... | 不适用（老栈目录形态） | — |
| TPAI-06 | SKILL.md:94 | 新能力先 Contract+Outcome+Client | 保留仓外 | — |
| TPAI-07 | SKILL.md:98-104 | 各层职责表 | 保留仓外 | — |
| TPAI-08 | SKILL.md:106-118 | Outcome 映射示例；系统错误不写成业务失败 | 保留仓外 | — |
| TPAI-09 | SKILL.md:120-139 | 配置 thirdparty.php、密钥仅 env | 保留仓外（密钥进 env 口径同 `B/SA` §五） | — |
| TPAI-10 | SKILL.md:141-145 | curl 约定（全局 curl/HttpClient） | 不适用（1.x 出网统一入口+强制证书校验 `B/BS` §八；禁 verify=false `L/DL` §3） | — |
| TPAI-11 | SKILL.md:147-155 | RPC 归属（retail→inv） | 不适用（sandpet 服务拓扑） | — |
| TPAI-12 | SKILL.md:157-166 | 新接入 checklist | 保留仓外 | — |
| TPAI-13 | SKILL.md:168-173 | 反模式 | 保留仓外 | — |
| TPAI-14 | SKILL.md:175-178 | 相关 skill 指针 | 保留仓外 | — |
| TPAI-15 | 全文 | 「Logic 禁直解厂商 JSON」触发条件 | 保留仓外（通用纪律） | — |
| TPAI-16 | 全文 | 按能力域统一命名（跨项目） | 保留仓外（通用命名方法；词表机制见命名规范附录 A） | — |

## C. 其余共用 skills 处置

`~/.shared-skills/` 共 73 项：四个 `sandadmin1x-*` 为 1.x skill 本体（上游唯一源，仓内副本经 `tools/skill-sync.sh` 同步）；A 语料 6 个 + B 语料 6 个已逐条对照；`install-skill.sh`、`README.md` 为分发机制文件。其余 57 项一行一技能：

| skill | 处置 | 理由 |
| --- | --- | --- |
| autopilot-delivery | 保留仓外 | 通用交付流程方法论，非 1.x 仓规范 |
| business-apps | 不适用 | Flutter 移动端仓（老板端+收银端）规则，与 1.x 栈无关 |
| business-server | 不适用 | sandpet 系 business-server 仓规则（think-orm/sandpet_ 前缀被 1.x 口径取代） |
| codex-autopilot-delivery | 保留仓外 | 通用交付流程（Codex 变体），非 1.x 仓规范 |
| dart-add-unit-test | 不适用 | Dart/Flutter 生态技能 |
| dart-build-cli-app | 不适用 | 同上 |
| dart-collect-coverage | 不适用 | 同上 |
| dart-fix-runtime-errors | 不适用 | 同上 |
| dart-generate-test-mocks | 不适用 | 同上 |
| dart-migrate-to-checks-package | 不适用 | 同上 |
| dart-resolve-package-conflicts | 不适用 | 同上 |
| dart-run-static-analysis | 不适用 | 同上 |
| dart-use-pattern-matching | 不适用 | 同上 |
| define-goal | 保留仓外 | 通用目标定义方法论 |
| execute-acceptance | 保留仓外 | 通用验收方法论 |
| flutter-add-integration-test | 不适用 | Flutter 移动端技能（1.x 无 Flutter） |
| flutter-add-widget-preview | 不适用 | 同上 |
| flutter-add-widget-test | 不适用 | 同上 |
| flutter-agent-playbook | 不适用 | 同上 |
| flutter-app-resources | 不适用 | 同上 |
| flutter-app-ui-spec | 不适用 | 同上 |
| flutter-apply-architecture-best-practices | 不适用 | 同上 |
| flutter-auth-onboarding | 不适用 | 同上 |
| flutter-build-responsive-layout | 不适用 | 同上 |
| flutter-data-layer | 不适用 | 同上 |
| flutter-device-verify | 不适用 | 同上 |
| flutter-fix-layout-issues | 不适用 | 同上 |
| flutter-implement-json-serialization | 不适用 | 同上 |
| flutter-network-client | 不适用 | 同上 |
| flutter-permissions-ui | 不适用 | 同上 |
| flutter-reusable-components | 不适用 | 同上 |
| flutter-runtime-debug | 不适用 | 同上 |
| flutter-setup-declarative-routing | 不适用 | 同上 |
| flutter-setup-localization | 不适用 | 同上 |
| flutter-use-http-package | 不适用 | 同上 |
| grill-me | 保留仓外 | 通用需求澄清方法论（一次一问） |
| install-skill.sh | 保留仓外 | shared-skills 分发机制文件 |
| miniprogram-ui-spec | 不适用 | 小程序 UI 语料 |
| openspec-apply-change | 保留仓外 | OpenSpec 流程方法论，非 1.x 仓规范 |
| openspec-archive-change | 保留仓外 | 同上 |
| openspec-explore | 保留仓外 | 同上 |
| openspec-propose | 保留仓外 | 同上 |
| openspec-sync-specs | 保留仓外 | 同上 |
| openspec-update-change | 保留仓外 | 同上 |
| optimize-check | 保留仓外 | 通用优化检查方法论 |
| pet-retail-auth | 不适用 | pet_retail 登录/JWT 语料（1.x 服务端会话取代 JWT 口径） |
| playwright | 保留仓外 | 通用 e2e 工具技能；1.x e2e 口径（每菜单页一条冒烟）见 `F/SK` 验证要求 |
| prepare-for-launch | 保留仓外 | 通用发版准备方法论 |
| product-manager | 保留仓外 | 通用角色方法论 |
| project-manager | 保留仓外 | 通用角色方法论 |
| readme-documentation | 保留仓外 | 通用文档技能 |
| README.md | 保留仓外 | shared-skills 目录说明（机制文件） |
| skill-quality-bar | 保留仓外 | 通用 skill 质量规范（skill 怎么写；1.x 落盘形态见 `L/PS` §二） |
| thinking-retro | 保留仓外 | 通用复盘方法论 |
| user-usability | 保留仓外 | 通用可用性方法论 |
| ux-acceptance | 保留仓外 | 通用 UX 验收方法论 |
| wiki-documentation | 保留仓外 | 通用 wiki 技能 |

## 缺口小结

原登记的 6 项缺口已于 2026-11-19 按技术总监要求（老板没提到的也不能丢）全部按老版原文补文收编，当前**无剩余缺口**。补文原拟落 4 个 skill 的 references 文件，因这些文件是上游 `~/.shared-skills/` 同步副本（skill-sync 全量比对、上游只读原文），同日改集中收编于 `LA`（本仓专属补遗文件）：

| 条目ID | 原缺口内容 | 实际落点 |
| --- | --- | --- |
| WDS-07 | 分支命名约定（`feat\|fix\|chore/<scope>`、可 `hotfix/`） | `LA` §1 |
| WDS-08 | Commit type 约定（feat/fix/docs/refactor/test/chore） | `LA` §1 |
| saiadmin6-backend-31 | destroy 校验禁止删有子级的项 | `LA` §2 |
| repo-sp-ace-02 | 安装验收必录字段清单（仓/分支/commit/库版本/插件修订/包版本/准备方式/时间） | `LA` §4 |
| repo-sp-ipr-12 | 移除被引用的旧 Release 前须先跑 catalog 契约测试+登录态检查 | `LA` §3 |
| SPC-17 | 有启停语义才加 status、不对不可变记录机械加 status | `LA` §5 |

另有两类说明性注记（非缺口，登记备查）：

- 聚合仓拆仓/退役步骤（repo-sp-ipr-06/07/09、ipr-16~21）继承表判「继承」，现行 `P/PK` §9 已明示聚合仓流程属 0.x 历史材料——按现行口径判「不适用」，见冲突与未拍板第 11 项。
- `openai.yaml` 展示元数据形态（repo-sp-yaml-01/02）未带入 1.x 仓 skill，等效物为 frontmatter（`L/PS` §二）。
