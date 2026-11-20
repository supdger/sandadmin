# AGENTS.md — SandAdmin

## 本仓是什么

SandAdmin 宿主：SaiAdmin 6.x 的独立 PostgreSQL fork，包含可运行的 Webman/Workerman 后端与 Vue3 管理端前端工程。

主要目录：

- `server/`：可运行 Webman 宿主——`app/middleware`、`app/process`、`config/`、`public/`、`support/`、`start.php`、`composer.json`、`.env.example`。
- `web/`：1.x 管理端前端工程（React 19 + Semi Design；规范见 `sandadmin1x-frontend`）。
- `sandadmin-artd/`：0.x 管理端前端工程（Vue3 + TS + Vite + pnpm，含 package.json、pnpm-lock；1.x 前端在 `web/`，本目录逐步退役——见 `sandadmin1x-frontend`）。
- `tests/`：宿主验证脚本（`http-error-data-test.php`）。
- `catalog.json`：插件目录；`RELEASE-CHECKLIST.md`：发布清单。

注意：核心业务后端与插件安装器不在本仓——由 `supdger/sand-core`、`supdger/sand-package` 经 Composer 安装到 `server/plugin/`（运行目录，非源码权威）。宿主迁移在 `server/database/migrations/`（0001–0003 基线 + 增量，增量带同名 `.down.sql` 回退文件），由统一迁移执行器 `server/bin/migrate.php` 按账本 `sand_system_schema_migration` 执行；新增插件表前缀 `sand_<域>_*`，历史 `sa_*` 表保留原名不改。

## 命名与注释规范

PHP ≥8.2（Webman/Workerman，PSR-12/PSR-4）+ Vue3/TS 前端。完整规范见 `/Users/supdger/agent-work/grokbot/devin-queue/命名与注释规范.md`，要点：

**数据库（PostgreSQL，小写蛇形）**

- 业务表前缀 `sand_<域>_*`（核心为 `sand_system_*`/`sand_tool_*`，插件用 `sand_<插件域>_*`），主键 `id`；列名主体 ≤3 词、≤30 字符。
- 布尔 `is_`/`has_`/`can_`/`must_` 或形容词；时间点 `*_time`、日期 `*_date`，固定三列 `create_time`/`update_time`/`delete_time`；数量带单位。
- `status` 表示启停（1 启用 / 2 停用），`state` 表示流转，两者分开；索引约束 `uk_`/`ix_`/`fk_`/`ck_` + 完整表名。

**接口与契约**

- 路径全小写连字符、资源用复数；JSON/查询参数小写蛇形、与列同名。
- 枚举小写蛇形（任务状态 `queued`/`running`/`succeeded`/`failed`/`cancelled`）；错误码大写蛇形；权限码 `域:资源:动作`。
- 迁移文件 `<序号>_<动词>_<对象>.sql`，增量迁移配同名 `<basename>.down.sql` 回退文件（基线 0001–0003 不带回退），已发布不改名、不改内容。

**代码**

- 类/组件/类型 PascalCase，函数/变量/属性 camelCase，常量大写蛇形；与列、接口字段一一对应的键保持蛇形原样。
- 函数/方法 ≤4 词且动词开头；不用 Manager/Helper/Util/Processor/Wrapper/Info/Data 收尾；不用 usr/pwd/cnt/msg/btn/tbl/mgr/svc/cfg/val/ret 等自造缩写；变量名不带类型后缀；布尔以 is/has/can/should 开头。
- 前端组件 `PascalCase.tsx`（一文件一主组件，文件名同组件名），hook `useXxx.ts`，测试 `同名.test.ts(x)`。

**注释**

- 只写代码看不出的原因、约束、单位、取值含义；禁逐行翻译、禁「此函数用于……」、禁流水账、禁横幅分隔线、禁表情、禁改动记录、禁注释掉的旧代码。
- 新文件注释用简体中文；TODO 写成 `TODO(负责人或#编号): 原因`。

**统一叫法**（同一概念全仓一个词）：`dept`、`file`、`create_time`/`update_time`/`delete_time`、`status`=启停、`state`=流转、`announcement`、`task`/`task_run`（后台任务）、`schedule`/`schedule_run`（定时任务）、`dict_type`、`audit_log`、`visibility`、`cancelled`（双写 l）、`client`（OAuth 接入方）。

交付前自查：`python3 /Users/supdger/agent-work/grokbot/devin-queue/naming_check.py sql|code|notes|terms ...`（用法见脚本开头）。

## 提交规矩

- 只 `git add` 具体文件，禁止 `git add -A` / `git add .`。
- 不提交 `.env`、密钥、token、`glm.key` 等敏感文件（`server/.env.example` 是模板可提交，真实 `.env` 不可）。
- 不 force push、不 `reset --hard`、不用 `--no-verify`；不合并自己的 PR。

## 测试和检查命令

- 后端：`cd server && composer install`；`composer run sand:publish`（`post-install-cmd`/`post-update-cmd` 自动执行 `php support/sand-publish.php` 发布核心前端源码）。
- 前端：`cd sandadmin-artd && pnpm dev` / `pnpm lint` / `pnpm build`（含 `vue-tsc --noEmit`）/ `pnpm lint:prettier` / `pnpm lint:stylelint`；Node ≥20.19、pnpm 固定 11.19.0。
- `php tests/http-error-data-test.php`（对前端源文件做静态契约断言）；
  `cd server && vendor/bin/phpunit`（B1 起引入 PHPUnit，`tests/Unit` 与 `tests/Integration` 分套件，
  集成测试需 `SAND_TEST_DB_URL` 指向 PostgreSQL）、`vendor/bin/phpstan analyse`。
- 无 CI；本地开发用 `pnpm dev`，不做多余构建。

## AI 开发必读 skill

动手改本仓代码前，先读 `.agents/skills/` 下随仓分发的四个 skill（上游权威副本在 `~/.shared-skills/`，本目录是同步副本）：

- `sandadmin1x-backend`：宿主后端（Webman/Workerman、`server/`）的写法与约束；
- `sandadmin1x-frontend`：管理端前端（Vue3/TS，`web/`、`sandadmin-artd/`）的写法与约束；
- `sandadmin1x-plugin`：插件开发约定；
- `sandadmin1x-ai-loop`：AI 参与开发的闭环流程。

老版 skills 到 1.x 规范的逐条对照、冲突与未拍板事项登记见 `.agents/skills/COVERAGE.md`。

读 delivery-loop、backend-structure、packaging、database 这几份同步 skill 时，以 `.agents/skills/LEGACY-ABSORBED.md` 对应小节为必守补充条款。

写、审 1.x 任何设计和方案时，另有一份本仓自有 skill `sandadmin1x-design-standard`（不在上面 skill-sync 比对的四个目录内）：所有设计和方案必须逐项回答 `.agents/skills/sandadmin1x-design-standard/` 清单，缺项的审计一律打回。

配套工具在 `tools/`（发布包不含，见 `.gitattributes`）：

- `tools/skill-sync.sh`：比对 `.agents/skills/` 与上游 `~/.shared-skills/` 是否一致；不一致时列出差异文件，`--pull` 用上游覆盖本仓副本后复核。
- `tools/naming_check.py` + `tools/naming-terms.tsv`：命名与注释规范的机械自查，用法见脚本开头注释（`terms` 子命令把词表路径作为第一个参数传入）。

## 许可证

MIT（根 LICENSE，含 saithink 双版权行；NOTICE 声明衍生自 SaiAdmin 6.x）。
