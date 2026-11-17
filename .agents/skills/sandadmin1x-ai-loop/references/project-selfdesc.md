# project-selfdesc：项目自带 AGENTS.md + skill 的约定

适用：新建/改写仓库根 `AGENTS.md`，或给项目新增/维护自带 skill。

出处：薄入口写法沿用全局 `agents-md` skill 约定；1.x AGENTS.md 必写项出自
顶层架构设计-v4「必改 6」（arch:363-373）；「项目自带 skill」出自 v4 U-2
（arch:1964-1970：skill 先写后开发、随 1.x 发布复制进仓库）。仓库内存放
位置 v4 留白，§二为定稿方案（已定，总监 11-17）。

---

## 一、AGENTS.md：薄入口，只路由

**写法硬规矩**（agents-md 约定）：

- 短，优先 ≤60 行。只写路由：先读什么、硬门槛、模块文档在哪。
- 不粘贴完整编码规范、接口表、长 runbook——链到对应文档/skill。
- 不写仓库里不存在的路径；路径失效视同文档 bug 处理。
- 中文仓库用中文节标题。

**sandadmin 1.x 的 AGENTS.md 必写项**（v4 必改 6，缺一项算不过）：

- [ ] 目录职责按真实结构写：`server/src` = 内核权威源码；`server/app` = 薄壳；
  `server/plugin/` = Composer 发布产物目录（非源码权威、在 .gitignore）；
  `server/database/migrations/` = 宿主迁移在仓。
- [ ] `web/` 是唯一前端；`sandadmin-artd/` 标退役。
- [ ] sand-core 标「旧兼容层（legacy），不接收新功能」；sand-package 标
  「下线中，能力收进内核 PackageOps」。
- [ ] 插件契约指路：插件开发看内核扩展点文档和 catalog.json 规范，不看
  sand-package 文档。
- [ ] 硬门槛：只在分支改、合并前门禁清单（指到本 skill 的 delivery-loop）、
  禁止事项速查。
- [ ] 必读 skill 清单：每条写「什么时候读」，不罗列不写的。

**维护约定**：

- [ ] 改目录结构、退役模块、改契约的 PR，同一 PR 内同步 AGENTS.md；结构变了
  AGENTS.md 没变 = PR 不完整，验收可打回。
- [ ] 建议仓内加一条路径检查（CI 或脚本）：AGENTS.md 里引用的相对路径全部
  真实存在（建议，属工程实现项）。

## 二、项目自带 skill（落盘形态已定，总监 11-17）

目标：AI 进项目就能按项目自己的规矩干活，不依赖外部记忆。

- [ ] **落盘位置（已定，总监 11-17）**：仓库内 `.agents/skills/<skill 名>/
  SKILL.md`（+ `references/`），随仓库分发；`.agents/` 是跨工具中立约定，
  在 AGENTS.md 里写清各工具的等价位置（如 `.claude/skills/` 做同名软链或
  同步副本）。
- [ ] **门禁工具同样进仓（M-10/对照 9）**：检查脚本进仓库 `tools/` 目录，
  CI 只用仓内版本、不用个人机器上的副本；上游 `~/.shared-skills` 是 skill
  唯一源，仓内 `.agents/skills/` 是副本，发版前做哈希比对、不一致即失败
  （R-3，防两边漂移）。
- [ ] **frontmatter 必填**：`name`（项目域前缀，如 `sandadmin1x-backend`）+
  `description`（一句话写清触发场景——干什么任务时读它）。
- [ ] **内容薄厚**：SKILL.md 薄入口（路由 + 硬规矩速查），细则、样板、检查表
  放 `references/` 按任务加载；不把整本规范塞进一个文件。
- [ ] **写法和代码同规矩**：简体中文、硬条款可检查（能判过/不过）、不写
  「建议尽量」式空话；引用仓内路径必须真实存在。
- [ ] **与全局 skill 的分工**：项目自带 skill 写项目事实（这个仓的分层、契约、
  门禁、特有命令）；语言级/通用写法（如 PHP 编译友好）由全局 skill 承载，
  项目 skill 只指过去、不复制。
- [ ] **维护**：skill 内容对应的行为改了（门禁加减、契约修订、目录迁移），
  同一 PR 更新 skill；skill 与代码不一致 = 文档 bug。

## 三、自描述体系的验收口径

- [ ] 新克隆的仓库，一个没见过项目的 AI 只靠 `AGENTS.md` + 自带 skill 能
  回答：代码权威在哪、改东西走什么分支流程、合并前过哪些检查、MCP 能干
  什么——四问答得齐才算自描述合格。
- [ ] AGENTS.md 与真实结构对得上（server/src 权威、plugin/ 非权威、双前端
  已并一）。
- [ ] 自带 skill 的 frontmatter description 都能当触发条件用（一句话说清
  什么时候读）。
