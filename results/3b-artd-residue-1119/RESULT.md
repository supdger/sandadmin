# artd 残留宿主页面盘点并搬到 web/ — RESULT

## 交付物

| 项 | 值 |
| --- | --- |
| 仓库 | `supdger/sandadmin`（本地 `~/agent-work/grokbot/fe-gap-1117/repo`） |
| 分支 | `feat/artd-residue` |
| 基线 SHA | `977427acc285da074a443e8206309289a9bd94fb`（origin/main） |
| 最终 SHA | `afdd56e7a2ce396373e51fd3dbadab79476bb595` |
| PR | https://github.com/supdger/sandadmin/pull/106（目标 `main`，**未合并**） |
| 范围 | 只改 `web/src`（pages/routes/mocks/zh-CN i18n）；未动 `server/`、`contract/openapi.yaml`、`sandadmin-artd/`、门禁、CI |

## 盘点表

口径：`sandadmin-artd/src/views` 全量。`plugin/sandpackage/` 属插件页（SandPackage 插件安装/系统更新面），按任务要求排除，不计入残留。

| artd 路径 | web/ 对应 | 契约接口 | 处理 |
| --- | --- | --- | --- |
| `auth/login` | `pages/auth/LoginPage.tsx`（`/login`） | `/api/auth/*`（v1 契约之外，前端已有） | 已存在，不搬 |
| `auth/change-password` | `pages/auth/ForceChangePasswordPage.tsx` | `/api/admin/v1/auth/password/change` | 已存在，不搬 |
| `auth/forget-password` | 无 | 契约无 | 不做 |
| `auth/register` | 无 | 契约无 | 不做 |
| `dashboard/console` | `pages/shell/WorkbenchPage.tsx`（`/`） | `/api/admin/v1/dashboard/*` | 已存在，不搬 |
| `dashboard/user-center` | `pages/personal/Profile|Notifications|Todos` | `/me/profile`、`/notifications`、`/dashboard/todos` | 已存在，不搬 |
| `exception/403|404|500` | `routes/modules/shell.tsx` 状态页 | — | 已存在，不搬 |
| `result/success|fail` | `pages/shell/ResultRoutePage.tsx` | — | 已存在，不搬 |
| `index/index.vue` | 路由默认跳转 `/` | — | 已由路由覆盖 |
| `outside/Iframe.vue` | 无 | — | 不搬（web 菜单外链直接打开，暂无内嵌 iframe 需求） |
| `safeguard/attachment` | 无 | 契约无 `/attachments` | 不做 |
| `safeguard/cache` | 无 | 契约无 | 不做 |
| `safeguard/database` | 无 | 契约无 | 不做 |
| `safeguard/dict` | `pages/data/DictPage.tsx`（`/data/dict`） | `/api/admin/v1/dicts` | 已存在，不搬 |
| `safeguard/email-log` | 无 | 契约无 | 不做 |
| `safeguard/login-log` | 无 | 契约无 | 不做 |
| `safeguard/oper-log` | 无 | 契约无 | 不做 |
| `safeguard/server`（服务器监控） | 无 | 契约无 | 不做 |
| `system/config` | 无 | 契约无 | 不做 |
| `system/dept` | `pages/org/depts/DeptsPage.tsx` | `/api/admin/v1/depts` | 已存在，不搬 |
| `system/menu` | `pages/rbac/menus/MenusPage.tsx` | `/api/admin/v1/menus` | 已存在，不搬 |
| `system/post` | `pages/org/posts/PostsPage.tsx` | `/api/admin/v1/posts` | 已存在，不搬 |
| `system/role` | `pages/rbac/roles/RolesPage.tsx` + `RolePermissionPage.tsx` | `/api/admin/v1/roles`、`/permission-tree` | 已存在，不搬 |
| `system/user` | `pages/org/users/UsersPage.tsx` | `/api/admin/v1/users` | 已存在，不搬 |
| `tool/code`（代码生成器） | 无 | 契约无 | 不做 |
| `tool/crontab`（定时任务 CRUD + 运行日志） | 运行面 → `pages/system/tasks/TasksPage.tsx`（`/system/tasks`） | 运行面：`/api/admin/v1/tasks`、`/tasks/{id}`、`/tasks/{id}/download`；crontab 列表/CRUD 契约无（仅 `PUT /scheduled-tasks/{code}` 覆盖写单条配置，无列表/详情） | **运行面本次迁移**；crontab CRUD 不做 |

结论：残留宿主页面中唯一有契约接口且 web/ 缺失的是 **后台任务运行面**（artd crontab 的 log-list 语义，即 `system:task:index` 对应的任务中心），已实现；其余页面要么已存在、要么契约无接口按规则不做。

## 本次实现

- `web/src/pages/system/tasks/TasksPage.tsx`：任务列表——类型模糊筛选、状态下拉（queued/running/succeeded/failed/cancelled）、「只看我发起的」、URL 查询态、分页、刷新、行内「详情/下载结果」
- `web/src/pages/system/tasks/TaskDetailDrawer.tsx`：详情抽屉，queued/running 时 2s 轮询
- `web/src/pages/system/tasks/TaskStateTag.tsx`：五态颜色标签（共用，避开 react-refresh 告警）
- `web/src/pages/system/tasks/TasksPage.test.tsx`：4 例——列表渲染、类型筛选、详情抽屉、成功任务结果下载（jsdom 补 `URL.createObjectURL` stub）
- `routes/paths.ts` + `routes/modules/system.tsx`：`/system/tasks`，`handle.permission = 'system:task:index'`
- `mocks/handlers/_v1data.ts`：菜单树「系统设置 → 任务中心」；补 running/queued/cancelled 三种状态种子（task-003~005，`taskSeq` 3→6 防撞号）；`adminMe.ts`/`auth.ts` 补 `system:task:index` 权限；`permissions.test.ts` 同步 viewer 期望
- `i18n/locales/zh-CN/tasks.ts`：中文文案

## 截图（1280x800，dev 服务器 + MSW，端口 19101）

- `shots/tasks-list.png`：任务中心列表——筛选区、五态标签、进度条、详情/下载结果按钮、菜单「任务中心」高亮
- `shots/tasks-detail.png`：详情抽屉——任务 ID/类型/状态/进度/阶段/时间/结果文件 + 下载结果按钮
- `shots/tasks-403.png`：guest token（无权限）直访 `/system/tasks` → 重定向 `/403`「没有权限」页

## 验证命令与退出码

| 命令 | 退出码 | 关键输出 |
| --- | --- | --- |
| `cd web && pnpm typecheck`（`tsc --noEmit`） | 0 | 无错误 |
| `cd web && pnpm lint`（`eslint .`） | 0 | 无错误无告警 |
| `cd web && pnpm vitest run` | 0 | `Test Files 125 passed (125)`、`Tests 534 passed | 2 skipped (536)` |
| `python3 naming_check.py code --diff /tmp/artd.diff` | 0 | 0 FAIL |
| `python3 naming_check.py notes --diff /tmp/artd.diff` | 0 | 0 FAIL；INFO：tsx 注释占比 16.7%（1 行说明性注释，达标内） |
| `VITE_ENABLE_MOCKS=true pnpm dev --port 19101` + Playwright 冒烟 | 0 | 列表/详情截图见上；`CONSOLE_ERRORS=[]`；guest 直访 → `/403` |

注：vitest 全程有 Node `localStorage` ExperimentalWarning 与 jsdom「navigation to another Document」提示，均为既有环境噪音，不影响判定。

## 剩余项

- artd `tool/crontab` 的定时任务**配置 CRUD**未迁移：契约无列表/详情接口（仅 `PUT /scheduled-tasks/{code}` 覆盖写单条配置，已被 `feMisc` 通用件消费）。如要做定时任务管理页，需先补契约——未定事项，待老板拍板。
- 附件、缓存、数据库维护、三类日志、服务器监控、系统配置、代码生成器、忘记密码/注册 等 artd 面在 1.x 契约均无接口，本次按规则不做；后续若补契约可照本次模式迁移。
- `outside/Iframe.vue` 内嵌外链容器未搬：web 菜单外链目前直接跳转；若产品要内嵌打开，另行决定。
- `sandadmin-artd/` 未删任何文件（退役另定）。
- PR #106 未合并，等待评审。
