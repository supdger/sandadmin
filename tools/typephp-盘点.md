# TypePHP 兼容性只读盘点

- 盘点日期：2026-11-17
- 分支：`feat/typephp-compat`（基于 main 的工作区）
- 盘点口径：`~/.shared-skills/php-aot-coding` + 仓内 skill `sandadmin1x-backend/references/typephp.md`（T-8 / B-23 / ADR-23 硬规矩）
- 扫描范围：`server/src`、`server/app`、`server/config`、`server/support` 四个目录全部 PHP 文件，共 **302 个文件、25,425 行**；不含 `vendor`、`server/plugin/`、前端、测试目录
- 各目录规模：`src` 256 文件 23,078 行；`app` 9 文件 1,031 行；`config` 32 文件 1,019 行；`support` 5 文件 297 行
- 说明：本次盘点在 main 分支现状上做，比 v4 文档 T-8 里记的 0.2.4 标签（`src` 217 文件、20,693 行）多出 39 个 src 文件；`__construct`/`__invoke`/`__toString` 按约定不算魔术方法问题；`$e::class`、`self::$prop` 这类固定类名的常量/静态属性读取不算动态调用
- 结论先说：**这个仓库的 TypePHP 底子很好**——魔术方法、eval、extract、可变变量、运行期反射扫描全是零命中，类型标注基本写全，PHPStan `level: max` 已配置且扫着 `src`。真正要整改的集中在：37 个文件缺 `declare(strict_types=1)`、个别引导/兼容文件的无类型参数、以及 `mixed` 用量的收口。大头风险不是"违规多"，而是 webman 原版引导文件天生不兼容"声明文件只放声明"这条规矩

---

## 1. 魔术方法

**命中 0 处。**

全仓搜遍 `__get/__set/__call/__callStatic/__isset/__unset/__sleep/__wakeup/__clone/__debugInfo/__serialize/__unserialize`，除 `__construct` 外没有任何魔术方法定义。

## 2. 动态属性

**命中 0 处。**

- 无 `#[AllowDynamicProperties]`；
- 全仓 `$this->x = ...` 赋值都能对上已声明且带类型的属性（抽查 `app/process/Monitor.php`、`app/` 各控制器确认）；
- `RequestContextStore` 往 `$request->context[]` 写数据——`context` 是 webman `Request` 类自带的公开数组属性（框架的每请求口袋），且 `Request::destroy()` 会清空，这是规范允许的用法，不算动态属性，归入保留项；
- `new \stdClass()` 有 20+ 处（`src/Http/*Controller.php`、`DevCatalog.php`、`JsonErrorResponse.php` 等），全部是用空对象生成 JSON `{}`，没人往里塞动态字段，属规范允许的"宽松结构"。

## 3. 可变变量 / 可变调用

| 位置 | 问题 | 判定 |
| --- | --- | --- |
| `server/support/bootstrap.php:106,122,134` | `$className::start($worker)` 变量类名静态调用 | 保留项（webman 引导循环，见保留项节） |
| `server/support/sand-publish.php:28` | `$class::install()` 变量类名静态调用 | 保留项（webman 插件发布机制） |
| `server/support/bootstrap.php:27-28` | `Worker::$eventLoopClass` 框架静态属性读写 | 保留项 |

其余：**`$$` 可变变量 0、`$obj->$m()` 可变方法调用 0、`new $class` 0、`call_user_func` 0、`Closure::bind/bindTo` 0**。`$work()`、`$handler($request)`、`$build()`、`$runner(...)` 这类都是对显式闭包参数的调用，类型可静态确定，不算问题。

## 4. extract() / compact()

**命中 0 处**（两种都没用）。

## 5. eval / create_function / assert(字符串) / 动态 include

- `eval`、`create_function`、任何形式的 `assert()`：**0 处**。
- include/require 非字面量路径共 **8 处**，全部是引导/发布脚本，逐条判定见保留项节：

| 位置 | 内容 |
| --- | --- |
| `server/src/Task/TaskCli.php:25` | `require_once` 固定拼接路径 `support/gssencmode.php` |
| `server/src/Task/TaskCli.php:78` | `require $file` 读 `config/task.php` 配置 |
| `server/support/bootstrap.php:54` | `require_once` 固定相对路径 |
| `server/support/bootstrap.php:67,75,79` | `include_once` 配置里登记的 autoload.files |
| `server/support/sand-publish.php:6,14` | `require` gssencmode 与 `vendor/autoload.php` |

## 6. 运行期反射发现

**命中 0 处。** 无 `new ReflectionClass`、无 `getAttributes()` 注解读取、无自动装配式扫描。权限点目前是手写路由 + 代码侧标注，本来就没有运行期扫描注册，与 M-12「注册表构建期生成」方向一致。

仅有的近似项：`OrgIdempotency.php:124` 对 `\stdClass` 用 `get_object_vars()` 转数组（固定形状转换，不是发现）；`bootstrap.php:46` `method_exists`、`sand-publish.php:24` `defined/constant/is_callable` 判定插件 Install 类（框架插件机制，保留项）。

## 7. 静态属性 / 全局变量存请求态或租户态

| 位置 | 内容 | 判定 |
| --- | --- | --- |
| `server/src/Infra/Auth/WeakPasswordRuntime.php:17` | `private static ?WeakPasswordList $list` | 保留项：进程级只读词表，加载后不变，注释已写明"不属于请求态"，符合"静态只许存不可变资源" |
| `server/app/process/Monitor.php:134` | 函数级 `static $lastMtime, $tooManyFilesCheck` | 保留项：webman 原版文件监视进程，独立进程内的计时缓存，不碰请求 |
| `server/config/process.php:19` | `global $argv` | 保留项：webman 原版进程配置文件，判定 CLI 参数用 |
| `server/support/bootstrap.php:27-28` | `Worker::$eventLoopClass` | 保留项：框架事件循环选型 |

请求上下文走的是 `RequestContextStore`（存在 `Request::$context` 请求对象上，`destroy()` 自动清），**没有发现把当前用户/请求参数塞进静态属性或单例的写法**。这一项是干净的。

## 8. 缺类型

- **缺返回类型：0 处**（构造器按语言规则不算）。
- **无类型属性：0 处**。
- **无类型参数：12 处**，其中 `src` 内只有 1 处：

| 位置 | 参数 | 建议改法 |
| --- | --- | --- |
| `server/src/Infra/Log/SdkQuietLogger.php:33` | `log($level, ...)` 的 `$level` | PSR-3 `LoggerInterface` 签名就是无类型 `$level`，实现侧可加 `mixed $level` 并注明接口兼容原因 |
| `server/app/bootstrap/ProductionDebugGuard.php:68` | `start($worker, ...)` 的 `$worker` | `Worker` 类型可标 `?\Workerman\Worker`（引导签名兼容 worker 为空场景） |
| `server/app/process/Monitor.php:96,132,232,269` | `$monitorDir`、`$monitorExtensions`、`$memoryLimit`（5 处） | webman 原版开发用监视进程；要改可标 `string|array`，也可整文件保留（见保留项节） |
| `server/support/bootstrap.php:31,38` | 错误处理/关机闭包的 `$level/$message/$file/$line`、`$startTime`（5 处） | 框架原版文件，保留项 |

> 注：扫描时 `array &$fields` 这类 by-ref 出参曾被误报为无类型，已人工逐条复核，实际都带 `array` 类型，不计。

## 9. mixed 用量（规模统计）

- 真实 `mixed` 类型标注（token 级）：**91 处，分布在 41 个文件**；
- 另有约 **391 处** `mixed` 出现在 docblock 形状标注里（`array<string, mixed>` 等），属给 PHPStan 看的形状声明。

大头是"边界解析器"模式——`mixed $value` 进、`parseXxx()/asXxx()` 出具体类型，处理请求输入与数据库行这类天然无类型边界，用量本身合理。按文件计数前列：

| 文件 | mixed 处数 | 说明 |
| --- | --- | --- |
| `server/app/bootstrap/ProductionDebugGuard.php` | 7 | env/配置判定 |
| `server/src/Module/Announcement/AnnouncementService.php` | 7 | 含 `create/update/publish/revoke` 返回 `mixed`、`idempotent()/snapshot()` 双向 `mixed` |
| `server/src/Application/File/FileHttp.php` | 6 | 请求输入解析 |
| `server/src/Task/PdoTaskStore.php` | 6 | 任务行解析 |
| `server/src/Application/Plugin/PluginManifestLoader.php` | 4 | 清单 JSON 解析 |
| `server/src/Module/Announcement/AnnouncementDraft.php` | 4 | 输入解析 |
| `server/src/Module/Org/UserService.php` | 4 | 查询参数解析 |

**值得收口的几处**（能写具体类型却写 `mixed` 的）：

| 位置 | 建议改法 |
| --- | --- |
| `server/src/Module/Announcement/AnnouncementService.php:48,70,88,103` | 四个公开方法返回 `mixed`，实际返回信封数组，应标 `array` 或专用结果类型 |
| `server/src/Module/Announcement/AnnouncementService.php:232,257` | `idempotent()`/`snapshot()` 的 `mixed` 可用泛型模板或具体信封类型替代 |
| `server/src/Application/File/IdempotentReplay.php:16` | `public readonly mixed $response` 可换 `array` 或联合类型 |
| `server/src/Infra/Db/Repository.php:250` | `bindValue(mixed $value): mixed` 可收窄为标量联合类型（`string|int|float|bool|null`） |
| `server/src/Infra/File/PdoFileRepository.php:218` | `transaction(callable $work): mixed` 可用 `@template` 泛型标注替代 |

## 10. 缺 declare(strict_types=1)

共 **37 个文件未声明**（总 302，已声明 265）：

| 目录 | 已声明/总数 | 缺的文件 |
| --- | --- | --- |
| `server/src` | **256/256，全有** | — |
| `server/app` | 5/9 | `functions.php`、`middleware/StaticFile.php`、`process/Http.php`、`process/Monitor.php` |
| `server/config` | 2/32 | 除 `file.php`、`security.php` 外全部 30 个 |
| `server/support` | 2/5 | `Request.php`、`Response.php`、`bootstrap.php` |

建议改法：声明对该文件内生效、无副作用，`config/*.php` 这类纯返回数组的配置文件和 `Request/Response` 空壳补上一行即可；`bootstrap.php` 属 webman 原版文件见保留项节，改不改随"是否 fork 引导文件"的决定走。

---

## 保留项（第三方框架硬性要求，不改或不该改）

| 位置 | 保留原因 |
| --- | --- |
| `server/support/bootstrap.php` 全文件 | webman 原版引导文件：顶层执行、`$className::start()` 可变静态调用、按配置 `include_once` autoload 文件、无类型错误处理闭包、`Worker::$eventLoopClass`——这套就是 webman 的启动序列本身，改它等于 fork 框架引导层，收益低风险高；严格达标需上游支持或接受"引导文件豁免" |
| `server/support/gssencmode.php` 全文件 | 顶层执行的引导补丁：必须在 .env 加载后、首个数据库连接与 worker fork 前被 `require`，时机本身决定了它没法做成纯声明文件 |
| `server/support/sand-publish.php` 全文件 | composer `post-install-cmd` 脚本：按 webman 插件约定扫 `installed.json` 找 `*\Install` 类调 `install()`，变量类名调用是这个机制本身；CLI 脚本不进请求生命周期 |
| `server/support/Request.php` / `Response.php` | webman 原版薄壳类，跟随框架升级会被覆盖式比对 |
| `server/config/*.php`（30 个无 strict_types） | webman 配置文件契约是"返回数组的脚本"，含顶层 `return`、`global $argv`；补 `declare` 可行，但 `route.php` 等文件本质是执行体，框架机制豁免更合理 |
| `server/app/process/Monitor.php` | webman 原版开发用文件监视进程：无类型参数 + 函数级 `static` 缓存；只在开发 reload 链用，进程独立不碰请求 |
| `server/app/process/Http.php` | 空壳 `extends App`，webman 进程入口占位 |
| `server/src/Infra/Auth/WeakPasswordRuntime.php:17` 静态属性 | 进程级只读弱密码词表，符合"静态只许存不可变资源"的例外条款，注释已声明 |
| `server/src/Task/TaskCli.php:25,78` 两处 require | CLI 装配引导：读固定路径的 gssencmode 补丁和 task 配置，路径确定、属引导入口 |
| `server/src/Http/RequestContextStore.php` 写 `$request->context[]` | `context` 是 webman Request 自带的每请求公开属性，`destroy()` 自动清空，是框架给的正确存放点，不是动态属性 |
| `server/src/Infra/Log/SdkQuietLogger.php:33` `$level` 无类型 | PSR-3 `LoggerInterface::log` 签名要求，收窄最多到 `mixed`，属接口兼容 |
| `server/support/bootstrap.php:46`、`sand-publish.php:24` 的 `method_exists/is_callable/constant/defined` | 框架/插件加载机制的兼容性探测，不是业务代码的反射发现 |

---

## 汇总表

| # | 类别 | 命中数 | 需整改 | 保留项 |
| --- | --- | --- | --- | --- |
| 1 | 魔术方法 | 0 | 0 | 0 |
| 2 | 动态属性 / AllowDynamicProperties | 0 | 0 | 0 |
| 3 | 可变变量/可变调用 | 4（另有框架静态属性读写 1 组） | 0 | 4+1 |
| 4 | extract() / compact() | 0 | 0 | 0 |
| 5 | eval/create_function/assert + 动态 include | eval 系 0；动态路径 require 8 | 0 | 8 |
| 6 | 运行期反射发现 | 0 | 0 | 0 |
| 7 | 静态/全局存请求态 | 0 违规（4 处静态/全局用法均为不可变资源或框架文件） | 0 | 4 |
| 8 | 缺类型（返回/属性/参数） | 无类型参数 12（`src` 仅 1） | ~7 可改（src 1 + app 6） | 5（bootstrap 闭包） |
| 9 | mixed | 真实标注 91 处/41 文件 + docblock 约 391 处 | 约 10 处可收窄 | 其余为边界解析合理用量 |
| 10 | 缺 `declare(strict_types=1)` | 37 文件 | 36 可补 | 1（bootstrap.php 随框架决定） |

## 最大整改风险点

1. **`server/support/bootstrap.php`（webman 原版引导）**：它是全仓唯一"顶层执行 + 可变静态调用 + 动态 include + 无类型闭包"全占的文件，而 webman 的 worker 启动序列就是它。整改它 = fork 框架引导层或推动上游改，牵连插件 autoload、中间件加载、Route::load 整条启动链——建议走"引导文件豁免"而不是硬改。
2. **`server/src/Infra/Db/Repository.php`**：全部模块的仓储基类（`protected readonly Connection`、mixed 边界方法），任何签名改动牵连 `src/Module/`、`src/Infra/` 下所有仓储子类，是整改波及面最大的单文件。
3. **输入解析器一族**（`FileHttp.php`、`AnnouncementHttp/Draft.php`、`TaskHttp.php`、`UserService.php`、`MenuService.php`）：`mixed` 进 + `array &$fields` 出参的错误收集模式是统一写法，要收窄就得整套模式一起定替代方案，单点改会产生两种风格并存。
4. **`server/config/*` 与 `server/support/*` 里的 webman 原版文件**（Monitor、Http、Request/Response、bootstrap）：补 `strict_types` 机械且安全，但这些文件跟随框架升级走，改动越大以后比对升级越费劲——建议最小化触碰，只补 declare。

---

## 整改落地记录（2026-11-17，feat/typephp-compat）

### 与盘点结论的出入

- `server/support/gssencmode.php` 也缺 `declare(strict_types=1)`（盘点第 10 节漏计）；它在保留项全文件清单里，不补。
- `server/config/plugin/**` 共 7 个插件发布配置文件同样缺 declare；属插件 composer 发布产物而非宿主自有代码（`tools/typephp_check.php` 的 `TYPEPHP_SKIP_PREFIX` 也跳过该前缀），不补。
- `server/app/process/Monitor.php` 盘点计入"可补 declare"，实查不补：`strict_types` 会改变该文件内的标量强转语义（`getMemoryLimit()` 里 `strlen($memoryLimit)` 对非字符串实参、`is_dir($file->getRealPath())` 对 `false` 返回值在严格模式下直接 TypeError），它是保留项 webman 原版文件，不动。

### 保留项追加（mixed 合理用量，本次登记不改）

共同原因三类：**A. 边界解析器**——入参是请求参数、表单字段、数据库行、JSON 解码值这类天然无类型边界，函数职责就是接收任意形状并判定收窄，把 `mixed` 收窄成联合类型只会把同样的防御代码上移到每个调用点；**B. `array &$fields` 出参**——字段错误收集器是全仓统一模式（FileHttp / AnnouncementDraft / TaskHttp / UserService / MenuService 同构），单点改返回元组会产生两种风格并存，替代方案牵连整套写法，本次保留；**C. 资源句柄**——PHP 类型系统没有 `resource` 原生类型，`mixed` 是合法表达。

| 位置 | 原因 |
| --- | --- |
| `server/app/bootstrap/ProductionDebugGuard.php:21,26,35,48`（`$appEnv`/`$debug`/`$devLogin`/`$mcpToken`） | A：env/配置原值，职责就是判定任意来源值 |
| `server/app/bootstrap/ProductionDebugGuard.php:68` `start($worker)` | webman `Bootstrap::start(?Worker)` 与独立脚本测试按任意 object 调用取并集，已补 `mixed`（原为无类型参数） |
| `server/app/bootstrap/WeakPasswordGuard.php:24,47`（`$worker`/`$appEnv`） | 同上：接口兼容 + env 原值解析 |
| `server/src/Infra/Log/SdkQuietLogger.php:37` `log($level)` | 保留项原有：PSR-3 `LoggerInterface::log` 无类型参数，已补 `mixed` 并注明 |
| `server/src/Application/File/FileHttp.php:48,51,52`（`$stream`/`$visibilityParam`/`$usageParam`）、246 `visibility()`、265 `usage()` | `$stream` 属 C（docblock 标 `resource`）；其余属 A（表单原值）+ B（`&$fields` 出参） |
| `server/src/Application/File/FileHttp.php:430` `success($data)`、`src/Module/Announcement/AnnouncementHttp.php:215` `success($data)`、`src/Http/ApiOutcome.php:18` `ok($data)`、`src/Http/JsonSuccessResponse.php:15` `fromPayload($payload)` | 契约 JSON 信封值：对象/数组/标量/null 都合法，与信封 docblock 一致 |
| `server/src/Application/File/UploadService.php:47` `$stream`、`src/Application/File/BoundedCopier.php:20` `$input`、`src/Application/File/PendingStream.php:15,22` `$stream`、`src/Infra/File/FinfoMimeSniffer.php:18` `$stream`、`src/Infra/File/LocalFileStorage.php:57` `$stream` | C：resource 无原生类型 |
| `server/src/Module/Announcement/AnnouncementDraft.php:84,98,124,143`（`string()`/`intList()`/`bool()`/`dateTime()`） | A + B：请求体字段解析 |
| `server/src/Module/Org/UserService.php:974,984,994,1008`（`page()`/`pageSize()`/`queryStatus()`/`queryId()`） | A：查询参数原值 |
| `server/src/Module/Menu/MenuService.php:1022,1027`（`isOn()`/`textOrNull()`） | A：请求体字段原值 |
| `server/src/Module/Org/OrgInput.php:137`、`src/Module/Notification/NotificationHttp.php:123`、`src/Module/Announcement/AnnouncementQuery.php:45`、`src/Module/DemoNote/DemoNoteQuery.php:52,65,79`、`src/Infra/Db/Query/ListQuery.php:78,94` | A：查询参数解析器 |
| `server/src/Module/Org/OrgSql.php:13,18,28`、`src/Module/Lifecycle/LifecycleRepository.php:53,58`、`src/Module/Announcement/AnnouncementRepository.php:500`、`src/Task/PdoTaskStore.php:410,422,427,477,506,518`、`src/Infra/Plugin/PluginExtensionRepository.php:153,162,174`、`src/Infra/Plugin/PluginDictRepository.php:263`、`src/Infra/File/PdoFileRepository.php:368` | A：数据库行值解析（行天然 `array<array-key,mixed>`） |
| `server/src/Application/Plugin/PluginManifestLoader.php:87,103,154,175`、`src/Domain/Plugin/ExtensionManifest.php:158`、`src/Http/McpController.php:118` | A：插件清单 JSON / JSON-RPC 原值解析 |
| `server/src/Module/Org/OrgIdempotency.php:145` `normalize()` | A：jsonb 快照递归归一化，进出同为 JSON 值 |
| `server/src/Domain/Idempotency/IdempotencyStore.php:32` 及各实现 `record($response)` | 端口写入侧收任意 jsonb 可序列化值，docblock 已注明 |
| `server/src/Domain/Mcp/ToolOutcome.php:18,23,28` `$payload` | MCP 工具返回载荷由工具自定形状 |
| `server/src/Module/Announcement/AnnouncementService.php:255,292`（`idempotent()`/`snapshot()` 原生 `mixed`） | 返回 `T|快照`/`T|数组`，原生类型表达不了，精确形状在 `@template` docblock |

### 本次已整改

- **declare(strict_types=1)**：补 28 个文件（`server/config/` 宿主配置 23 个、`server/app/functions.php`、`app/middleware/StaticFile.php`、`app/process/Http.php`、`server/support/Request.php`、`support/Response.php`）。未补：`support/bootstrap.php`、`support/gssencmode.php`、`app/process/Monitor.php`（保留项）、`config/plugin/**`（插件产物）。
- **无类型参数**：`SdkQuietLogger::log($level)` → `mixed`（PSR-3 兼容）；`ProductionDebugGuard::start($worker)` → `mixed`（接口与测试取并集）。保留项文件内其余无类型参数不动。
- **mixed 收窄**：`AnnouncementService::create/update/publish/revoke` → `Announcement|array<array-key,mixed>|\stdClass|null`；`idempotent()`/`snapshot()` → `@template T` docblock；`AnnouncementHttp::apiOf()` 入参同步收窄；`IdempotentReplay::$response` → `array|\stdClass|string|int|float|bool`；`IdempotencyStore::findActive()` 及两个实现 + 测试 Fake 的 `response` 形状 → jsonb 解码值联合类型；`Repository::bindValue()` → `string|int|float|bool|\DateTimeInterface|null` 进、`string|int|float|null` 出（`bindValues`/`insertRow`/`updateRow` docblock 同步）；`PdoFileRepository::transaction()` → 补 `@template T` docblock（对齐接口已有标注）。
- **行为微调一处**：`AnnouncementService::idempotent()` 重放路径对非数组/非 stdClass 的快照抛 `RuntimeException('幂等记录损坏')`（对齐 `OrgIdempotency::replay` 既有口径；此前会把损坏快照原样返回）。
