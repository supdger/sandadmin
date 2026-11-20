# SandAdmin 1.x 接口盘点（U-6 第 1 步）

盘点时间：2026-11-17。盘点范围：`server/` 宿主内核 + `app/` 薄壳 + `config/`。
目标：把「路由、权限码、MCP 工具、错误码」现在到底在哪定义、是显式清单还是
运行期扫描摸清楚，为「以 openapi.yaml 为唯一契约来源、构建期生成」打底。

一句话现状：**四样东西目前都是散在代码里的手写内容，不是从契约文件派生
的；没有统一的注册表文件，也没有 openapi.yaml / api-conventions.md 实体
文件（代码里到处引用它，但仓里搜不到这份文档）。**

## 1. 结论速览

| 盘点项 | 现在在哪定义 | 显式清单还是运行期扫描 |
| --- | --- | --- |
| HTTP 路由 | `server/config/route.php` 手写 `Route::get/post/put/...`，共 105 条（100 常驻 + 5 开发构建专用） | **显式清单**；但插件路由是**运行期目录扫描**——`server/support/bootstrap.php` 末尾 `Util::scanDir(base_path().'/plugin')` 找每个插件的 `config` 目录交给 `Route::load` |
| 权限码 | 散在控制器/装配层的字符串字面量里（`run($request, 'system:xxx', ...)`、`requirePermission()`、类常量）；展示树在 `RoleService::MENUS` 硬编码数组；角色↔权限关系存 `sand_system_role_permission` 表 | **显式（字面量）**，但**没有统一注册表**，同一个码在「判定处」「展示树」「数据库」三处各写一遍；无运行期扫描收集 |
| MCP 工具 | `server/src/Infra 下 dev MCP 目录/dev 工具装配表.php` 手写 4 条 `ToolSpec`，装进 `Application/Mcp/ToolCatalog` | **显式清单**（代码注释自己写明「不是反射/注解扫描」）；但装配是每请求在 `dev MCP 控制器::handle()` 里现 new，仅开发构建存在 |
| 错误码 | 散在各模块异常类的工厂方法里（`OrgException`、`FileException`、`MenuException` 等）和 `ApiOutcome::fail(...)` 直接调用点 | **显式（字面量）**，没有错误码总表文件；各异常类用 `^[A-Z][A-Z0-9_]*$` 正则兜底形状，不查重、不登记 |

补充几个重要事实：

- `server/config/middleware.php` 返回 `[]`——**全局中间件链是空的**。
  `src/Http/Middleware/AuthMiddleware.php`、`ErrorMiddleware.php` 已经写好但
  **没有挂进任何配置**（只在 `server/tests/` 的测试桩 `HttpStack` 里用）。
  所以现在的鉴权不是中间件做的，是每个控制器自己在方法里
  `principal->resolve($request)` 解析身份 + 查权限码。
- 身份解析的生产实现是 `UnavailablePrincipalProvider`（恒返回 null → 一律
  401）；开发构建才有 `dev 身份主体`（dev 登录 令牌换超管身份）。也就是说
  **生产制品里这 100 条受保护路由目前全部返回 401**——统一会话底座（B2）
  还没合入，属于在建状态。
- 没有注解/attribute 路由：全仓 `src/`、`app/`、`config/` 里没有任何
  `#[Route]`、`#[Permission]` 之类的 attribute 用法。
- `config/exception.php` 只指到 `support\exception\Handler`（旧 `{code,msg}`
  壳），v1 信封是各控制器自己拼的，不走全局异常处理器。

## 2. 路由清单（全量 105 条）

全部出自 `server/config/route.php`；控制器在 `server/src/Http/`（命名空间
`Sandadmin\Http`）和 `server/app/controller/`（命名空间 `app\controller`）。
`Route::disableDefaultRoute()`（第 39 行）已关闭 Webman 默认路由。

### 2.1 文件（6 条，`app\controller\FileController`）

| 方法 | 路径 | 处理器 | 权限要求 |
| --- | --- | --- | --- |
| POST | /api/admin/v1/files | FileController::store | 登录即可 |
| GET | /api/admin/v1/files | FileController::index | system:file:index |
| GET | /api/admin/v1/files/{file_id} | FileController::show | system:file:index |
| DELETE | /api/admin/v1/files/{file_id} | FileController::destroy | system:file:delete |
| GET | /api/admin/v1/files/{file_id}/download | FileController::download | 登录即可（上传人或 system:file:index 才可下私有件） |
| POST | /api/admin/v1/files/cleanup | FileController::cleanup | system:file:cleanup |

### 2.2 后台任务（3 条，`Sandadmin\Http\TaskController`）

| 方法 | 路径 | 处理器 | 权限要求 |
| --- | --- | --- | --- |
| GET | /api/admin/v1/tasks | TaskController::index | 登录即可（viewAll=system:task:index 看全部，否则只看本人） |
| GET | /api/admin/v1/tasks/{task_id} | TaskController::show | 同上 |
| GET | /api/admin/v1/tasks/{task_id}/download | TaskController::download | 同上 |

### 2.3 公告（10 条，`Sandadmin\Http\AnnouncementController` → `AnnouncementHttp`）

| 方法 | 路径 | 处理器 | 权限码 |
| --- | --- | --- | --- |
| GET | /api/admin/v1/me/announcements/popup | AnnouncementController::popup | 登录即可 |
| GET | /api/admin/v1/announcements | AnnouncementController::index | system:announcement:index |
| POST | /api/admin/v1/announcements | AnnouncementController::store | system:announcement:create |
| GET | /api/admin/v1/announcements/{announcement_id} | AnnouncementController::show | system:announcement:index |
| PUT | /api/admin/v1/announcements/{announcement_id} | AnnouncementController::update | system:announcement:update |
| DELETE | /api/admin/v1/announcements/{announcement_id} | AnnouncementController::destroy | system:announcement:delete |
| POST | /api/admin/v1/announcements/{announcement_id}/publish | AnnouncementController::publish | system:announcement:publish |
| POST | /api/admin/v1/announcements/{announcement_id}/revoke | AnnouncementController::revoke | system:announcement:revoke |
| POST | /api/admin/v1/announcements/{announcement_id}/read | AnnouncementController::markRead | 登录即可 |
| GET | /api/admin/v1/announcements/{announcement_id}/read-stats | AnnouncementController::readStats | system:announcement:index |

### 2.4 消息中心（4 条，`Sandadmin\Http\NotificationController` → `NotificationHttp`）

| 方法 | 路径 | 处理器 | 权限要求 |
| --- | --- | --- | --- |
| GET | /api/admin/v1/notifications/unread-count | NotificationController::unreadCount | 登录即可 |
| POST | /api/admin/v1/notifications/read-all | NotificationController::markAllRead | 登录即可 |
| GET | /api/admin/v1/notifications | NotificationController::index | 登录即可 |
| POST | /api/admin/v1/notifications/{notification_id}/read | NotificationController::markRead | 登录即可 |

### 2.5 生命周期申请（1 条，`Sandadmin\Http\LifecycleController`）

| 方法 | 路径 | 处理器 | 权限要求 |
| --- | --- | --- | --- |
| GET | /api/admin/v1/lifecycle-requests/{request_id} | LifecycleController::show | 登录即可（本人或持 system:plugin:index / system:update:index 看全部） |

### 2.6 当前登录人（6 条，`Sandadmin\Http\MeController`）

| 方法 | 路径 | 处理器 | 权限要求 |
| --- | --- | --- | --- |
| GET | /api/admin/v1/auth/password-policy | MeController::passwordPolicy | 登录即可 |
| POST | /api/admin/v1/auth/password/change | MeController::changePassword | 登录即可 |
| GET | /api/admin/v1/me | MeController::me | 登录即可 |
| GET | /api/admin/v1/me/profile | MeController::profile | 登录即可 |
| PUT | /api/admin/v1/me/profile | MeController::updateProfile | 登录即可 |
| GET | /api/admin/v1/me/menus | MeController::menus | 登录即可（按权限码过滤菜单） |

### 2.7 菜单管理（8 条，`Sandadmin\Http\MenuController`）

| 方法 | 路径 | 处理器 | 权限码 |
| --- | --- | --- | --- |
| GET | /api/admin/v1/menus/tree | MenuController::tree | system:menu:index |
| POST | /api/admin/v1/menus/groups | MenuController::storeGroup | system:menu:create |
| POST | /api/admin/v1/menus/links | MenuController::storeLink | system:menu:create |
| POST | /api/admin/v1/menus/reset | MenuController::reset | system:menu:reset |
| PATCH | /api/admin/v1/menus/{menu_key} | MenuController::update | system:menu:update |
| DELETE | /api/admin/v1/menus/{menu_key} | MenuController::destroy | system:menu:delete |
| POST | /api/admin/v1/menus/{menu_key}/move | MenuController::move | system:menu:update |
| PUT | /api/admin/v1/menus/{menu_key}/visible | MenuController::visible | system:menu:update |

### 2.8 工作台（8 条，`Sandadmin\Http\DashboardController`，全是只读）

| 方法 | 路径 | 处理器 | 权限要求 |
| --- | --- | --- | --- |
| GET | /api/admin/v1/dashboard/onboarding | DashboardController::onboarding | 登录即可 |
| GET | /api/admin/v1/dashboard/summary | DashboardController::summary | 登录即可 |
| GET | /api/admin/v1/dashboard/todos/all | DashboardController::allTodos | 登录即可 |
| GET | /api/admin/v1/dashboard/todos | DashboardController::todos | 登录即可 |
| GET | /api/admin/v1/dashboard/charts | DashboardController::charts | 登录即可 |
| GET | /api/admin/v1/dashboard/activities | DashboardController::activities | 登录即可 |
| GET | /api/admin/v1/dashboard/notices | DashboardController::notices | 登录即可 |
| GET | /api/admin/v1/dashboard/shortcuts | DashboardController::shortcuts | 登录即可 |

### 2.9 组织（用户/部门/角色/岗位，31 条，`Sandadmin\Http\OrgController`）

| 方法 | 路径 | 处理器 | 权限码 |
| --- | --- | --- | --- |
| GET | /api/admin/v1/users | OrgController::usersIndex | system:user:index |
| POST | /api/admin/v1/users | OrgController::usersStore | system:user:create |
| POST | /api/admin/v1/users/export | OrgController::usersExport | system:user:export |
| GET | /api/admin/v1/users/{user_id} | OrgController::usersShow | system:user:index |
| PUT | /api/admin/v1/users/{user_id} | OrgController::usersUpdate | system:user:update |
| DELETE | /api/admin/v1/users/{user_id} | OrgController::usersDestroy | system:user:delete |
| PUT | /api/admin/v1/users/{user_id}/status | OrgController::usersStatus | system:user:update |
| GET | /api/admin/v1/users/{user_id}/disable-check | OrgController::usersDisableCheck | 登录 + DisableCheckAccess（system:user:update 或 system:user:delete 任一） |
| POST | /api/admin/v1/users/{user_id}/reset-password | OrgController::usersResetPassword | system:user:reset-password |
| PUT | /api/admin/v1/users/{user_id}/roles | OrgController::usersRoles | system:user:assign-role |
| GET | /api/admin/v1/org/user-options | OrgController::userOptions | 登录即可 |
| GET | /api/admin/v1/org/dept-options | OrgController::deptOptions | 登录即可 |
| GET | /api/admin/v1/depts/tree | OrgController::deptsTree | system:dept:index |
| POST | /api/admin/v1/depts | OrgController::deptsStore | system:dept:create |
| PUT | /api/admin/v1/depts/{dept_id} | OrgController::deptsUpdate | system:dept:update |
| GET | /api/admin/v1/depts/{dept_id}/delete-check | OrgController::deptsDeleteCheck | system:dept:delete |
| DELETE | /api/admin/v1/depts/{dept_id} | OrgController::deptsDestroy | system:dept:delete |
| GET | /api/admin/v1/roles | OrgController::rolesIndex | system:role:index |
| POST | /api/admin/v1/roles | OrgController::rolesStore | system:role:create |
| GET | /api/admin/v1/roles/{role_id} | OrgController::rolesShow | system:role:index |
| PUT | /api/admin/v1/roles/{role_id} | OrgController::rolesUpdate | system:role:update |
| DELETE | /api/admin/v1/roles/{role_id} | OrgController::rolesDestroy | system:role:delete |
| POST | /api/admin/v1/roles/{role_id}/copy | OrgController::rolesCopy | system:role:create |
| PUT | /api/admin/v1/roles/{role_id}/status | OrgController::rolesStatus | system:role:update |
| GET | /api/admin/v1/roles/{role_id}/permissions | OrgController::rolesPermissions | system:role:index |
| PUT | /api/admin/v1/roles/{role_id}/permissions | OrgController::rolesSavePermissions | system:role:permission:update |
| GET | /api/admin/v1/permission-tree | OrgController::permissionTree | system:role:index |
| GET | /api/admin/v1/posts | OrgController::postsIndex | system:post:index |
| POST | /api/admin/v1/posts | OrgController::postsStore | system:post:create |
| PUT | /api/admin/v1/posts/{post_id} | OrgController::postsUpdate | system:post:update |
| DELETE | /api/admin/v1/posts/{post_id} | OrgController::postsDestroy | system:post:delete |

### 2.10 选项组/选项（10 条，`Sandadmin\Http\DictController`）

| 方法 | 路径 | 处理器 | 权限码 |
| --- | --- | --- | --- |
| GET | /api/admin/v1/dicts | DictController::index | system:dict:index |
| POST | /api/admin/v1/dicts | DictController::store | system:dict:create |
| GET | /api/admin/v1/dicts/{type_id} | DictController::show | system:dict:index |
| PUT | /api/admin/v1/dicts/{type_id} | DictController::update | system:dict:update |
| DELETE | /api/admin/v1/dicts/{type_id} | DictController::destroy | system:dict:delete |
| POST | /api/admin/v1/dicts/{type_id}/items | DictController::storeItem | system:dict:create |
| PUT | /api/admin/v1/dicts/items/sort | DictController::sortItems | system:dict:update |
| PUT | /api/admin/v1/dicts/items/{item_id} | DictController::updateItem | system:dict:update |
| DELETE | /api/admin/v1/dicts/items/{item_id} | DictController::destroyItem | system:dict:delete |
| PUT | /api/admin/v1/dicts/items/{item_id}/status | DictController::itemStatus | system:dict:update |

### 2.11 插件与系统更新（13 条，`HostReadController` / `PackageController`）

| 方法 | 路径 | 处理器 | 权限要求 |
| --- | --- | --- | --- |
| GET | /api/admin/v1/plugins/overview | HostReadController::pluginsOverview | 登录即可 |
| GET | /api/admin/v1/plugins/market | HostReadController::pluginsMarket | 登录即可 |
| GET | /api/admin/v1/plugins/installed | HostReadController::pluginsInstalled | 登录即可 |
| POST | /api/admin/v1/plugins/upgrade-all | PackageController::upgradeAll | 超管（PackageOps::guard 判 isSuper） |
| POST | /api/admin/v1/plugins/upload | PackageController::upload | 超管 |
| GET | /api/admin/v1/plugins/{plugin_code} | PackageController::show | 登录即可 |
| POST | /api/admin/v1/plugins/{plugin_code}/install | PackageController::install | 超管 |
| POST | /api/admin/v1/plugins/{plugin_code}/upgrade | PackageController::upgrade | 超管 |
| POST | /api/admin/v1/plugins/{plugin_code}/uninstall | PackageController::uninstall | 超管 |
| GET | /api/admin/v1/system/update/status | HostReadController::updateStatus | 登录即可 |
| POST | /api/admin/v1/system/update/check | HostReadController::updateCheck | 登录即可 |
| POST | /api/admin/v1/system/update/start | PackageController::startUpdate | 超管 |
| PUT | /api/admin/v1/scheduled-tasks/{code} | HostReadController::scheduledTaskUpdate | 登录即可（无专门权限码） |

### 2.12 开发构建专用（5 条，只在 `dev 构建标记文件` 标记存在时挂出）

| 方法 | 路径 | 处理器 | 说明 |
| --- | --- | --- | --- |
| POST | /api/auth/dev 登录 | app\controller\dev 登录控制器::dev 登录方法 | 开发免登录签发 |
| POST | /api/admin/v1/auth/dev 登录 | dev 登录控制器::dev 登录方法 | 同上（v1 前缀别名） |
| GET | /api/auth/dev 登录/available | dev 登录控制器::available | 查 dev 登录 是否可用 |
| GET | /api/admin/v1/auth/dev 登录/available | dev 登录控制器::available | 同上 |
| POST | /api/admin/ 下 dev MCP 端点 | Sandadmin\Http\dev MCP 控制器::handle | 开发构建 MCP 端点（无状态 Streamable HTTP） |

### 2.13 不在 route.php 里的路由来源

- **插件路由**：`server/plugin/<app>/config/route.php`（安装产物，`.gitignore`
  内）。sand-core ≥0.2.10 自带一键登录端点，由它自己的 route.php 注册
  （route.php 第 56-57 行注释明说宿主不重复挂）。源码不在本仓，无法逐条
  列出，只能确认机制存在。
- **Webman 官方插件**：`server/config/plugin/` 下只有 `tinywan/storage`、
  `webman/channel`（起 frame:// 服务进程，端口默认 2206）、`webman/console`
  （打包）、`webman/event`（bootstrap 类）四组配置，**没有 route.php、没有
  middleware.php**——即 Webman 官方插件目前不贡献任何 HTTP 路由。
- `config/static.php` 开了静态文件服务（public/），StaticFile 中间件被
  注释掉未启用。
- 没有任何路由定义在注解、YAML 或数据库里。

## 3. 权限码清单（全量 45 个）

### 3.1 判定处用的码（41 个 `system:*` + 4 个 `mcp:dev:*`）

「判定处」= 控制器 `run()` 第二参 / `AnnouncementHttp::requirePermission()` /
`FileHttp` 类常量 / `OrgActor::can()` 调用点。这些码就是实际拦 403 的码。

| 权限码 | 用在哪（出处） |
| --- | --- |
| system:user:index | OrgController::usersIndex/usersShow |
| system:user:create | OrgController::usersStore |
| system:user:update | OrgController::usersUpdate/usersStatus；DisableCheckAccess::UPDATE |
| system:user:delete | OrgController::usersDestroy；DisableCheckAccess::DELETE |
| system:user:export | OrgController::usersExport |
| system:user:reset-password | OrgController::usersResetPassword |
| system:user:assign-role | OrgController::usersRoles |
| system:dept:index | OrgController::deptsTree |
| system:dept:create | OrgController::deptsStore |
| system:dept:update | OrgController::deptsUpdate |
| system:dept:delete | OrgController::deptsDestroy/deptsDeleteCheck |
| system:role:index | OrgController::rolesIndex/rolesShow/rolesPermissions/permissionTree |
| system:role:create | OrgController::rolesStore/rolesCopy |
| system:role:update | OrgController::rolesUpdate/rolesStatus |
| system:role:delete | OrgController::rolesDestroy |
| system:role:permission:update | OrgController::rolesSavePermissions；RoleService 内多处（防自锁判定、管理员码清单 UserService::ADMIN_CODES） |
| system:post:index | OrgController::postsIndex |
| system:post:create | OrgController::postsStore |
| system:post:update | OrgController::postsUpdate |
| system:post:delete | OrgController::postsDestroy |
| system:menu:index | MenuController::tree |
| system:menu:create | MenuController::storeGroup/storeLink |
| system:menu:update | MenuController::update/move/visible |
| system:menu:delete | MenuController::destroy |
| system:menu:reset | MenuController::reset |
| system:dict:index | DictController::index/show |
| system:dict:create | DictController::store/storeItem |
| system:dict:update | DictController::update/sortItems/updateItem/itemStatus |
| system:dict:delete | DictController::destroy/destroyItem |
| system:announcement:index | AnnouncementHttp::index/show/readStats |
| system:announcement:create | AnnouncementHttp::create |
| system:announcement:update | AnnouncementHttp::update |
| system:announcement:delete | AnnouncementHttp::destroy |
| system:announcement:publish | AnnouncementHttp::publish |
| system:announcement:revoke | AnnouncementHttp::revoke |
| system:file:index | FileHttp::PERMISSION_INDEX（index/show/download 私有件）；FileService::assertDownloadable |
| system:file:delete | FileHttp::PERMISSION_DELETE（destroy） |
| system:file:cleanup | FileHttp::PERMISSION_CLEANUP（cleanup） |
| system:task:index | TaskActor.viewAll 的等效码（dev 任务执行者提供器 判定、TaskHttp 注释） |
| system:plugin:index | dev 生命周期执行者提供器 viewAll 判定 |
| system:update:index | dev 生命周期执行者提供器 viewAll 判定 |
| mcp:dev:routes | dev 工具装配表 dev_inspect_routes |
| mcp:dev:errors | dev 工具装配表 dev_recent_errors |
| mcp:dev:phpunit | dev 工具装配表 dev_run_phpunit |
| mcp:dev:gate | dev 工具装配表 dev_run_pre_merge |

### 3.2 权限树的出处（展示面 ≠ 判定面）

`server/src/Module/Org/RoleService.php` 的 `MENUS` 常量（第 16-50 行）硬编码
7 个菜单 × 动作列表，`permissionTree()` 拼出 **35 个码**给角色配置页渲染。
对比判定面可以发现：**6 个判定码不在权限树里**——`system:file:index`、
`system:file:delete`、`system:file:cleanup`、`system:task:index`、
`system:plugin:index`、`system:update:index`（它们只用于内部 viewAll/文件
判定，没有对应后台菜单页，角色界面也配不出来，只能 is_super 生效或直接
插库）。`mcp:dev:*` 4 个是凭据 scope 命名空间，同样不在树中。

### 3.3 菜单 key（9 个，不是三段式权限码但是权限绑定键）

`MenuService::sidebarItems()`（第 76-84 行）和 `catalogNode()`（第 345-353
行）硬编码：`system:user`、`system:dept`、`system:post`、`system:role`、
`system:dict`、`system:announcement`、`system:menu`、`system:plugin`、
`system:update`。侧栏按这些 key 过滤可见性、前端守卫也用它。

### 3.4 权限怎么判定的（现状机制）

- 没有中间件统一查权限。每个控制器自己在 `run()`/`execute()` 里：
  `BizKernel::fromWebman()` → `principal->resolve($request)` 拿 `OrgActor`
  → `$actor->can('system:xxx')`（OrgActor::can = `isSuper || in_array`）。
- `OrgActor.permissions` 来自 `sand_system_role_permission` 表
  （角色↔权限码多对多），由 dev 登录 的身份提供方在解析时装配；
  生产侧 `UnavailablePrincipalProvider` 恒 null，统一会话底座未接。
- `FileActor`、`AnnouncementActor`、`TaskActor`、`LifecycleActor` 各有自己的
  actor/provider 体系（同样 dev 实现 + Unavailable 兜底），形状不同但同思路。
- `UserService::ADMIN_CODES` 是「管理员码清单」常量（5 个高危码），
  `RoleService::guardSelfLockout` 用 `system:role:permission:update` 防自锁。
- **没有任何运行期扫描/反射收集权限点的逻辑**（也没有 `service\Permission`
  注解——那是旧 sand-core 的玩法，这里不存在）。

## 4. MCP 工具清单（全量 4 个）

出处：`server/src/Infra 下 dev MCP 目录/dev 工具装配表.php`（`specs()` 显式返回数组），
装进 `Application/Mcp/ToolCatalog`，只在开发构建由 `dev MCP 控制器` 装配。

| 工具名 | 档位 | 权限码 | 对应 v1 接口 | 干什么 |
| --- | --- | --- | --- | --- |
| dev_inspect_routes | read | mcp:dev:routes | 无（route 字段空串） | 读 `Route::getRoutes()` 运行时路由表 + 契约文件 |
| dev_recent_errors | read | mcp:dev:errors | 无 | 摘读本地日志的错误/慢请求（脱敏） |
| dev_run_phpunit | write | mcp:dev:phpunit | 无 | 白名单目标跑 PHPUnit（要 idempotency_key，支持 dry_run） |
| dev_run_pre_merge | write | mcp:dev:gate | 无 | 跑 `tools/pre-merge.sh --fast`（固定命令，要 idempotency_key，支持 dry_run） |

现状要点：

- **ToolSpec 结构**（`Domain/Mcp/ToolSpec.php`）：name、title、description、
  tier（`ToolTier` 枚举：read/write/high_risk）、permission（必填非空）、
  route（v1 路径，dev 工具为空串）、inputSchema（JSON Schema）、
  runner（`ToolRunner` 接口实现）。
- **三档执行口径**（`Application/Mcp/McpKernel.php`）：read 直执；write 必带
  `idempotency_key`（缺 → MCP_IDEMPOTENCY_REQUIRED）、`dry_run=true` 只回显不
  执行；high_risk 不执行、只返回 pending_request 形状（MCP_HIGH_RISK_NOT_OPEN，
  待确认链路本期未开放）。每个工具按 `mcp:tool:<凭据>:<工具名>` 固定窗口限流
  （`config/mcp.php`：凭据总桶 60/分、单工具 30/分）。
- **身份**：`dev 令牌校验器`（env `dev MCP 令牌环境变量` + `dev 构建标记文件`
  双门槛，令牌 ≥16 位）→ `McpIdentity('admin','dev-token',['mcp:dev'],['*'])`，
  `can()` 对任何权限码放行（'['*']'）。
- **装配方式**：`dev MCP 控制器::handle()` 每请求 `new ToolCatalog($this->devSpecs())`
  → `McpServerFactory` 用官方 `mcp/sdk` 0.9.0 组 StatelessHttpTransport。
  显式清单、无扫描；但清单是在请求时现建的（不是启动期注册表）。
- 生产面：`sand_system_api_token` 机器令牌、生产 MCP 端点均未实现；
  `Infra/Mcp/` 下的通用件（dev MCP 门禁、窗口限流器、日志审计落点、
  dev 令牌校验器）当前在 `tools/dev-only-paths.txt` 里登记为发布剔除
  （注释写明 U-3 生产版落地时再按生产口径实现）。

## 5. 错误码清单（全量 56 个）

信封形状（`Http/ApiOutcome.php`、各 `*Http::failure()`）：失败固定
`{code, message, fields, request_id}`；**成功只有 `{data, request_id}`，
不带 code 字段**（skill 文档里写的成功 `"OK"` 码在实际代码里并不存在）。
`fields` 是 `{字段名: [文案]}`，空集输出 `{}`。

错误码全部是大写蛇形字面量，散在以下文件；没有总表登记处，各异常类只用
正则 `^[A-Z][A-Z0-9_]*$` 兜底形状。

| 错误码 | 含义（代码里的中文文案） | 主要出处 |
| --- | --- | --- |
| VALIDATION_FAILED | 请求内容有误，请按提示修改 | 各异常类 invalid()、各 Http 装配层、ErrorResponder（422/400 两种状态都有用） |
| UNAUTHENTICATED | 请先登录 | OrgException、FileException、AnnouncementException、DictException、NotificationException、UnauthenticatedException、PackageOps、各 Http 装配层 |
| SESSION_EXPIRED | 会话过期/吊销（B2 链，尚未挂载） | Domain/Exception/SessionExpiredException |
| PERMISSION_DENIED | 你没有权限做这个操作 | OrgException、FileException、AnnouncementException、DictException |
| NOT_FOUND | 要找的内容不存在或已被删除 | OrgException::roleNotFound、NotificationException、DictException |
| INTERNAL_ERROR | 系统出了点问题，请稍后再试 | 所有控制器 Throwable 兜底、ErrorResponder |
| IDEMPOTENCY_KEY_REUSED | 相同请求标识被用于不同内容 | FileException、OrgException、AnnouncementException |
| FILE_NOT_FOUND | 文件不存在 | FileException |
| FILE_TOO_LARGE | 文件超过 100MB 上限 | FileException |
| FILE_TYPE_NOT_ALLOWED | 这种文件类型不支持上传 | FileException |
| USER_NOT_FOUND | 用户不存在 | OrgException |
| USERNAME_TAKEN | 这个账号已经被使用了 | OrgException（OrgDbMap 把 uk_sand_system_user_username 翻成它） |
| USER_DISABLE_BLOCKED | 停用被阻断（文案动态） | OrgException |
| DEPT_NOT_FOUND | 部门不存在 | OrgException |
| DEPT_NAME_TAKEN | 同一上级下已经有同名部门了 | OrgException（OrgDbMap） |
| DEPT_NOT_EMPTY | 部门非空不能删（文案动态） | OrgException |
| DEPT_REQUIRED | 请选择这个角色能查看的部门 | OrgException |
| POST_NOT_FOUND | 岗位不存在 | OrgException |
| POST_NAME_TAKEN | 这个岗位名称已经有人用了 | OrgException（OrgDbMap） |
| ROLE_NAME_TAKEN | 这个角色名称已经有人用了 | OrgException（OrgDbMap） |
| LAST_ADMIN | 系统里至少要留一个能用的超级管理员 | OrgException |
| SYSTEM_ROLE_READONLY | 系统角色不能修改 | OrgException |
| STALE_VERSION | 这条刚被别人改过，刷新后再改 | OrgException、MenuException |
| SELF_LOCKOUT | 不能移除你自己正在使用的权限 | OrgException |
| DATA_SCOPE_INVALID | 数据范围选择不正确 | OrgException |
| ANNOUNCEMENT_NOT_FOUND | 公告不存在或对你不可见 | AnnouncementException |
| MENU_NOT_FOUND | 要操作的菜单不存在或已被删除 | MenuException |
| MENU_LOCKED | 菜单被锁（文案动态） | MenuException |
| MENU_URL_INVALID | 链接地址不正确，只能是 http 或 https 开头 | MenuException |
| MENU_DEPTH_EXCEEDED | 菜单最多两级，不能再往里放 | MenuException |
| DICT_TYPE_TAKEN | 这个分组名称已经存在了 | DictException |
| DICT_ITEM_TAKEN | 这个选项已经存在了 | DictException |
| TASK_NOT_FOUND | 要找的内容不存在或已被删除 | ScheduleHttp（定时任务 code 不存在） |
| TASK_RUN_NOT_FOUND | 任务不存在或已过期 | TaskHttp |
| TASK_RUN_NOT_DOWNLOADABLE | 结果文件已过期或不存在 | TaskHttp、TaskController |
| TASK_SCHEDULE_INVALID | 执行时间填写不正确 | ScheduleHttp、HostReadController、ErrorResponder（InvalidExtensionException 的 schedule.* 字段映射） |
| LIFECYCLE_REQUEST_NOT_FOUND | 申请不存在或无权查看 | LifecycleHttp |
| PLUGIN_NOT_FOUND | 插件不存在或已经被卸载 | PackageOps |
| PLUGIN_ALREADY_INSTALLED | 已经装好了 | PackageOps |
| PLUGIN_NOT_INSTALLED | 这个插件还没安装 | PackageOps |
| PLUGIN_VERSION_INCOMPATIBLE | 不能装比现在更旧的版本 | PackageOps |
| PLUGIN_BUSY | 已有别的安装或升级在进行 | PackageOps |
| PLUGIN_PACKAGE_INVALID | 这个文件不是能用的安装包 | PackageOps、PackageController |
| PLUGIN_OPERATION_FORBIDDEN | 只有超级管理员能安装和升级 / 编译版拒绝 | PackageOps |
| SYSTEM_UPDATE_OPERATION_FORBIDDEN | 系统更新被禁（编译版/开关） | PackageOps |
| UPDATE_NOT_AVAILABLE | 没有可升级项 / 已是最新 | PackageOps |
| MCP_UNAUTHORIZED | 凭据无效或未配置 | dev MCP 控制器 |
| MCP_ORIGIN_REJECTED | Origin 不在本机白名单 | dev MCP 控制器 |
| MCP_RATE_LIMITED | 调用频率超限 | dev MCP 控制器、McpKernel |
| MCP_FORBIDDEN | 凭据没有工具需要的权限码 | McpKernel |
| MCP_IDEMPOTENCY_REQUIRED | 普通写工具必须带幂等键 | McpKernel |
| MCP_HIGH_RISK_NOT_OPEN | 高危写未开放，只能生成待确认请求 | McpKernel |
| MCP_TOOL_FAILED | 工具执行失败 | McpKernel |
| MCP_BAD_ARGUMENT | 工具参数不合法 | PhpunitTool、ErrorsTool |
| MCP_TARGET_REJECTED | 测试目标不在白名单 | PhpunitTool |
| MCP_GATE_MISSING | tools/pre-merge.sh 不存在 | PreMergeTool |

另外两类不算信封错误码但相关：

- `TaskRun.error.code`（任务结果里的错误码）是小写蛇形如 `timeout`，存
  `sand_system_task_run.error_code`，走任务 schema 不走信封。
- `OrgDbMap` 把 PG 唯一/检查约束名（`uk_sand_system_*`、`ck_sand_system_*`）
  翻译成上面的业务码——约束名变更会静默破坏这层映射，也是盘点要注意的
  隐式契约。

## 6. 插件挂接机制现状

- **安装**：`composer.json` 的 `post-package-install/update` 钩子调
  `support\Plugin::install`（webman 自带），再跑 `support/sand-publish.php`
  扫 `vendor/composer/installed.json` 里带 `Install::WEBMAN_PLUGIN` 常量的包
  逐个 `Install::install()`，把插件载荷发布到 `server/plugin/<app>/`
  （该目录 gitignore、是产物不是源码）。当前依赖：`supdger/sand-core`
  ^0.2.10、`supdger/sand-package` ^0.2.26。
- **路由**：`support/bootstrap.php` 140-147 行 `Util::scanDir(base_path().'/plugin')`
  找出所有 `plugin/*/config` 目录，`Route::load([config_path(), ...插件config目录])`
  ——**运行期目录扫描**，插件 route.php 里的 `Route::` 声明直接进路由表。
  宿主 route.php 注释明确：sand-core 的一键登录端点由插件自己注册。
- **中间件**：同文件 86-97 行，`config('plugin.<firm>')` 下每个项目的
  `middleware` 配置按厂商分组 `Middleware::load()`；宿主 `config/middleware.php`
  本身是 `[]`。
- **bootstrap/进程**：100-138 行同法扫插件 `bootstrap` 配置逐个 `::start($worker)`。
- **扩展点（新契约面，部分已落地）**：
  - `Application/Plugin/PluginManifestLoader`：运行期读
    `plugin/<code>/manifest.json`（menus、locales 两段），给 MenuService 合
    并侧栏用；按 code 进程内缓存，文件缺失按「无声明」处理。
  - `Domain/Plugin/ExtensionManifest` + `ExtensionCatalog`：从清单数据解析
    扩展声明（settings/search/notification/cron/file_usage/workbench/
    option_group/personal_settings 等 ExtensionKind），`Infra/Plugin/
    ExtensionRegistry` 按「清单声明 + `sand_system_plugin_extension` 覆盖行」
    合并生效项——**声明式、不扫目录**（注释自己写明）。
  - `sand_system_schedule` 表存定时任务登记，`ScheduleHttp` 提供覆盖写接口。
- **插件管理接口**：`PackageOps`/`PackageController`（安装/升级/卸载/上传走
  `sand_system_lifecycle_request` 申请单，由独立生命周期进程执行；
  `PackageGate` 在编译版/生产直接 403）。
- **没有的东西**：插件权限码注册表、插件 OpenAPI 片段收集、插件 MCP 工具
  登记——skill 契约里写了目标形态（x-permission/x-mcp 构建期收集），
  代码里还没有对应实现。

## 7. 门禁与测试文件清单（后续要求只减不增，先立台账）

### 7.1 仓库根 tools/

- `tools/pre-merge.sh` — 本地合并前门禁（--fast 默认 6 步：php -l、危险函数
  扫描、dev-only 引用门禁、TypePHP diff 扫描、PHPUnit unit、PHPStan；--full
  追加 integration；--typephp 可选编译）
- `tools/check-dev-refs.php` — dev-only 符号引用门禁（含 `DEV_REF_PATTERNS`
  符号表与 `DEV_REF_WHITELIST` 豁免清单，白名单本身也是只减不增对象）
- `tools/dev-manifest.php` — 被 check-dev-refs 引用（dev 清单数据/工具函数）
- `tools/dev-only-paths.txt` — 开发专用代码清单（rm/strip 条目，发布剔除面）
- `tools/dev-only-paths.sand-core.txt` — sand-core 的 dev-only 清单镜像副本
- `tools/strip-dev.php` — 发布期按 dev-only-paths 执行 rm/strip
- `tools/strip-sand-core.php` — 对 vendor 里的 sand-core 套用镜像清单
- `tools/build-release.sh` — 发布构建脚本
- `tools/typephp_check.php` — TypePHP 禁用写法扫描（diff 模式卡增量）
- `tools/typephp-allow.txt` — TypePHP 豁免白名单（目前只有头部注释、零条目）
- `tools/typephp-盘点.md` — TypePHP 盘点文档（历史文档，非门禁逻辑）
- `tools/naming_check.py` — 命名与注释规范机械检查（sql/code/notes/terms）
- `tools/naming-terms.tsv` — 统一叫法词表
- `tools/skill-sync.sh` — `.agents/skills/` 与上游 `~/.shared-skills/` 比对
- `tools/build-weak-passwords.php` — 弱密码表构建工具
- `naming-allow.txt`（仓库根）— 命名豁免登记（4 条带原因）

### 7.2 server/ 侧

- `server/phpstan.neon.dist` — level: max，paths = src + tests + 两个 app 文件；
  **没有 baseline 文件**（即当前零豁免硬过）
- `server/phpunit.xml.dist` — unit（tests/Unit）+ integration（tests/Integration）
  两套件；集成套件要 `SAND_TEST_DB_URL`（PostgreSQL）
- `server/tests/security/scan-dangerous-functions.php` — 危险函数扫描
- `server/tests/security/dangerous-functions-allowlist.txt` — 危险函数白名单
  （3 条：windows.php、app/process/Monitor.php、src/Infra 下 dev MCP 目录/Subprocess.php）
- `server/tests/security/assert-no-http-terminal.php` — 断言无网页终端
- `server/tests/security/ProductionDebugTest.php` — 生产 debug 守卫测试
- `server/bin/ci/check-release-tags.php` — CI 用：workerman/webman 必须是
  release tag、禁 cweagans/composer-patches、禁 vendor 入库
- `server/bin/task-cli-autoload.php`、`task-dispatch.php`、`task-run-once.php`、
  `task-show.php` — 任务 CLI（非门禁但属受控入口）
- `server/app/bootstrap/ProductionDebugGuard.php`、`WeakPasswordGuard.php` —
  启动期守卫（生产开 debug / 缺弱密码表拒启）

### 7.3 仓库根 tests/ 与 CI

- `tests/http-error-data-test.php` — 前端 HTTP 错误 data 透传断言（静态契约断言）
- `tests/root-redirect-guard-test.php` — 前端路由守卫断言
- `.github/workflows/dependency-audit.yml` — 唯一 CI workflow：
  composer validate/audit、release tag 检查、vendor 入库拒绝、
  前端 `pnpm audit --prod`
- `catalog.json`、`RELEASE-CHECKLIST.md` — 插件目录与发布清单（流程类台账）

测试目录结构：`server/tests/{Unit,Integration,Support,Fixtures,security}`，
单测/集成按模块对应 `src/` 分层；目前没有 `tests/contract/`（OpenAPI 生成
测试的落地目录还不存在）。

## 8. 改造落点建议（U-6 构建期生成时要动的文件）

契约权威：`openapi.yaml` + `api-conventions.md`（目前仓里都没有，要新建；
建议放 `server/contracts/` 或仓根 `contracts/`）。

### 8.1 路由

- **写生成器**：仓内脚本（建议 `server/tools/` 或仓根 `tools/`），从
  `openapi.yaml` paths 生成 `server/config/route.php`（或拆出
  `route.generated.php` 被 route.php require）。105 条现有路由要逐条对回
  OpenAPI paths；生成物一致性检查进 `tools/pre-merge.sh`。
- **插件路由**：`support/bootstrap.php` 的 `Util::scanDir` 扫 `plugin/*/config`
  是运行期扫描的现形——要么保留「插件 route.php 也是构建期生成物」并加
  启动期一致性断言，要么改成生成期合并路由表。这段 bootstrap 是 webman
  框架代码（`support/bootstrap.php` 属骨架），改动要想清楚边界。
- dev 路由 5 条继续走 `dev 构建环境常量` 哨兵，不进契约或在契约里标 dev-only。

### 8.2 权限码

- 每个控制器里的 `run($request, 'xxx', ...)`、`requirePermission()`、
  `FileHttp::PERMISSION_*` 常量、`DisableCheckAccess` 常量——全部改为从
  `x-permission` 生成的权限注册表取（或生成 `#[Permission]` 等代码侧标注 +
  启动期与注册表一致性校验）。涉及文件：`src/Http/OrgController.php`、
  `MenuController.php`、`DictController.php`、`PackageController.php`、
  `HostReadController.php`、`MeController.php`、`DashboardController.php`、
  `LifecycleController.php`、`TaskController.php`、`AnnouncementController.php`、
  `dev MCP 控制器.php`、`app/controller/FileController.php`、
  `src/Module/Announcement/AnnouncementHttp.php`、
  `src/Application/File/FileHttp.php`、`src/Task/TaskHttp.php`、
  `src/Module/Lifecycle/LifecycleHttp.php`、`src/Module/Org/DisableCheckAccess.php`。
- `RoleService::MENUS` 权限树改由契约生成（顺手补齐 file/task/plugin/update
  这几个判定面有、展示面缺的码）。
- `MenuService` 的 sidebar/catalog 菜单 key 与权限绑定关系也属契约派生物。
- `UserService::ADMIN_CODES`、DevLifecycle/DevTask 的 viewAll 等效判定
  改用注册表常量。

### 8.3 MCP 工具

- `dev 工具装配表::specs()` 的手写 4 条改由 `x-mcp` 生成（dev 工具要么走契约
  dev 扩展段、要么保持手写但进一致性校验）；`ToolSpec.route` 字段开始真正
  绑 v1 路由。
- `dev MCP 控制器` 的每请求装配改为启动期一次装配（或保留现状仅工具表来源
  换生成物）。
- `config/mcp.php` 保留（限流参数不是契约面）。

### 8.4 错误码

- 建错误码总表（`x-error-codes` 域 + api-conventions §2.4），把上面 56 个码
  连同 HTTP 状态映射抄进去；`OrgDbMap` 的约束名→码映射、`ErrorResponder`
  的异常→码映射保留为代码侧消费方，但码的字面量改为引用生成的常量表，
  杜绝「码在异常类里随手写」。
- 决定成功信封是否补 `code:"OK"`——当前实现与 skill 文档不一致，写契约
  时要二选一写死。

### 8.5 门禁联动

- `tools/pre-merge.sh` 加「生成物与 openapi.yaml 一致性」步骤；
  `server/tests/` 下建 `contract/` 目录承接生成测试骨架。
- 白名单类文件（typephp-allow、dev-only-paths、dangerous-functions
  allowlist、naming-allow、check-dev-refs 内嵌白名单）改契约后不应新增
  条目——生成物落在既有文件上即满足「只减不增」。
