# RESULT：1.x 框架核心剩余工作拆分（只写文档）

本任务只读核对，只写了这一个 RESULT.md。没有改仓库文件，没有开 PR，没有建库或启库，没有开下级会话，没有用云端，也没有读秘密。

## 0. 输入、口径、证据边界

- **路径对应**：本机没有 `/workspace/1x-docs/`、`/workspace/intro/`。实际读取的是：
  - `~/agent-work/grokbot/review-1117-r3/顶层架构设计-v4.md`（下称 v4），读了 f1 L538–689 和「11-17 独立审计新增项」表 L1138–1170
  - 同目录的 `鉴权P0-v4.md`（§E 工作包 L1035–1094）和 `待老板确认.md`（R-1/R-2/R-6/R-7 已于 11-17 按建议定稿）
  - `~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md`（下称总表，第五节为待确认项）
  - `~/agent-work/assistant/progress-1119/RESULT.md`（下称进度核算）
  - f1 的基数 57–60 在 v4:540 只写了总数，明细取自 `~/agent-work/grokbot/1x-gap-review/缺口检查.md:639–659`（21 条）
- **代码基线**：`~/agent-work/pub-sandadmin` 本地 `origin/main` = `977427a`（merge #92，#93 `d61f738` 已包含）。这个 ref 是 11-18 的本地快照，本任务没有 fetch，避免改动 .git 引用。
- **主干现状**（只读 `git ls-tree`/`git grep` 得到）：
  - 宿主 `server/src` 里**还没有**这些东西：会话认证（SessionAuthenticator）、迁移执行器（schema_migration）、IdGenerator、ConnectionRegistry、数据权限、缓存服务、安全响应头、SECURITY.md、CODEOWNERS、Webhook、导出实现
  - `server/database/migrations/` 里只有 0004–0007
  - `.github/` 被 `.gitignore` 忽略，只放行 `workflows/dependency-audit.yml`；`/docs/` 也被忽略
- **在途工作**（拆分时避开，以免重复或冲突）：
  - `1a-mcp-tools-1119`：U-3 剩余，正在跑
  - `q/tasks/1c-contract-m05-1119`：审计表 #6 M-05，已排队
  - `q/tasks/1d-audit-packageops-1119`：PR #94/#97 合并前审计，已排队
  - PR #96：J-1 三接口，未合并
  - PR #95：boot-smoke，未合并
  - PR #81：merge-gates CI，待批准
  - PR #98：1.x skill，改了 `.agents/skills` 和 `AGENTS.md`，未合并
  - PR #99：分段上传方案，未合并
- **验收口径**沿用进度核算：合并并且有同范围独立验收证据才算验收。进度核算里没有映射的行，一律记「未验收」。
- **两列的含义**：
  - 「需建库」：实现或验收要用真实 PostgreSQL/MySQL（包括临时库和容器）或 Redis。
  - 「DDL/迁移」：要新增表、改列或写迁移文件。
- **未拍板事项**取自总表第五节：
  - 国际化
  - workflow 归属
  - MySQL 最低版本
  - 双因素适用范围
  - 审计模型

  另把 N6 的 CLA/DCO、N13 的法务口径记为「外部决定」：v4:916/1184 写明要法务或老板定。

## 1. 框架核心、未验收或只部分验收的行

**已排除的插件归属行**：

- 业务插件连带改造 v4:567–573
- install.sql 转 MySQL v4:574
- AI 流式 v4:607
- AI 超时 v4:608
- AI 安全 v4:632

**已全额验收、不再列出的行**：

- v4:566 插件前端构建期集成
- v4:648 T-5
- v4:652 U-2
- v4:664 图片裁剪
- v4:665 水印
- 审计表 #3（v4:1144）
- 审计表 #5（v4:1146）
- 审计表 #24（v4:1165）

### 1.1 f1 基数 57–60（v4:540 → 缺口检查.md:639–659）

| # | 来源行 | 项 | 人天 | 验收 | 剩余部分 | 需建库 | DDL/迁移 | 未拍板 | 备注 |
|---|---|---|---|---|---|---|---|---|---|
| B1 | 缺口:639 | CI 门禁与契约入仓 | 2 | 部分（openapi 入仓随 #91 验收） | ci.yml 的 PHP 矩阵、PG 服务、deptrac、其他仓语法检查 | 是（CI 里的 PG 服务） | 否 | MySQL 一腿依赖 B18 | 被 #81 卡住（CI 文件争用） |
| B2 | 缺口:640 | 统一连接入口 | 2.5 | 未验收 | 全部 | 是 | 否 | 否 | |
| B3 | 缺口:641 | 主从读写分离 | 6 | 未验收 | 全部 | 是（PG 和 MySQL 各一主一从） | 是（心跳表） | MySQL 版本 | |
| B4 | 缺口:642 | 迁移执行器统一 | 1.5 | 未验收 | 全部 | 是 | 是（账本表） | 否 | 与 v4:565 合并做 |
| B5 | 缺口:643 | 迁移目录与基线入仓 | 1.5 | 未验收 | 全部 | 是 | 是 | MySQL 版本（基线 MySQL 版） | |
| B6 | 缺口:644 | 会话核心（鉴权 WP1） | 2 | 未验收 | 全部 | 是 | 是 | 否 | 鉴权 WP1/WP2 |
| B7 | 缺口:645 | 登录闭环（WP3） | 1.5 | 未验收 | 全部 | 是 | 是 | 否 | |
| B8 | 缺口:646 | 改密与会话管理（WP4/WP9） | 1.5 | 未验收 | 全部 | 是 | 是 | 否 | |
| B9 | 缺口:647 | 数据权限 | 2 | 未验收 | 全部 | 是 | 否 | 否 | |
| B10 | 缺口:648 | 缓存与锁 | 2 | 未验收 | 全部 | 是（Redis、DB 兜底表、咨询锁） | 是 | 否 | |
| B11 | 缺口:649 | 定时调度器 | 2.5 | 未验收（主干已有 ScheduleClaim 等，无验收映射） | 待核算 | 是 | 是 | 否 | |
| B12 | 缺口:650 | 对象存储 | 2.5 | 未验收（本地存储已有，无验收映射） | S3 驱动、短时签名下载、多实例自检 | 签名下载要库 | 否 | 否 | 与 T-7（PR #99 方案）改同一存储接口，等方案定稿再排 |
| B13 | 缺口:651 | 审计查询、健康检查、监控、全局限流 | 3 | 未验收 | 全部 | 是 | 否 | 否 | |
| B14 | 缺口:652 | 安全基线收尾 | 2 | 部分（弱密码 #84 已验收；按进度核算 §7 不能折算） | Origin 加自定义头、跨域白名单、日志审计脱敏、SQL 拼接静态检查，约 1.5 | **否** | 否 | 否 | 进任务 2-F、2-J |
| B15 | 缺口:653 | 插件和升级闭环 | 4 | 未验收 | PR #94/#97 只差真实库 | 是 | 否 | 否 | 在途（1d 审计） |
| B16 | 缺口:654 | 设置与剩余契约 | 3.5 | 未验收 | 全部 | 是 | 是（设置表） | 否 | |
| B17 | 缺口:655 | 不支持清单与部署文档 | 0.5 | 未验收 | 全部 | 否 | 否 | **MySQL 版本**（原文写「8.0 以上」，总表五-1 待确认） | 不排 |
| B18 | 缺口:656 | 两种库通用写法底座 | 4.5 | 未验收 | 全部 | 是 | 是 | MySQL 版本 | |
| B19 | 缺口:657 | 全局唯一主键 | 3 | 未验收 | 已被总表第 17 条改定（41+13+9 雪花，只用于不可预见的表），落到 v4:605/658 | 节点租约要库 | 是 | 否 | 不重复排，见 v4:605 |
| B20 | 缺口:658 | 老代码补齐（MySQL） | 5 | 未验收 | 全部 | 是 | 是 | MySQL 版本 | |
| B21 | 缺口:659 | sand-core 双库 | 4–7 | 未验收 | v4:546 记净 0，改由宿主侧承担 | 是 | 是 | MySQL 版本 | |

### 1.2 f1 新增项（v4:556–577，11-17 新增 v4:647–665）

| # | 来源行 | 项 | 人天 | 验收 | 剩余部分 | 需建库 | DDL/迁移 | 未拍板 | 备注 |
|---|---|---|---|---|---|---|---|---|---|
| N-a | v4:556 | sand-core 旧面迁入宿主 | 1–2 | 未验收 | 全部（SessionAuthAdapter 依赖 WP1） | 是 | 否 | 否 | |
| N-b | v4:557–564 | PackageOps 承接 | 5.7–11.5 | 未验收（#94/#97 未合并） | 合并和真实库验证 | 是 | 否 | 否 | 在途（1d） |
| N-c | v4:565 | 迁移机制改造 | 5–8 | 未验收 | 全部 | 是 | 是 | MySQL 版本（双方言） | |
| N-d | v4:575 | artd 残留页面搬迁 | 0.5–2 | 未验收（#86 的通用组件按 f1 附表计 0） | 盘点宿主自有页面，搬到 web/ | **否**（用 msw mock） | 否 | 否 | 任务 2-I |
| N-e | v4:576 | AGENTS.md 和文档改写（D 节必改 6，v4:421–430） | 0.5 | 未验收（主干 AGENTS.md 仍写「本仓无 migrations、无 CI」） | 全部 | **否** | 否 | 不写 MySQL 版本细化 | 任务 2-O，前置 #98 |
| N-f | v4:577 / v4:1118 | N1 SECURITY.md | 0.5–1 | 未验收（主干没有 SECURITY.md） | 全部；开启 Private Vulnerability Reporting 是仓库设置，留给总监 | **否** | 否 | 否 | 任务 2-D |
| N-g | v4:577 / v4:1119 | N3 安全响应头 | 0.5–1 | 未验收 | 全部 | **否** | 否 | Referrer-Policy 单值待总监定（技术选型，不是老板事项） | 任务 2-G |
| N-h | v4:577 / v4:1120 | N4 高危操作 sudo | 2–3 | 未验收 | 全部 | 是 | 是（`sudo_until_time`） | 否 | |
| N-i | v4:577 / v4:1121 | N5 密钥加密和主密钥轮换 | 2–3 | 未验收 | Crypto 服务、字段清单、文档可以无库做；`crypto:rotate` 对存量密文重加密要库 | 部分 | 否 | 否 | 任务 2-A 做无库部分 |
| N-j | v4:577 / v4:1122 | N6 CLA/DCO、贡献指南、CHANGELOG | 1–1.5 | 未验收 | 全部 | 否 | 否 | **外部决定**（CLA 还是 DCO 要法务或老板定，v4:916） | 不排 |
| N-k | v4:577 / v4:1123 | N7 导出公式注入规约 | 0.5 | 未验收（宿主还没有导出实现） | 统一转义器、静态检查、用例 | **否** | 否 | 否 | 任务 2-E |
| N-l | v4:577 / v4:1124 | N11 滚动升级迁移规则 | 0.5–1 | 未验收 | 依赖迁移执行器 | 是 | 是 | 否 | 随 v4:565 |
| N-m | v4:577 / v4:1125 | N12 连接 TLS | 0.5–1 | 未验收 | 全部 | 是 | 否 | 否 | |
| N-n | v4:577 / v4:1126 | N13 个人信息合规文档 | 0.5 | 未验收 | 全部 | 否 | 否 | **外部决定**（法务口径，v4:1184） | 不排 |
| N-o | v4:577 / v4:1127 | N16 支持周期与弃用政策文档 | 0.5 | 未验收 | 全部 | 否 | 否 | **MySQL 版本**（支持矩阵） | 不排 |
| N-p | v4:647 | T-4 日志留存 | 0.5–1 | 部分（#89：文件日志 ≈0.3–0.5） | 库表日志清理、设置页 | 是 | 否 | 否 | |
| N-q | v4:650 | T-7 分段上传 | 3–5 | 未验收（只有方案 PR #99） | 全部 | 是 | 是 | 否 | |
| N-r | v4:651 | T-8 TypePHP | 2–4 | 部分（#88 ≈1.5–3） | CI 门禁 job | 否（本地）；CI 在云上 | 否 | 否 | 任务 2-M，前置 #81 |
| N-s | v4:653 | U-3 框架即 MCP 服务 | 4–7 | 部分（≈1–2） | 业务工具集、权限门、审计；机器令牌并入审计表 #1 | 部分 | 部分 | 否 | 在途（1a），不重复排 |
| N-t | v4:654 | U-4 框架作 MCP 客户端 | 2–4 | 未验收 | 全部（外部 MCP 登记要表） | 是 | 是 | 否 | 出网校验先由任务 2-L 打底 |
| N-u | v4:655 | U-6 AI 改代码闭环 | 5–10（其中 2–3 已并入审计表 #5） | 未验收（pre-merge.sh 已有 7 步，无验收映射） | 模块测试规范与样例 1–2、保留上一版与回退命令 0.5–1 可无库做；迁移回退验证 1–3 要库 | 部分 | 部分 | 否 | 任务 2-C 做无库部分 |
| N-v | v4:656 | T-6 密码定期更换 | 0.5–1 | 未验收 | 全部 | 是 | 是（字段） | 否 | |
| N-w | v4:657 | T-9 国际化留口 | 1.5–3 | 部分（#82 插件 i18n 收集点 ≈0.5–1） | 错误码、文案 key、硬编码检查 | 否 | 否 | **国际化**（总表五-4） | 不排 |
| N-x | v4:658 | 雪花节点登记表 | 0.5–1 | 未验收 | 全部 | 是 | 是（`sand_system_id_node`） | 否 | |
| N-y | v4:659 | 附件分类和批量移动 | 2–4 | 未验收 | 全部 | 是 | 是 | 否 | |
| N-z | v4:660 | Excel 通用导入 | 1–2 | 未验收（fe-gap 跳过，理由是要新接口） | 模板、解析校验、逐行报错、按资源登记可无库做；真实写入资源要库 | 部分 | 否 | 否 | 任务 2-E |
| N-aa | v4:661 | 通用助手 | 0.5–1 | 未验收 | 缺口.md:34 写「随页面重做按需补」，不单独成包 | 否 | 否 | 否 | 不单独排 |
| N-ab | v4:662 | 锁屏 | 0.5–1 | 部分（#86 前端 ScreenLock 已独立验收，进度核算未计入） | 与会话联动（等 `/auth/sudo`，依赖 N4） | 是 | 是 | 否 | 进度核算可补计前端部分 |
| N-ac | v4:663 | 设置面板 | 0.5–1 | 已交付（#86 SettingsPanel，独立验收通过，fe-gap-1117 RESULT §五），进度核算未计入 | 无 | — | — | — | 建议进度核算补计 |

### 1.3 A/C 评审新增项（v4:602–616，不含 607/608）

| # | 来源行 | 项 | 人天 | 验收 | 剩余 | 需建库 | DDL/迁移 | 未拍板 | 备注 |
|---|---|---|---|---|---|---|---|---|---|
| AC-1 | v4:602 | 多组织三接口与静态检查 | 2–4 | 未验收（#96 未合并） | 合并以后的静态检查和测试 | 是 | 否 | 否 | 在途 #96 |
| AC-2 | v4:603 | 律序桌面端插座 | 3–6 | 未验收 | 全部 | 是 | 是 | 否 | |
| AC-3 | v4:604 | 宿主账号层 | 1.8–3.4 | 未验收 | 全部 | 是 | 是 | 否 | |
| AC-4 | v4:605 | 编号策略落地 | 0.5–1 | 未验收 | 发号器与约定 0.3–0.7 可无库做；外键同型检查和两库测试要库 | 部分 | 否（发号器） | 否 | 任务 2-N |
| AC-5 | v4:606 | 连接代理与事务池出路 | 1–2 | 未验收 | 账务验证要库；总表第 53 条运维暂停 | 是 | 否 | 否 | |
| AC-6 | v4:609 | 会话 Redis 改造 | 2–3 | 未验收 | 全部（依赖 WP1） | 是（Redis） | 是 | 否 | |
| AC-7 | v4:610 | 本机并发闸 | 0.5–1 | 未验收 | 要接的「回主库鉴权路径」还不存在 | 否 | 否 | 否 | 依赖 WP1，暂不排，免得做出没人用的代码 |
| AC-8 | v4:611 | 权限版本号单调化 | 1–2 | 未验收 | 全部 | 是（Redis、outbox 表） | 是 | 否 | |
| AC-9 | v4:612 | 读写分离覆盖插件 | 1.5–3 | 未验收 | 全部 | 是 | 否 | 否 | |
| AC-10 | v4:613 | 大表在线变更 | 1.5–3 | 未验收 | 全部 | 是 | 否 | **MySQL 版本**（INSTANT/INPLACE 依赖版本） | |
| AC-11 | v4:614 | 密钥管理接口 | 1–2 | 未验收 | 全部 | **否** | 否 | 否 | 任务 2-A |
| AC-12 | v4:615 | 压测与门禁数字 | 1.5–3 | 未验收 | 全部 | 是 | 否 | 否 | |
| AC-13 | v4:616 | 用量计量、额度池、阈值事件 | 2–4 | 未验收 | 全部 | 是 | 是 | 否 | |

### 1.4 安全评审新增项（v4:623–636，不含 632）

| # | 来源行 | 项 | 人天 | 验收 | 剩余 | 需建库 | DDL/迁移 | 未拍板 | 备注 |
|---|---|---|---|---|---|---|---|---|---|
| S-1 | v4:623 | 双因素和恢复码 | 3–5 | 未验收 | 全部 | 是 | 是 | **双因素范围**（总表五-2） | |
| S-2 | v4:624 | 找回密码全套 | 3–6 | 未验收 | 全部 | 是 | 是 | 双因素联动 | |
| S-3 | v4:625 | 认证与会话加固 | 2–4 | 未验收 | 全部（依赖 WP1/WP3） | 是 | 是 | 否 | |
| S-4 | v4:626 | 中间件链生产守卫 | 1–2 | 未验收 | 断言器和接入点可无库做；「旧 JWT→401」那部分断言要会话 | 部分 | 否 | 否 | 任务 2-H |
| S-5 | v4:627 | 授权判定读主库 | 0.5–1 | 未验收 | 依赖读写分离 | 是 | 否 | 否 | |
| S-6 | v4:628 | 数据库账号五类和 TLS 强制 | 1–2 | 未验收 | 全部 | 是 | 否 | 否 | |
| S-7 | v4:629 | 插件信任边界 | 1.5–3 | 未验收 | 依赖迁移执行器和库账号 | 是 | 否 | 否 | |
| S-8 | v4:630 | 插件签名密钥管理 | 0.5–1 | 未验收 | 规范成文可无库做；验签拒绝在 #94 | 否（文档） | 否 | 否 | 任务 2-D |
| S-9 | v4:631 | 备份与审计日志最低配置 | 0.5–1.5 | 未验收 | 依赖 DB-02 | 是 | 否 | 否 | |
| S-10 | v4:633 | 漏洞响应合规 OSS-01/02 | 0.3–0.5 | 未验收 | 全部 | **否** | 否 | 否 | 任务 2-D |
| S-11 | v4:634 | 组织上下文安全 | 1.5–3 | 未验收 | 跨租户越权测试 | 是 | 否 | 否 | 依赖 #96 |
| S-12 | v4:635 | Webhook 安全三件 | 2–4 | 未验收 | SSRF 校验组件 0.5–1 可无库做；端点密钥和熔断要表 | 部分 | 部分 | 否 | 任务 2-L |
| S-13 | v4:636 | 供应链门禁 | 2–4 | 未验收（只有 dependency-audit.yml） | job 加进 Actions、SBOM、EOL 矩阵、UPSTREAM；Dependabot 和分支保护是仓库设置 | 否（本地） | 否 | EOL 矩阵含 **MySQL 版本** | 被 #81 卡住；含未拍板项，不排 |

### 1.5 11-17 独立审计新增表（v4:1140–1168）

| # | 来源行 | 项 | 人天 | 验收 | 剩余 | 需建库 | DDL/迁移 | 未拍板 | 备注 |
|---|---|---|---|---|---|---|---|---|---|
| A1 | v4:1142 | 机器令牌 | 3–5 | 未验收 | 全部 | 是 | 是 | 否（R-1 已定） | |
| A2 | v4:1143 | MCP 写操作三档、待确认请求 | 3–5 | 未验收（主干只有 Domain/Mcp 雏形） | 全部 | 是 | 是 | 否 | |
| A4 | v4:1145 | MCP 客户端工具定义锁定 | 0.5–1 | 未验收 | 依赖 U-4 登记表 | 是 | 是 | 否 | |
| A6 | v4:1147 | 插件契约测试、命名连接四规则 | 1.5–3 | 部分（≈1–2） | M-05 | 否 | 否 | 否 | 在途（1c） |
| A7 | v4:1148 | 租户作用域机制 | 1.5–3 | 未验收（#96 未合并） | 两租户越权矩阵 | 是 | 否 | 否（R-7 已定） | 在途 #96 |
| A8 | v4:1149 | 生产回滚口径 | 0.5–1 | 未验收 | 依赖执行器 | 是 | 否 | 否 | |
| A9 | v4:1150 | 审计、日志、搜索写入接口 | 1.5–3 | 未验收 | 接口、异步批量、防篡改链、非库实现可无库做；默认库表实现要库 | 部分 | 部分 | 否 | 任务 2-H2，前置 1a |
| A10 | v4:1151 | 门禁自保护 | 1–2 | 未验收 | CODEOWNERS、只减不增检查、机器可读报告可无库做；「打标签禁自批」要 Actions | 否 | 否 | 否 | 任务 2-K |
| A11 | v4:1152 | 门禁工具与规矩书进仓 | 0.5–1 | 未验收（tools/ 和 skill-sync.sh 大概率已由 #83 交付，进度核算只映射到 U-2） | 待核算 | 否 | 否 | 否 | 建议只读核对，不排实现 |
| A12 | v4:1153 | 测试判定口径 | 1.5–3 | 未验收 | 全部 | **否**（Playwright 冒烟用 mock） | 否 | 否 | 任务 2-B |
| A13 | v4:1154 | 恢复点检查接口 | 0.5–1 | 未验收 | 依赖执行器和 pg_dump | 是 | 否 | 否 | |
| A14 | v4:1155 | 门禁快慢两级 | 0.5–1 | 未验收 | 夜间三库矩阵 | 是（CI） | 否 | MySQL 版本 | 被 #81 卡住 |
| A15 | v4:1156 | MySQL 8.0.x 安装警告 | 0.3–0.5 | 未验收 | 全部 | 否 | 否 | **MySQL 版本** | 不排 |
| A16 | v4:1157 | 单机文件存储例外 | 0.3–0.5 | 未验收 | 全部 | **否** | 否 | 否 | 任务 2-P |
| A17 | v4:1158 | 乐观锁约定 | 0.5–1 | 未验收 | 全部 | 是 | 是（version 列） | 否 | |
| A18 | v4:1159 | 游标分页 | 0.3–0.5 | 未验收 | 总数约数口径依赖库 | 是 | 否 | 否 | |
| A19 | v4:1160 | 导入导出转后台任务 | 0.5–1 | 未验收 | 任务表 | 是 | 否 | 否 | |
| A20 | v4:1161 | 用量预留额度 | 0.5–1 | 未验收 | 全部 | 是（共享计数） | 否 | 否 | |
| A21 | v4:1162 | 存量迁移压基线 | 0.3–0.5 | 未验收 | 全部 | 是 | 是 | 否 | |
| A22 | v4:1163 | bot 审批口径 | 0–0.3 | 未验收 | 真人审批名单 | 否 | 否 | **要人定名单**（11-19 已改为 Opus 审计视同批准，名单仍未成文） | 不排 |
| A23 | v4:1164 | AI 上下文打包 | 0.3–0.5 | 未验收 | 全部 | **否** | 否 | 否 | 任务 2-Q |

### 1.6 第 1 点小结

- **要库、要 DDL、或卡在未拍板事项上的**：B1–B13、B15–B21、N-a/b/c/h/j/l–q/t/v/w/x/y/ab、AC-1–3/5/6/8–10/12/13、S-1–3/5–7/9/11/13、A1/2/4/7/8/13–15/17–22。本轮不写任务。
- **在途、不重复排**：U-3（1a）、A6（1c）、PackageOps/B15（1d、#94/#97）、AC-1/A7（#96）。
- **依赖未开工的前置、暂不排**：AC-7 并发闸、N-aa 通用助手、B12 对象存储（等 PR #99 定稿）。
- **待核算**：B11、A11；N-ac/N-ab 已交付部分建议补计进度。

## 2. 可直接排队的无库任务（按剩余人天中点从大到小）

### 2.0 总表

| 序 | 任务名 | 来源行 | 剩余人天 | 模型 | 改动范围 | 与其他项是否改同一批文件 |
|---|---|---|---|---|---|---|
| 2-A | crypto-keyprovider-1119 | v4:614、v4:1121（N5 无库部分） | 2–3.5 | SWE-2 | `server/src` 新增 Security/Crypto，config，测试 | 独立；以后 U-4、Webhook 会用它 |
| 2-B | test-gate-1119 | v4:1153（A12） | 1.5–3 | SWE-2 | `tools/pre-merge.sh`、`server/phpunit.xml.dist`、`tools/` 新脚本、`web/` Playwright 冒烟 | **与 2-C、2-J、2-K、2-M、2-Q 同改 tools/pre-merge.sh，必须串行** |
| 2-C | ai-loop-rollback-1119 | v4:655（U-6 无库部分） | 1.5–3 | SWE-2 | `tools/` 回退脚本、测试样例、`tools/build-release.sh` | 同上，**串在 2-B 之后** |
| 2-D | security-docs-1119 | v4:1118（N1）、v4:633、v4:630 | 1.3–2.5 | SWE-2 | 根目录 `SECURITY.md`、`server/docs/` | 与 2-O 都碰根目录文档，文件不同，可并行 |
| 2-E | table-io-1119 | v4:660、v4:1123（N7） | 1.2–2 | SWE-2 | `server/src` 导入导出模块、`contract/openapi.yaml`、生成产物、`web/` 导入组件 | **改契约和生成产物，与 1c 冲突**（1c 也重新生成产物）；排在 1c 合并之后 |
| 2-H | middleware-chain-guard-1119 | v4:626 | 1–2 | SWE-2 | `server/app/bootstrap/`、`server/config/bootstrap.php`、测试 fixture | **与 2-G、2-P 同改 config/bootstrap 或 config/middleware.php，必须串行** |
| 2-G | http-security-mw-1119 | v4:1119（N3）、缺口:652（B14 的 Origin 和跨域部分） | 1–1.8 | SWE-2 | `server/src/Http/Middleware`、`server/config/middleware.php`、`server/config/security.php` | 与 2-H、2-L 同改 config/security.php |
| 2-H2 | audit-sink-1119 | v4:1150（A9 无库部分） | 1–1.5 | SWE-2 | `server/src/Domain/Audit`、`server/src/Infra/Audit`、`Domain/Mcp/AuditSink` | **与 1a（正在跑）改同一处 MCP 审计，前置：1a 合并** |
| 2-I | artd-residue-1119 | v4:575 | 0.5–2 | SWE-2 | `web/src/pages`、`web/src/routes`、`web/src/mocks` | 与 2-E 的前端部分同改 routes 和 i18n，建议错开 |
| 2-K | gate-selfguard-1119 | v4:1151（A10 无库部分） | 0.7–1.5 | SWE-2 | 根目录 `CODEOWNERS`、`tools/` 检查脚本、`tools/pre-merge.sh` | 门禁组，串行 |
| 2-J | redact-sqlcheck-1119 | 缺口:652（B14 的脱敏和 SQL 拼接检查） | 0.7–1 | SWE-2 | `server/src/Infra/Log`、`tools/` 或 `server/tests/security/` 扫描、`tools/pre-merge.sh` | 门禁组，串行 |
| 2-L | outbound-guard-1119 | v4:635（WH-01 校验组件）、鉴权 R61 | 0.5–1 | SWE-2 | `server/src` 新增出网入口、`config/security.php` | 与 2-G 同改 config/security.php，串行 |
| 2-M | typephp-ci-1119 | v4:651（T-8 剩余） | 0.5–1 | SWE-2 | `.github/workflows/` | **前置：#81 合并**；门禁组 |
| 2-N | id-generator-1119 | v4:605（发号器部分） | 0.3–0.7 | SWE-2 | `server/src/Infra/Id`（新增）、config | 独立 |
| 2-O | agents-md-1119 | v4:576（D 节必改 6） | 0.5 | SWE-2 | 根目录 `AGENTS.md` | **前置：#98 合并**（#98 改了 AGENTS.md） |
| 2-P | single-node-storage-1119 | v4:1157（A16） | 0.3–0.5 | SWE-2 | `server/src/Application/File/StorageConfig.php`、`server/app/bootstrap/` | 与 2-H 同改 bootstrap；将来 T-7、B12 也会改 StorageConfig |
| 2-Q | ai-context-pack-1119 | v4:1164（A23） | 0.3–0.5 | SWE-2 | `tools/` 新脚本（skill 引用等 #98 合并后再补） | 门禁组只读 pre-merge.sh，不改它，可并行 |

- 中点排序：2-A 2.75 > 2-B 2.25 = 2-C 2.25 > 2-D 1.9 > 2-E 1.6 > 2-H 1.5 > 2-G 1.4 > 2-H2 1.25 = 2-I 1.25 > 2-K 1.1 > 2-J 0.85 > 2-L 0.75 = 2-M 0.75 > 2-N 0.5 = 2-O 0.5 > 2-P 0.4 = 2-Q 0.4。
- **模型建议**：全部用 SWE-2（swe-2-high，model.txt 留空）。依据是总表第 43 条（开发只用本地 Devin swe-2-high）和第 50 条（Claude 只用在关口和免费模型反复做不好的活）。
- **建议另排 Claude 审计（属于关口，不是实现）**：2-A 加解密、2-H 中间件链守卫、2-L 出网 SSRF。这几项完成后，排一次 claude-opus-5-5-medium 只读安全审计。审计任务本轮没写。
- **并行**：总表第 43/44 条限两槽。下面几组彼此不改同一批文件，可以两两并行：
  - 2-A
  - 2-D
  - 2-N
  - 2-I
  - 2-Q
  - 门禁组中的一项（2-B → 2-C → 2-K → 2-J → 2-M）
  - HTTP 组中的一项（2-G → 2-L → 2-H → 2-P）

---

### 2-A 密钥管理接口与加密服务（无库部分）

```
【任务】1.x 框架密钥管理接口（KeyProvider）与 Crypto 加密服务，无库部分

依据：v4 第 614 行「密钥管理接口（M11）：KeyProviderInterface 信封加密 + env 默认实现 + KMS/Vault 可替换 + 按组织数据密钥（J-10）」1–2 人天；v4 第 902–911 行 N5「第三方密钥加密存储＋主密钥管理和轮换」（f1 第 1121 行 2–3 人天）。两项都未验收，见 ~/agent-work/assistant/progress-1119/RESULT.md。

输入（Mac 路径）：~/agent-work/grokbot/review-1117-r3/顶层架构设计-v4.md（第 614、902–911 行，J-10 节）和同目录 鉴权P0-v4.md（WP8 的 SAND_CRYPTO_KEY 派生子密钥、LOG-03 脱敏器）；~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md；supdger/sandadmin main 的 server/src、server/config（只读起步）；PR #98 分支 docs/1x-skills 的 .agents/skills（照规范写，未合并，只读）。

要做：
1. KeyProviderInterface（信封加密：数据密钥加解密、按 keyver 取主密钥）＋ env 默认实现（主密钥键 SAND_CRYPTO_KEY，旧密钥列表 SAND_CRYPTO_PREVIOUS_KEYS，只从环境变量读，不落库、不进日志）；KMS/Vault 只留可替换接口，不接真实服务。按组织数据密钥：框架单租户，组织号固定 'default'（总表第 11 条、R-7）。
2. Crypto 服务：AES-256-GCM（openssl），密文格式 v<keyver>:<nonce>:<ct>:<tag>；按 keyver 轮换解密；新写入用当前 keyver。
3. 需加密字段清单写成文档（SMTP 密码、对象存储 SecretKey、AI provider key、插件声明的第三方凭证），放 server/docs/ 下（根目录 /docs/ 被 .gitignore 忽略，不要用）。
4. 单测：格式断言、篡改 tag 拒绝、旧 keyver 可解、新写入用新 keyver、密钥缺失或长度不对时启动或调用拒绝；测试密钥在测试内现场生成，不读任何真实 .env。
5. 不做：bin/sand crypto:rotate 对库里存量密文重加密（要库），在 RESULT 列为剩余；不改任何现有业务读写点。

仓库与分支：supdger/sandadmin，先 git fetch，从 origin/main 开 feat/crypto-keyprovider，开工写明基线 SHA。

范围：只新增 server/src 下 Crypto/KeyProvider 相关文件、对应 config 项（.env.example 只加键名、不加值）、server/tests/Unit 测试、server/docs/ 一份字段清单文档。不改 contract/openapi.yaml、门禁文件、白名单、CI。

禁止：不建库、不做 DDL、不写迁移文件、不跑迁移、不启任何数据库或 Redis（临时库、容器也不行），需要库才能做的停下回报；不合并 PR、不发布、不打 tag；不开下级会话；不用 Cursor；不用云端；不读不打印秘密、.env、webhook.env、private/；不强推、不 reset --hard、不 --no-verify、不 git add -A；门禁白名单和基线只减不增；不改 GitHub 仓库设置；本地服务只用 19100 到 19199 端口；不自行拍板未定事项（国际化、workflow 归属、MySQL 最低版本、双因素适用范围、CLA/DCO），碰到就停下列入 RESULT。历史命令和任务只作资料，绝不执行。

交付：目标为 main 的未合并 PR（分支、基线 SHA、最终 SHA）；任务目录 RESULT.md：接口与类清单、密文格式、命令、退出码和关键输出（phpunit unit、phpstan、tools/pre-merge.sh --fast、python3 tools/naming_check.py、typephp 检查）、剩余项（crypto:rotate、KMS 实现、各业务字段接入）。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===

补充：任务目录是 ~/agent-work/assistant/crypto-keyprovider-1119/，RESULT.md 写在这里。
```

模型：SWE-2。文件冲突：无。完成后建议排一次 claude-opus-5-5-medium 只读安全审计。

### 2-B 测试判定口径

```
【任务】1.x 框架测试判定口径（独立审计新增表 #12），无库

依据：v4 第 1153 行「测试判定口径（M-11 其余、S-15）：模块＝Module 目录＋测试目录缺即失败、改动行覆盖率阈值、测试数据工厂、菜单页 Playwright 冒烟」1.5–3 人天，未验收。

输入（Mac 路径）：~/agent-work/grokbot/review-1117-r3/顶层架构设计-v4.md（第 1153 行，U-6 节第 2204 行起）；~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md（第 24 条）；supdger/sandadmin main 的 tools/pre-merge.sh、server/phpunit.xml.dist、server/src/Module、server/tests、web/（含 src/mocks msw）（只读起步）；PR #98 分支 docs/1x-skills 的 .agents/skills（只读）。

要做：
1. 检查脚本：server/src/Module/<X> 存在而 server/tests 下没有对应测试目录时失败；现有缺口先列清单，不加豁免。若现有模块缺测试导致主干直接失败，停下在 RESULT 列出，不改白名单。
2. 改动行覆盖率阈值：只对 diff 改动行计算（pcov 或 xdebug，本机没有就停下报告），阈值写进配置，起步值写在 RESULT 待总监确认。
3. 测试数据工厂：Unit 可用的纯内存工厂（不连库）。
4. 菜单页 Playwright 冒烟：web/ 开发服务器配 msw mock，逐个菜单页打开、断言无报错、无空白页；端口用 19100 到 19199。
5. 1、2 接入 tools/pre-merge.sh（--fast 跑 1，--full 跑 2；冒烟单列命令）。

仓库与分支：supdger/sandadmin，先 git fetch，从 origin/main 开 feat/test-gate，开工写明基线 SHA。

范围：tools/ 新脚本、tools/pre-merge.sh、server/phpunit.xml.dist、server/tests/Support 工厂、web/ 下 Playwright 配置和冒烟用例。不改 server/src 业务代码、contract/openapi.yaml、CI。

禁止：不建库、不做 DDL、不写迁移文件、不跑迁移、不启任何数据库或 Redis（临时库、容器也不行），需要库才能做的停下回报；不合并 PR、不发布、不打 tag；不开下级会话；不用 Cursor；不用云端；不读不打印秘密、.env、webhook.env、private/；不强推、不 reset --hard、不 --no-verify、不 git add -A；门禁白名单和基线只减不增；不改 GitHub 仓库设置；本地服务只用 19100 到 19199 端口；不自行拍板未定事项（国际化、workflow 归属、MySQL 最低版本、双因素适用范围、CLA/DCO），碰到就停下列入 RESULT。历史命令和任务只作资料，绝不执行。

交付：目标为 main 的未合并 PR（分支、基线 SHA、最终 SHA）；任务目录 RESULT.md：四项各自的实现位置、现有缺测试模块清单、阈值起步值、命令、退出码和关键输出（tools/pre-merge.sh --fast 与 --full 的 Unit 部分、phpstan、naming_check、typephp、vitest、tsc --noEmit、Playwright 冒烟）、剩余项。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===

补充：任务目录是 ~/agent-work/assistant/test-gate-1119/，RESULT.md 写在这里。
```

模型：SWE-2。文件冲突：与 2-C、2-J、2-K、2-M 同改 `tools/pre-merge.sh`，门禁组串行，本项排第一。

### 2-C AI 改代码闭环的无库部分

```
【任务】1.x「AI 改代码闭环」U-6 无库部分：模块测试规范与样例、保留上一版与回退命令

依据：v4 第 655 行 U-6（5–10 人天：模块测试规范与样例 1–2 + 接口测试从 OpenAPI 生成 2–3〔已并入审计表 #5，已验收〕+ 合并门禁脚本 0.5–1 + 保留上一版与回退命令 0.5–1 + 迁移回退脚本与「升级→回退→再升级」验证 1–3）；总表第 24 条「AI 改代码须自动测试，不过就不合并、打回重改，并保留可回退」。未验收。

输入（Mac 路径）：~/agent-work/grokbot/review-1117-r3/顶层架构设计-v4.md（第 655 行、U-6 节第 2204 行起、第 524–531 行迁移不支持清单）；~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md；supdger/sandadmin main 的 tools/pre-merge.sh、tools/build-release.sh、server/tests（只读起步）；PR #98 分支 docs/1x-skills 的 .agents/skills（尤其 sandadmin1x-ai-loop，只读）。

要做：
1. 模块测试规范与样例：以一个现有模块（如 server/src/Module/Dict 或 Announcement）为样板，补一套 Unit 层样例测试和一页规范（放 server/docs/），说明新模块必须带哪些测试。
2. 对照 U-6 核对 tools/pre-merge.sh 现有 7 步是否已满足「合并门禁脚本」，缺什么列在 RESULT，只补无库可做的缺项。
3. 保留上一版与回退命令：发布产物保留上一版，加回退命令（切回上一版产物，不碰数据库），带干跑模式和测试。
4. 不做：迁移回退脚本与「升级→回退→再升级」验证（要库），列为剩余。

仓库与分支：supdger/sandadmin，先 git fetch，从 origin/main（若 test-gate PR 已合并就基于它）开 feat/ai-loop-rollback，开工写明基线 SHA。

范围：server/tests 样例、server/docs/ 规范文档、tools/ 回退脚本、tools/build-release.sh、tools/pre-merge.sh 的必要接入。不改 server/src 业务代码、contract/openapi.yaml、CI。

禁止：不建库、不做 DDL、不写迁移文件、不跑迁移、不启任何数据库或 Redis（临时库、容器也不行），需要库才能做的停下回报；不合并 PR、不发布、不打 tag；不开下级会话；不用 Cursor；不用云端；不读不打印秘密、.env、webhook.env、private/；不强推、不 reset --hard、不 --no-verify、不 git add -A；门禁白名单和基线只减不增；不改 GitHub 仓库设置；本地服务只用 19100 到 19199 端口；不自行拍板未定事项（国际化、workflow 归属、MySQL 最低版本、双因素适用范围、CLA/DCO），碰到就停下列入 RESULT。历史命令和任务只作资料，绝不执行。

交付：目标为 main 的未合并 PR（分支、基线 SHA、最终 SHA）；任务目录 RESULT.md：样例与规范位置、pre-merge 对照结果、回退命令用法和测试、命令、退出码和关键输出（phpunit unit、phpstan、tools/pre-merge.sh --fast、naming_check、typephp、回退命令干跑）、剩余项（迁移回退验证）。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===

补充：任务目录是 ~/agent-work/assistant/ai-loop-rollback-1119/，RESULT.md 写在这里。
```

模型：SWE-2。文件冲突：门禁组，排在 2-B 之后。

### 2-D 安全文档包（SECURITY.md、漏洞报送合规、插件签名密钥规范）

```
【任务】1.x 安全文档：SECURITY.md、漏洞响应合规、插件签名密钥管理规范（只写文档）

依据：v4 第 858–870 行 N1（f1 第 1118 行 0.5–1 人天）；v4 第 633 行 OSS-01/OSS-02（工信部报送义务、security.txt、备用渠道，0.3–0.5）；v4 第 630 行 PLG-02 插件签名密钥管理（信任根、私钥保管、轮换、吊销成文，0.5–1；验签实现在 PR #94，不在本任务）。均未验收。

输入（Mac 路径）：~/agent-work/grokbot/review-1117-r3/顶层架构设计-v4.md（第 630、633、858–870 行，K-3 节）；同目录 鉴权P0-v4.md；~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md；supdger/sandadmin main 根目录文件、RELEASE-CHECKLIST.md、tools/build-release.sh（只读）；PR #94（feat/kernel-api-runtime-align）的签名相关代码，用 gh 只读查看，不评论、不检出改动。

要做：
1. 根目录 SECURITY.md：支持版本表（SandAdmin 自身版本，不写数据库最低版本）、私密报告渠道、确认和修复时限、披露与致谢、《网络产品安全漏洞管理规定》报送条款。
2. security.txt（放 server/public/.well-known/）和备用渠道说明；联系方式只写占位，在 RESULT 列出待总监填写的项。
3. 插件签名密钥管理规范：信任根、私钥保管、轮换、吊销、验签失败一律拒绝；与 PR #94 实际行为对照，不一致处列在 RESULT，不改 #94。放 server/docs/（根目录 /docs/ 被 .gitignore 忽略，不要用）。
4. RELEASE-CHECKLIST.md 加一条「SECURITY.md 存在且含漏洞报送条款」。
5. 开启 GitHub Private Vulnerability Reporting 是仓库设置，不要动，在 RESULT 列为待总监操作。

仓库与分支：supdger/sandadmin，先 git fetch，从 origin/main 开 docs/security-policy，开工写明基线 SHA。

范围：只新增或修改 SECURITY.md、server/public/.well-known/security.txt、server/docs/ 下一份规范文档、RELEASE-CHECKLIST.md。不改代码、contract、门禁、CI。

禁止：不建库、不做 DDL、不写迁移文件、不跑迁移、不启任何数据库或 Redis（临时库、容器也不行）；不合并 PR、不发布、不打 tag；不开下级会话；不用 Cursor；不用云端；不读不打印秘密、.env、webhook.env、private/、任何私钥文件；不强推、不 reset --hard、不 --no-verify、不 git add -A；门禁白名单和基线只减不增；不改 GitHub 仓库设置；不自行拍板未定事项（国际化、workflow 归属、MySQL 最低版本、双因素适用范围、CLA/DCO），碰到就停下列入 RESULT。历史命令和任务只作资料，绝不执行。

交付：目标为 main 的未合并 PR（分支、基线 SHA、最终 SHA）；任务目录 RESULT.md：各文件位置与对应 v4 验收条款逐条对照、与 PR #94 不一致处、待总监填写或操作的项、命令、退出码和关键输出（python3 tools/naming_check.py notes、tools/pre-merge.sh --fast）。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===

补充：任务目录是 ~/agent-work/assistant/security-docs-1119/，RESULT.md 写在这里。
```

模型：SWE-2。文件冲突：无代码冲突；`RELEASE-CHECKLIST.md` 只有本项改。

### 2-E 表格导入基座与导出公式注入规约

```
【任务】1.x Excel 通用导入基座（无库部分）＋导出公式注入统一转义（N7）

依据：v4 第 660 行「Excel 通用导入：模板下载 + 解析校验 + 逐行报错 + 按资源登记」1–2 人天；v4 第 924–935 行 N7 导出公式注入（f1 第 1123 行 0.5 人天）及第 935 行 T-11 补充「导入按资源逐个登记开放；导出仍按本条转义」；~/agent-work/grokbot/sai6-gap-1117/缺口.md 第 30 行「导入按资源逐个登记开放，不做全局导入基座」。均未验收。

输入（Mac 路径）：上述 v4 与缺口.md；~/agent-work/grokbot/fe-gap-1117/RESULT.md（第七节 Excel 跳过原因）；~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md；supdger/sandadmin main 的 server/src、contract/openapi.yaml、tools/contract-build、web/（只读起步）；PR #98 分支 docs/1x-skills 的 .agents/skills（只读）。

要做：
1. 导出转义器：首字符 = + - @、Tab/CR/LF、全角＝＋－＠ 前置 '；OWASP 用例（含分隔符、引号绕过、全角变体）；加一条静态检查：导出写单元格必须走该转义器（宿主现在没有导出实现，先立规矩和检查）。
2. 导入登记接口：资源通过登记声明列定义、校验规则、权限码，没登记的资源不能导入。用已有 openspout（composer 已依赖）解析 xlsx/csv；逐行报错结构；模板下载按登记的列生成（模板单元格同样走转义器）。
3. 契约：在 contract/openapi.yaml 增加模板下载和「解析校验（只校验不写入）」端点，用 php tools/contract-build 重新生成产物；真正写入资源的端点不做（要库），列为剩余。
4. web/：导入弹窗组件（上传、显示逐行错误、下载模板），msw mock，vitest。

仓库与分支：supdger/sandadmin，先 git fetch，从 origin/main 开 feat/table-io，开工写明基线 SHA。若 q/tasks/1c-contract-m05-1119 的 PR 未合并，契约产物可能冲突，先在 RESULT 写明当时 main SHA。

范围：server/src 新增导入导出模块与测试、contract/openapi.yaml 的新增条目和重新生成的产物、tools/ 下转义静态检查（接入 pre-merge 只追加一步）、web/ 导入组件。不改既有接口、门禁白名单、CI。

禁止：不建库、不做 DDL、不写迁移文件、不跑迁移、不启任何数据库或 Redis（临时库、容器也不行），需要库才能做的停下回报；不合并 PR、不发布、不打 tag；不开下级会话；不用 Cursor；不用云端；不读不打印秘密、.env、webhook.env、private/；不强推、不 reset --hard、不 --no-verify、不 git add -A；门禁白名单和基线只减不增；不改 GitHub 仓库设置；本地服务只用 19100 到 19199 端口；不自行拍板未定事项（国际化、workflow 归属、MySQL 最低版本、双因素适用范围、CLA/DCO），碰到就停下列入 RESULT。历史命令和任务只作资料，绝不执行。

交付：目标为 main 的未合并 PR（分支、基线 SHA、最终 SHA）；任务目录 RESULT.md：转义器与静态检查、登记接口、新增端点、前端组件、命令、退出码和关键输出（phpunit unit 和 contract、phpstan、php tools/contract-build --check、tools/pre-merge.sh --fast、naming_check、typephp、vitest、tsc --noEmit）、剩余项（写入资源端点、后台任务化 #19、各资源逐个登记）。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===

补充：任务目录是 ~/agent-work/assistant/table-io-1119/，RESULT.md 写在这里。
```

模型：SWE-2。文件冲突：改契约和生成产物，与 1c（M-05，也会重新生成产物）冲突，排在 1c 合并后；前端与 2-I 同改 routes 和 i18n，错开。

### 2-H 中间件链生产守卫

```
【任务】1.x 中间件链生产守卫（AUTH-03）断言器与接入点，无库

依据：v4 第 626 行「中间件链生产守卫（AUTH-03）：启动断言 + 装/升插件后必跑，不通过拒绝启动」1–2 人天；鉴权P0-v4.md §E 回归行 R45（v4 拆为 CI 与生产启动守卫）。未验收。

输入（Mac 路径）：~/agent-work/grokbot/review-1117-r3/顶层架构设计-v4.md（第 626 行、K-6 节第 1671 行起）；同目录 鉴权P0-v4.md（A-6、WP6、R45、R36–R38）；~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md；supdger/sandadmin main 的 server/app/bootstrap、server/config/bootstrap.php、server/config/middleware.php、server/src/Contract、server/src/Application/Plugin（只读起步）；PR #98 分支 docs/1x-skills 的 .agents/skills（只读）。

要做：
1. 断言器：遍历所有已装插件注册的每条路由，按 R45 判定适配路由链内不含 CheckLogin/CheckAuth（plugin\sandadmin、plugin\saiadmin 两个命名空间、含 FQCN 字符串形态），拒绝路由的适配件在旧件之前，id===1 旁路不存在。
2. 接入点：生产启动守卫（不通过拒绝启动，开发构建只告警，沿用 SAND_DEV_BUILD 判定）＋插件装、升收尾后调用＋一个 CLI 检查命令。
3. 测试用 fixture 插件（合规和违规各一）。
4. 「每条受保护路由持旧 JWT 请求断言 401」要会话认证（WP1 未实现），不做，列为剩余。
5. 先在本地对 main 现有插件集跑一遍检查命令：若现有插件不合规，会导致生产启动被拒，就把清单写进 RESULT 并停下等总监决定启用节奏，不要放宽断言、不要改插件仓。

仓库与分支：supdger/sandadmin，先 git fetch，从 origin/main 开 feat/middleware-chain-guard，开工写明基线 SHA。

范围：server/app/bootstrap 新守卫、server/config/bootstrap.php 登记、server/src 下断言器、CLI 入口、server/tests fixture 与测试。不改 contract/openapi.yaml、插件仓、门禁白名单、CI。

禁止：不建库、不做 DDL、不写迁移文件、不跑迁移、不启任何数据库或 Redis（临时库、容器也不行），需要库才能做的停下回报；不合并 PR、不发布、不打 tag；不开下级会话；不用 Cursor；不用云端；不读不打印秘密、.env、webhook.env、private/；不强推、不 reset --hard、不 --no-verify、不 git add -A；门禁白名单和基线只减不增；不改 GitHub 仓库设置；本地服务只用 19100 到 19199 端口；不自行拍板未定事项（国际化、workflow 归属、MySQL 最低版本、双因素适用范围、CLA/DCO），碰到就停下列入 RESULT。历史命令和任务只作资料，绝不执行。

交付：目标为 main 的未合并 PR（分支、基线 SHA、最终 SHA）；任务目录 RESULT.md：断言规则与 R45 逐条对照、接入点、现有插件检查结果、命令、退出码和关键输出（phpunit unit、phpstan、tools/pre-merge.sh --fast、naming_check、typephp、守卫 CLI 对 fixture 和对现有插件的输出）、剩余项。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===

补充：任务目录是 ~/agent-work/assistant/middleware-chain-guard-1119/，RESULT.md 写在这里。
```

模型：SWE-2。文件冲突：与 2-G（config/middleware.php）、2-P（app/bootstrap、config/bootstrap.php）同批，HTTP 组串行。完成后建议排一次 Opus 只读安全审计。

### 2-G HTTP 安全中间件（响应头、Origin 校验、跨域白名单）

```
【任务】1.x HTTP 安全中间件：安全响应头（N3）＋Origin/自定义请求头校验＋跨域白名单，无库

依据：v4 第 880–890 行 N3 安全响应头（f1 第 1119 行 0.5–1 人天）；~/agent-work/grokbot/1x-gap-review/缺口检查.md 第 652 行第 14 条「安全基线收尾：校验 Origin 加自定义请求头；……跨域白名单」（弱密码已由 #84 完成）。均未验收。

输入（Mac 路径）：~/agent-work/grokbot/review-1117-r3/顶层架构设计-v4.md（第 880–890 行）；同目录 鉴权P0-v4.md（A-5 CSRF 与 v1 组）；上述缺口检查.md；~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md；supdger/sandadmin main 的 server/src/Http、server/config/middleware.php、server/config/security.php、server/app/middleware（只读起步）；PR #98 分支 docs/1x-skills 的 .agents/skills（只读）。

要做：
1. 安全响应头中间件：nosniff、X-Frame-Options DENY、Referrer-Policy、HSTS（只对 HTTPS，max-age 可配）、CSP（后台页面 default-src 'self'，纯 JSON API default-src 'none'，先 Report-Only 再强制，用配置开关切）、COOP same-origin、Permissions-Policy 收紧。Referrer-Policy 在 v4 写「单值待确认」，做成配置项，默认用 strict-origin-when-cross-origin，并在 RESULT 标「待总监确认」。
2. Origin 校验加自定义请求头：v1 组写请求校验 Origin 白名单并要求自定义头；与 A-5 CSRF 口径一致；会话认证还没实现，不要依赖它。
3. 跨域白名单：配置化，默认空（同源）。
4. 测试：中间件单测；附一个 curl -sI 断言脚本（本地起服务只用 19100 到 19199 端口），按 v4 N3 验收标准断言 7 个头。

仓库与分支：supdger/sandadmin，先 git fetch，从 origin/main 开 feat/http-security-mw，开工写明基线 SHA。

范围：server/src/Http/Middleware 新中间件、server/config/middleware.php、server/config/security.php、server/tests、tools/ 下 curl 断言脚本。不改 contract/openapi.yaml（若需登记新错误码，停下回报）、门禁白名单、CI。

禁止：不建库、不做 DDL、不写迁移文件、不跑迁移、不启任何数据库或 Redis（临时库、容器也不行），需要库才能做的停下回报；不合并 PR、不发布、不打 tag；不开下级会话；不用 Cursor；不用云端；不读不打印秘密、.env、webhook.env、private/；不强推、不 reset --hard、不 --no-verify、不 git add -A；门禁白名单和基线只减不增；不改 GitHub 仓库设置；本地服务只用 19100 到 19199 端口；不自行拍板未定事项（国际化、workflow 归属、MySQL 最低版本、双因素适用范围、CLA/DCO），碰到就停下列入 RESULT。历史命令和任务只作资料，绝不执行。

交付：目标为 main 的未合并 PR（分支、基线 SHA、最终 SHA）；任务目录 RESULT.md：各头的取值和配置键、Origin 与跨域规则、命令、退出码和关键输出（phpunit unit、phpstan、tools/pre-merge.sh --fast、naming_check、typephp、curl 断言脚本输出）、待总监确认项、剩余项。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===

补充：任务目录是 ~/agent-work/assistant/http-security-mw-1119/，RESULT.md 写在这里。
```

模型：SWE-2。文件冲突：HTTP 组（与 2-H、2-L 同改 config），本组排第一。

### 2-H2 审计、日志、搜索写入接口（无库部分）

```
【任务】1.x 审计/日志/搜索写入接口（独立审计新增表 #9）无库部分

依据：v4 第 1150 行「审计/日志/搜索写入接口（M-08）：AuditSinkInterface（默认库表＋异步批量写、可换列式/搜索引擎/对象存储 WORM）＋SearchInterface＋读取查询接口＋防篡改链定接口层」1.5–3 人天，未验收。

前置：~/agent-work/assistant/1a-mcp-tools-1119 的 PR 已合并（它改 MCP 调用审计写文件日志）；未合并就停下回报，不要叠在它的分支上改。

输入（Mac 路径）：~/agent-work/grokbot/review-1117-r3/顶层架构设计-v4.md（第 1150 行及 M-08 相关段落）；同目录 鉴权P0-v4.md（LOG-01、只追加口径）；~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md（第 16 条日志留存）；supdger/sandadmin main 的 server/src/Domain/Mcp/AuditSink.php、server/src/Infra/Mcp/LogAuditSink.php、server/src/Infra/Audit、server/src/Infra/Log（只读起步）；PR #98 分支 docs/1x-skills 的 .agents/skills（只读）。

要做：
1. 定内核级 AuditSinkInterface、SearchInterface、读取查询接口、防篡改链接口（逐条哈希链，接口层加纯内存或文件参考实现与校验器）。
2. 异步批量写缓冲（进程内队列加定时冲刷，Workerman 定时器），带上限与丢弃告警策略，单测。
3. 文件日志实现（复用现有日志通道与留存规则）；把 MCP 现有 AuditSink 改为适配到内核接口，行为不变。
4. 不做：默认库表实现（要表）、列式、搜索引擎、WORM 实现，列为剩余。

仓库与分支：supdger/sandadmin，先 git fetch，从 origin/main 开 feat/audit-sink，开工写明基线 SHA。

范围：server/src/Domain/Audit、server/src/Infra/Audit、MCP 审计适配、server/tests。不改 contract/openapi.yaml、门禁白名单、CI。

禁止：不建库、不做 DDL、不写迁移文件、不跑迁移、不启任何数据库或 Redis（临时库、容器也不行），需要库才能做的停下回报；不合并 PR、不发布、不打 tag；不开下级会话；不用 Cursor；不用云端；不读不打印秘密、.env、webhook.env、private/；不强推、不 reset --hard、不 --no-verify、不 git add -A；门禁白名单和基线只减不增；不改 GitHub 仓库设置；本地服务只用 19100 到 19199 端口；不自行拍板未定事项（国际化、workflow 归属、MySQL 最低版本、双因素适用范围、CLA/DCO），碰到就停下列入 RESULT。历史命令和任务只作资料，绝不执行。

交付：目标为 main 的未合并 PR（分支、基线 SHA、最终 SHA）；任务目录 RESULT.md：接口清单、批量与防篡改设计、MCP 适配前后行为对比、命令、退出码和关键输出（phpunit unit、phpstan、tools/pre-merge.sh --fast、naming_check、typephp）、剩余项。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===

补充：任务目录是 ~/agent-work/assistant/audit-sink-1119/，RESULT.md 写在这里。
```

模型：SWE-2。文件冲突：与 1a（正在跑）同改 MCP 审计，前置 1a 合并。

### 2-I artd 残留宿主页面搬迁到 web/

```
【任务】1.x artd 残留宿主页面盘点并搬到 web/（前端，无库）

依据：v4 第 575 行「artd 残留页面搬迁：只含宿主自有页面残留，插件页面不在内；量未核实」0.5–2 人天，未验收；v4 第 421–430 行必改 6「web/ 是唯一前端，sandadmin-artd/ 标退役」。

输入（Mac 路径）：~/agent-work/grokbot/review-1117-r3/顶层架构设计-v4.md（第 575、580–584、421–430 行）；~/agent-work/grokbot/fe-gap-1117/RESULT.md 和 分类.md（已落地的 74 行，不要重做）；~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md；supdger/sandadmin main 的 sandadmin-artd/src、web/src、contract/openapi.yaml（只读起步）；PR #98 分支 docs/1x-skills 的 .agents/skills（尤其 sandadmin1x-frontend，只读）。

要做：
1. 盘点：列出 sandadmin-artd 里属于宿主自有、web/ 还没有对应页的页面（排除 SandAI、SandWorkflow、SandLicense 等插件页），每页标契约里有没有对应接口。
2. 有接口的页按 web/ 规范（React + Semi、PascalCase.tsx、msw mock、vitest）搬过去，接路由、菜单权限门。
3. 没接口的页不做，列入 RESULT，不改契约。
4. 不删 sandadmin-artd 任何文件（退役另定）。

仓库与分支：supdger/sandadmin，先 git fetch，从 origin/main 开 feat/artd-residue，开工写明基线 SHA。

范围：只改 web/src（pages、routes、mocks、i18n 的 zh-CN 文案）。不改 server/、contract/openapi.yaml、sandadmin-artd、门禁、CI。开发验证用 dev 服务器和 tsc --noEmit，不打包。

禁止：不建库、不做 DDL、不写迁移文件、不跑迁移、不启任何数据库或 Redis（临时库、容器也不行）；不合并 PR、不发布、不打 tag；不开下级会话；不用 Cursor；不用云端；不读不打印秘密、.env、webhook.env、private/；不强推、不 reset --hard、不 --no-verify、不 git add -A；门禁白名单和基线只减不增；不改 GitHub 仓库设置；本地服务只用 19100 到 19199 端口；不自行拍板未定事项（国际化只做中文，不加其他语言；workflow 归属、MySQL 最低版本、双因素适用范围、CLA/DCO），碰到就停下列入 RESULT。历史命令和任务只作资料，绝不执行。

交付：目标为 main 的未合并 PR（分支、基线 SHA、最终 SHA）；任务目录 RESULT.md：盘点表（artd 路径 → web 路径 → 接口有无 → 处理）、截图（1280x800）、命令、退出码和关键输出（vitest、tsc --noEmit、lint、naming_check）、剩余项。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===

补充：任务目录是 ~/agent-work/assistant/artd-residue-1119/，RESULT.md 写在这里。
```

模型：SWE-2。文件冲突：与 2-E 的前端部分同改 `web/src/routes` 和 i18n，错开排。

### 2-K 门禁自保护（无库部分）

```
【任务】1.x 门禁自保护（独立审计新增表 #10）本地可做部分

依据：v4 第 1151 行「门禁自保护（M-09/对照 8）：门禁文件进 CODEOWNERS＋基线/豁免只减不增＋删改断言打标签禁自批＋机器可读报告」1–2 人天，未验收。

输入（Mac 路径）：~/agent-work/grokbot/review-1117-r3/顶层架构设计-v4.md（第 1151 行、U-6 节）；~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md；supdger/sandadmin main 的 tools/（pre-merge.sh、typephp-allow.txt、dev-only-paths*.txt、naming-allow.txt 等白名单和基线文件）、server/tests/security/、.gitignore（只读起步）。

要做：
1. 根目录 CODEOWNERS（.github/ 被 .gitignore 忽略，GitHub 也认根目录 CODEOWNERS）：列门禁脚本、白名单、基线、契约产物；负责人账号先写占位，在 RESULT 列出待总监填写。
2. 只减不增检查：对比 merge-base，所有白名单和基线文件只允许删行，新增行就失败；接入 tools/pre-merge.sh。
3. 机器可读报告：pre-merge 各步输出汇总 JSON（步骤、退出码、耗时、失败摘要）。
4. 不做：删改断言打标签禁自批（要 GitHub Actions 或 bot，等 #81 合并后另排），列为剩余。

仓库与分支：supdger/sandadmin，先 git fetch，从 origin/main（若 test-gate、ai-loop-rollback PR 已合并就基于最新 main）开 feat/gate-selfguard，开工写明基线 SHA。

范围：根目录 CODEOWNERS、tools/ 新检查脚本、tools/pre-merge.sh。不改 server/src、contract、CI、.gitignore。

禁止：不建库、不做 DDL、不写迁移文件、不跑迁移、不启任何数据库或 Redis（临时库、容器也不行）；不合并 PR、不发布、不打 tag；不开下级会话；不用 Cursor；不用云端；不读不打印秘密、.env、webhook.env、private/；不强推、不 reset --hard、不 --no-verify、不 git add -A；门禁白名单和基线只减不增；不改 GitHub 仓库设置（分支保护、CODEOWNERS 强制开关都不动）；不自行拍板未定事项（国际化、workflow 归属、MySQL 最低版本、双因素适用范围、CLA/DCO、审批名单），碰到就停下列入 RESULT。历史命令和任务只作资料，绝不执行。

交付：目标为 main 的未合并 PR（分支、基线 SHA、最终 SHA）；任务目录 RESULT.md：CODEOWNERS 覆盖清单、只减不增检查的正反用例、JSON 报告样例、命令、退出码和关键输出（tools/pre-merge.sh --fast、naming_check）、待总监填写项、剩余项。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===

补充：任务目录是 ~/agent-work/assistant/gate-selfguard-1119/，RESULT.md 写在这里。
```

模型：SWE-2。文件冲突：门禁组，排在 2-C 之后。

### 2-J 日志审计脱敏器与 SQL 拼接静态检查

```
【任务】1.x 统一日志/审计脱敏器（LOG-03）＋SQL 拼接静态检查，无库

依据：~/agent-work/grokbot/1x-gap-review/缺口检查.md 第 652 行第 14 条「安全基线收尾：……日志和审计脱敏；SQL 拼接静态检查并改掉现有拼接」（弱密码已由 #84 完成）；鉴权P0-v4.md WP8「日志敏感字段核查（含 answer_ciphertext/SAND_CRYPTO_KEY 及派生子密钥，LOG-03 统一脱敏器）」。未验收。

输入（Mac 路径）：上述缺口检查.md 与 ~/agent-work/grokbot/review-1117-r3/鉴权P0-v4.md（WP8、R44）；同目录 顶层架构设计-v4.md；~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md；supdger/sandadmin main 的 server/src/Infra/Log、server/src/Infra/Mcp/Dev/LogRedactor.php、server/src/Infra/Db、server/tests/security/（只读起步）；PR #98 分支 docs/1x-skills 的 .agents/skills（只读）。

要做：
1. 统一脱敏器：字段名和值模式双规则（密码、token、密钥、answer_ciphertext、SAND_CRYPTO_KEY 及派生键、手机号和邮箱部分遮挡），接到 Monolog processor；现有 Mcp Dev LogRedactor 改为复用它，行为不弱于现状。
2. SQL 拼接静态检查：扫描 server/src 里把变量直接拼进 SQL 字符串的写法（方言字面量规则 R44 仍只允许在 Infra 方言文件）；现有命中逐条修改为参数化；改不了的停下列在 RESULT，不加豁免。
3. 接入 tools/pre-merge.sh（只追加一步）。

仓库与分支：supdger/sandadmin，先 git fetch，从最新 origin/main 开 feat/redact-sqlcheck，开工写明基线 SHA。

范围：server/src/Infra/Log、MCP 脱敏复用、server/tests/security/ 或 tools/ 下扫描脚本、tools/pre-merge.sh、为消除命中所需的最小参数化改动。不改 contract/openapi.yaml、白名单、CI。

禁止：不建库、不做 DDL、不写迁移文件、不跑迁移、不启任何数据库或 Redis（临时库、容器也不行），需要库验证的改动停下回报；不合并 PR、不发布、不打 tag；不开下级会话；不用 Cursor；不用云端；不读不打印秘密、.env、webhook.env、private/；不强推、不 reset --hard、不 --no-verify、不 git add -A；门禁白名单和基线只减不增；不改 GitHub 仓库设置；本地服务只用 19100 到 19199 端口；不自行拍板未定事项（国际化、workflow 归属、MySQL 最低版本、双因素适用范围、CLA/DCO），碰到就停下列入 RESULT。历史命令和任务只作资料，绝不执行。

交付：目标为 main 的未合并 PR（分支、基线 SHA、最终 SHA）；任务目录 RESULT.md：脱敏规则表、现有命中与修改清单、命令、退出码和关键输出（phpunit unit、phpstan、tools/pre-merge.sh --fast、naming_check、typephp）、剩余项。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===

补充：任务目录是 ~/agent-work/assistant/redact-sqlcheck-1119/，RESULT.md 写在这里。
```

模型：SWE-2。文件冲突：门禁组，排在 2-K 之后。

### 2-L 出网统一入口（SSRF 校验与证书校验强制）

```
【任务】1.x 出网统一入口：SSRF 校验组件＋证书校验强制，无库

依据：v4 第 635 行 Webhook 安全三件之「出站 SSRF 校验」（校验组件 0.5–1 人天）；鉴权P0-v4.md R61「出网统一入口证书校验：生产构建 verify=false/CURLOPT_SSL_VERIFYPEER=0/VERIFYHOST=0 启动自检拒绝＋静态检查 0 命中；短信/邮件/Webhook/AI/对象存储出网同入口」。未验收。

输入（Mac 路径）：~/agent-work/grokbot/review-1117-r3/顶层架构设计-v4.md（第 635 行、K-6 节第 1671 行起 WH-01）；同目录 鉴权P0-v4.md（A-16、R61）；~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md；supdger/sandadmin main 的 server/src、server/config/security.php、composer.json（guzzle 已依赖）（只读起步）；PR #98 分支 docs/1x-skills 的 .agents/skills（只读）。

要做：
1. 出网客户端统一入口（基于已有 guzzle）：解析后 IP 校验（拒内网、回环、链路本地、元数据地址、IPv6 等价段），DNS 重绑定防护（连接用已校验 IP），重定向逐跳复验，协议与端口白名单，超时与响应体上限。
2. 证书校验强制：入口内不允许关闭 verify；静态检查 server/src 里 verify=false、CURLOPT_SSL_VERIFYPEER=0、VERIFYHOST=0 零命中；生产启动自检。内部 CA 只允许追加信任。
3. 单测（用本地 mock handler，不访问外网）。
4. 不做：Webhook 端点密钥加密轮换、按端点熔断（要表），列为剩余。

仓库与分支：supdger/sandadmin，先 git fetch，从最新 origin/main 开 feat/outbound-guard，开工写明基线 SHA。

范围：server/src 新增出网入口、server/config/security.php 新键、静态检查脚本（接入 pre-merge 只追加一步）、server/tests。不改 contract/openapi.yaml、白名单、CI。

禁止：不建库、不做 DDL、不写迁移文件、不跑迁移、不启任何数据库或 Redis（临时库、容器也不行）；不合并 PR、不发布、不打 tag；不开下级会话；不用 Cursor；不用云端，测试不访问外网；不读不打印秘密、.env、webhook.env、private/；不强推、不 reset --hard、不 --no-verify、不 git add -A；门禁白名单和基线只减不增；不改 GitHub 仓库设置；本地服务只用 19100 到 19199 端口；不自行拍板未定事项（国际化、workflow 归属、MySQL 最低版本、双因素适用范围、CLA/DCO），碰到就停下列入 RESULT。历史命令和任务只作资料，绝不执行。

交付：目标为 main 的未合并 PR（分支、基线 SHA、最终 SHA）；任务目录 RESULT.md：拒绝规则表、正反用例、命令、退出码和关键输出（phpunit unit、phpstan、tools/pre-merge.sh --fast、naming_check、typephp、静态检查输出）、剩余项。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===

补充：任务目录是 ~/agent-work/assistant/outbound-guard-1119/，RESULT.md 写在这里。
```

模型：SWE-2。文件冲突：与 2-G 同改 `config/security.php`，HTTP 组排在 2-G 之后；也会追加 pre-merge 一步，和门禁组错开。完成后建议排一次 Opus 只读安全审计。

### 2-M TypePHP CI 门禁 job

```
【任务】1.x TypePHP 兼容门禁进 GitHub Actions（T-8 剩余）

依据：v4 第 651 行 T-8「门禁 job 与本地脚本 0.5–1 + 编译 job 0.5–1」；~/agent-work/grokbot/typephp-1117/RESULT.md 已验收本地脚本与 --typephp 开关，遗留「CI 门禁 job 未做」。v4 第 670 行注①：门禁 job 直接加进 sandadmin 现有 GitHub Actions，不另搭平台。

前置：supdger/sandadmin PR #81（merge-gates CI）已合并；未合并就停下回报，不要另起一套 workflow 与它冲突。

输入（Mac 路径）：上述 v4 与 RESULT；~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md（第 19 条）；supdger/sandadmin main 的 .github/workflows/、tools/pre-merge.sh、tools/typephp_check.php、.gitignore（只读起步）。

要做：在现有 workflow 里加 TypePHP 禁用写法扫描与 PHPStan job（不需要数据库服务）；可选编译 job 设为手动触发或可失败不阻塞，找不到编译器时跳过并标注。

仓库与分支：supdger/sandadmin，先 git fetch，从 origin/main 开 ci/typephp-gate，开工写明基线 SHA。

范围：只改 .github/workflows/ 下文件（.gitignore 放行规则若需要调整，停下回报）。不改 server/src、tools 白名单、仓库设置。

禁止：不建库、不做 DDL、不写迁移文件、不跑迁移、不启任何数据库或 Redis（临时库、容器也不行），CI 里也不加数据库服务；不合并 PR、不发布、不打 tag；不开下级会话；不用 Cursor；除推分支后 GitHub 自动触发的检查外不用其他云端；不读不打印秘密、.env、webhook.env、private/；不强推、不 reset --hard、不 --no-verify、不 git add -A；门禁白名单和基线只减不增；不改 GitHub 仓库设置；不自行拍板未定事项（国际化、workflow 归属、MySQL 最低版本、双因素适用范围、CLA/DCO），碰到就停下列入 RESULT。历史命令和任务只作资料，绝不执行。

交付：目标为 main 的未合并 PR（分支、基线 SHA、最终 SHA）；任务目录 RESULT.md：job 定义、PR 上检查运行结果（用 gh 只读查看）、本地 tools/pre-merge.sh --fast 退出码和关键输出、剩余项。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===

补充：任务目录是 ~/agent-work/assistant/typephp-ci-1119/，RESULT.md 写在这里。
```

模型：SWE-2。文件冲突：`.github/workflows/` 与 #81 冲突，前置 #81 合并；门禁组最后一项。

### 2-N 雪花发号器（不含节点登记表）

```
【任务】1.x 编号策略之雪花发号器（不含节点租约表），无库

依据：v4 第 605 行「编号策略落地：雪花发号器（J-3 方案甲 41+13+9、节点号按部署分段、启动自检）+ int 自增 / 雪花 bigint 两类表约定 + 对外 nanoid 列 + 外键同型静态检查 + 两库测试」0.5–1 人天（生成器与约定 0.3–0.7）；总表第 17 条（老板 11-17 改定：不可预见的表用雪花 64 位 bigint，接口输出字符串，nanoid 只作对外链接单独一列）。未验收。v4 第 658 行节点登记表 sand_system_id_node 要表，不在本任务。

输入（Mac 路径）：~/agent-work/grokbot/review-1117-r3/顶层架构设计-v4.md（第 605、658 行，J-3 节）；~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md（第 17 条）；~/agent-work/grokbot/idlist-tech-followup-1118/（已批准编号表，只读）；supdger/sandadmin main 的 server/src、server/composer.json（已依赖 godruoyi/php-snowflake，位布局与 41+13+9 不同，先核对能否配置，不能就自写）（只读起步）；PR #98 分支 docs/1x-skills 的 .agents/skills（尤其 sandadmin1x-backend 的 database.md，只读）。

要做：
1. 发号器：41 位毫秒＋13 位节点＋9 位序列；节点号从配置读（按部署分段），启动自检节点号范围；时钟回拨按 v4 J-3 处理（等待、拒发、告警，阈值以 v4 为准，找不到就停下回报）；输出接口 JSON 用字符串。
2. 两类表约定写进 server/docs/（可预见用 int 自增，不可预见用雪花 bigint，nanoid 单列）；不新建表、不改任何现有表。
3. 单测：单进程并发唯一、序列溢出等下一毫秒、回拨各分支、节点越界拒启。
4. 不做：节点租约表、心跳续期、外键同型静态检查（要读库结构）、两库测试，列为剩余。

仓库与分支：supdger/sandadmin，先 git fetch，从 origin/main 开 feat/id-generator，开工写明基线 SHA。

范围：server/src 新增发号器、config 项、server/tests/Unit、server/docs/ 一份约定。不改 contract/openapi.yaml、现有插入代码、门禁、CI。

禁止：不建库、不做 DDL、不写迁移文件、不跑迁移、不启任何数据库或 Redis（临时库、容器也不行），需要库才能做的停下回报；不合并 PR、不发布、不打 tag；不开下级会话；不用 Cursor；不用云端；不读不打印秘密、.env、webhook.env、private/；不强推、不 reset --hard、不 --no-verify、不 git add -A；门禁白名单和基线只减不增；不改 GitHub 仓库设置；本地服务只用 19100 到 19199 端口；不自行拍板未定事项（国际化、workflow 归属、MySQL 最低版本、双因素适用范围、CLA/DCO），碰到就停下列入 RESULT。历史命令和任务只作资料，绝不执行。

交付：目标为 main 的未合并 PR（分支、基线 SHA、最终 SHA）；任务目录 RESULT.md：位布局与回拨处理依据（附 v4 行号）、命令、退出码和关键输出（phpunit unit、phpstan、tools/pre-merge.sh --fast、naming_check、typephp）、剩余项。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===

补充：任务目录是 ~/agent-work/assistant/id-generator-1119/，RESULT.md 写在这里。
```

模型：SWE-2。文件冲突：无。

### 2-O AGENTS.md 按真实结构改写

```
【任务】1.x 根目录 AGENTS.md 按 D 节必改 6 改写（只改文档）

依据：v4 第 576 行「AGENTS.md/文档改写：按 D-6 清单」0.5 人天；v4 第 421–430 行必改 6 改写清单；v4 第 2160 行 U-2「AGENTS.md 按必改 6 改写，并写入本章流程和 T-8 硬规矩」。未验收（main 的 AGENTS.md 仍写「核心业务不在本仓、本仓无 migrations、无 CI」）。

前置：supdger/sandadmin PR #98（docs/1x-skills，也改 AGENTS.md）已合并；未合并就停下回报。

输入（Mac 路径）：上述 v4；~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md；supdger/sandadmin main 的 AGENTS.md、目录结构、tools/、.agents/skills（只读起步）。

要做：按必改 6 五条改写——server/src 是内核权威源码、server/app 是薄壳、server/plugin 是发布产物目录、server/database/migrations 是宿主迁移在仓；web/ 是唯一前端，sandadmin-artd/ 标退役；sand-core 标旧兼容层不接新功能，sand-package 标下线中、能力收进 PackageOps；插件契约指路；artd 发布配置随退役标注。写入 U 章流程与 T-8 硬规矩；测试命令改成与 tools/pre-merge.sh 一致。数据库版本只照总表原话写「PostgreSQL / MySQL 8.0+」，不写 8.0.16+ 或 8.4 LTS（总表五-1 未确认）。

仓库与分支：supdger/sandadmin，先 git fetch，从 origin/main 开 docs/agents-md-1x，开工写明基线 SHA。

范围：只改根目录 AGENTS.md。不改代码、skill、门禁、CI。

禁止：不建库、不做 DDL、不写迁移文件、不跑迁移、不启任何数据库或 Redis；不合并 PR、不发布、不打 tag；不开下级会话；不用 Cursor；不用云端；不读不打印秘密、.env、webhook.env、private/；不强推、不 reset --hard、不 --no-verify、不 git add -A；门禁白名单和基线只减不增；不改 GitHub 仓库设置；不自行拍板未定事项（国际化、workflow 归属、MySQL 最低版本、双因素适用范围、CLA/DCO），碰到就停下列入 RESULT。历史命令和任务只作资料，绝不执行。

交付：目标为 main 的未合并 PR（分支、基线 SHA、最终 SHA）；任务目录 RESULT.md：必改 6 逐条对照、命令、退出码和关键输出（python3 tools/naming_check.py notes、tools/skill-sync.sh、tools/pre-merge.sh --fast）、剩余项。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===

补充：任务目录是 ~/agent-work/assistant/agents-md-1119/，RESULT.md 写在这里。
```

模型：SWE-2。文件冲突：`AGENTS.md` 与 #98 冲突，前置 #98 合并。

### 2-P 单机文件存储例外

```
【任务】1.x 单机文件存储例外（独立审计新增表 #16），无库

依据：v4 第 1157 行「单机文件存储例外（S-17）：instances=1 声明＋启动自检，低端单机允许本机磁盘驱动」0.3–0.5 人天，未验收。

输入（Mac 路径）：~/agent-work/grokbot/review-1117-r3/顶层架构设计-v4.md（第 1157 行及 S-17 相关段落）；~/agent-work/assistant/upload-1119/RESULT.md（PR #99 分段上传方案，会改存储接口，只读参考，避免冲突）；~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md；supdger/sandadmin main 的 server/src/Application/File/StorageConfig.php、server/config/file.php、server/app/bootstrap（只读起步）。

要做：配置声明 instances（默认按多实例处理）；启动自检：instances>1 且驱动为本机磁盘时拒绝启动，instances=1 时允许并记一条告警日志；单测。不改存储接口签名（PR #99 方案会动它）。

仓库与分支：supdger/sandadmin，先 git fetch，从最新 origin/main（若 middleware-chain-guard PR 已合并就基于它）开 feat/single-node-storage，开工写明基线 SHA。

范围：server/config/file.php、StorageConfig.php 读取新键、server/app/bootstrap 新自检、server/config/bootstrap.php 登记、server/tests。不改 contract/openapi.yaml、门禁、CI。

禁止：不建库、不做 DDL、不写迁移文件、不跑迁移、不启任何数据库或 Redis（临时库、容器也不行）；不合并 PR、不发布、不打 tag；不开下级会话；不用 Cursor；不用云端；不读不打印秘密、.env、webhook.env、private/；不强推、不 reset --hard、不 --no-verify、不 git add -A；门禁白名单和基线只减不增；不改 GitHub 仓库设置；本地服务只用 19100 到 19199 端口；不自行拍板未定事项（国际化、workflow 归属、MySQL 最低版本、双因素适用范围、CLA/DCO），碰到就停下列入 RESULT。历史命令和任务只作资料，绝不执行。

交付：目标为 main 的未合并 PR（分支、基线 SHA、最终 SHA）；任务目录 RESULT.md：配置键、自检规则、命令、退出码和关键输出（phpunit unit、phpstan、tools/pre-merge.sh --fast、naming_check、typephp）、剩余项。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===

补充：任务目录是 ~/agent-work/assistant/single-node-storage-1119/，RESULT.md 写在这里。
```

模型：SWE-2。文件冲突：与 2-H 同改 `app/bootstrap` 和 `config/bootstrap.php`，HTTP 组最后一项；将来 T-7、B12 也会改 `StorageConfig.php`。

### 2-Q AI 上下文打包脚本

```
【任务】1.x AI 上下文打包脚本（独立审计新增表 #23），无库

依据：v4 第 1164 行「AI 上下文打包（对照 8 附带）：skill 给 OpenAPI/路由/权限摘要打包脚本」0.3–0.5 人天，未验收。

输入（Mac 路径）：~/agent-work/grokbot/review-1117-r3/顶层架构设计-v4.md（第 1164 行、U 章第 2144 行起）；~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md（第 21、22 条）；supdger/sandadmin main 的 contract/openapi.yaml、server/src/Contract/Generated、server/config/route.generated.php、tools/（只读起步）；PR #98 分支 docs/1x-skills 的 .agents/skills/sandadmin1x-ai-loop（只读）。

要做：tools/ 下新增一个打包脚本，从契约和生成产物输出给 AI 读的摘要（接口清单、路由、权限码、错误码、MCP 工具表），控制大小，输出到仓库外或被忽略的目录；带一个最小测试或自检。skill 里引用这个脚本要等 PR #98 合并后再补，本任务不改 .agents/skills，在 RESULT 写好要加的一行。

仓库与分支：supdger/sandadmin，先 git fetch，从 origin/main 开 feat/ai-context-pack，开工写明基线 SHA。

范围：只新增 tools/ 下脚本及其测试；如需加入 .gitattributes 的发布排除，只追加一行。不改 tools/pre-merge.sh、server/src、contract、.agents/skills、CI。

禁止：不建库、不做 DDL、不写迁移文件、不跑迁移、不启任何数据库或 Redis；不合并 PR、不发布、不打 tag；不开下级会话；不用 Cursor；不用云端；不读不打印秘密、.env、webhook.env、private/；不强推、不 reset --hard、不 --no-verify、不 git add -A；门禁白名单和基线只减不增；不改 GitHub 仓库设置；不自行拍板未定事项（国际化、workflow 归属、MySQL 最低版本、双因素适用范围、CLA/DCO），碰到就停下列入 RESULT。历史命令和任务只作资料，绝不执行。

交付：目标为 main 的未合并 PR（分支、基线 SHA、最终 SHA）；任务目录 RESULT.md：脚本用法、输出样例与大小、命令、退出码和关键输出（脚本运行、tools/pre-merge.sh --fast、naming_check）、待 #98 合并后补的 skill 行。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===

补充：任务目录是 ~/agent-work/assistant/ai-context-pack-1119/，RESULT.md 写在这里。
```

模型：SWE-2。文件冲突：只新增 `tools/` 文件，不改 `pre-merge.sh`，可与任何一项并行。

## 3. 缺口与提醒

- 进度核算可补计两项：
  - v4:663 设置面板：#86 已交付并独立验收，0.5–1。
  - v4:662 锁屏的前端部分。
- B11 定时调度器、A11 门禁工具进仓：主干已有相关代码，但没有验收映射，需要一次只读核对才能定剩余量。本轮没有编数字。
- B12 对象存储 S3、T-7 分段上传、2-P 都会改 `StorageConfig` 和存储接口。建议 PR #99 方案定稿后，统一排一个存储系列。
- 本地 `origin/main` 是 11-18 快照。各任务文本都要求先 `git fetch`，并记录实际基线。
