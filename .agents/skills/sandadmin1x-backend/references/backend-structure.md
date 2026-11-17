# 后端目录、分层、响应壳与路由

适用范围：SandAdmin 1.x 宿主（`server/`）。写代码前先确认仓库真实结构；本文件讲的是规矩，不替代仓库自己的 AGENTS.md 和 `api-conventions.md`（契约权威）。

## 一、目录职责（写死）

| 目录 | 是什么 | 规矩 |
| --- | --- | --- |
| `server/src/` | 内核权威源码，命名空间 `Sandadmin\` | 所有内核代码都在这里；禁止把业务代码塞进 `app/` |
| `server/app/` | webman 薄壳（少量入口文件） | 只做引导装配，不写业务逻辑 |
| `server/config/` | 路由、中间件、进程、数据库等配置 | 路由手写，不开默认路由扫描 |
| `server/database/migrations/` | 宿主迁移文件的家 | 全部迁移入仓，基线文件必须齐全，全新环境能靠仓库完整建库 |
| `server/plugin/` | Composer 发布产物目录 | 在 `.gitignore` 内，不是源码权威，禁止往里写代码 |

`server/src` 内部按现有分层归位（按真实目录对齐）：

| 层 | 放什么 | 例子 |
| --- | --- | --- |
| `Http/` | 控制器、中间件、`RequestContext`、契约异常处理 | `Http/Middleware/AuthMiddleware` |
| `Module/` | 按域分的业务模块（身份、权限、审计、设置、文件、插件管理等） | `Module/Package/PackageOps` |
| `Domain/` | 领域契约与类型（含扩展点 ExtensionManifest 体系） | `Domain/Plugin/ExtensionManifest` |
| `Infra/` | 基础设施：DBAL 仓储与方言、缓存、加密、发信等 | `Infra/Db/`（唯一数据访问入口） |

分层规矩：

- 必须沿用这套分层，**禁止**为"好看"机械加 Service、Repository 或万能 Base；抽象必须隔离真实变化。
- Service/模块层**禁止**直接依赖 `Connection` 等 DBAL 连接对象（deptrac 强制，CI 会拦）；取数据只能经 `Infra/Db` 的 `Repository<T>`。
- 请求态身份、参数、事务状态**禁止**放进静态属性、单例属性等常驻进程可见的共享可变状态（webman 常驻多进程，下一条请求会读到上一条的脏数据）；`AuthMiddleware` 必须用 try/finally 清 `RequestContext` 并断言无残留事务。

## 二、内核职责边界

内核（宿主）独占：身份（登录/会话/改密/首次设置）、权限（用户/角色/权限码/菜单/部门/岗位/字典）、审计（操作日志、登录日志）、系统设置、文件、插件管理（PackageOps + 扩展点）、定时任务、通知公告、监控、搜索、迁移执行与记账。

内核**不做**：业务域功能（工作流、AI、许可等归插件）、产品用户体系、对外单点登录、生产环境网页安装/升级、网页终端/命令执行、代码生成器（含网页版与命令行脚手架——网页版和 CLI 脚手架都算生成器，不留口子）。

组织上下文约定（已定，总监 11-17）：`OrgContext`/`ShardResolver`/`ConnectionResolver` 三接口是框架给将来多租户版本留的约定，1.x 单租户下恒返回固定值；业务代码不自己拼"属于谁"的条件、统一走仓储入口，**不把它当多租户能力用**（T-1）。

租户作用域注入口（M-06）【老板 2026-11-17 已定 R-7】：框架定义 `TenantContextProviderInterface`——框架中间件调用它取租户号、校验后写 `RequestContext`；宿主实现恒返回 `'default'`；产品侧租户号由 SandIAM SDK 实现同一接口注入。**插件不能自造租户上下文**，谁注入租户号归框架管；措辞定稿：「框架不做多租户功能，但租户作用域机制是框架能力」。

旧面边界：sand-core 是旧兼容层（legacy）只退役不加功能，sand-package 已下线（能力由 PackageOps 承接）。新代码**禁止**依赖 sand-core 的 `BaseModel`/`BaseLogic`/`BaseController`/`BaseValidate`、`CheckLogin`/`CheckAuth` 中间件、`service\Permission` 注解、`ApiException`、`UserAuthCache`/`UserInfoCache`、`SystemLog` 等。

## 三、响应壳（v1 契约信封）

`/api/admin/v1` 组内所有响应统一契约信封：

```json
// 成功
{"code": "OK", "message": "操作成功", "data": { ... }, "request_id": "..."}

// 失败
{"code": "ROLE_NAME_TAKEN", "message": "角色名已被占用", "fields": {"name": "该名称已存在"}, "request_id": "..."}
```

硬规矩：

- `code`：成功固定 `"OK"`；失败用 `api-conventions.md` 错误码总表里登记过的 `UPPER_SNAKE` 码，先登记再使用，能复用不新增。
- `message`：中文、人能看懂的提示；前端提示文案用它。
- `fields`：字段级校验错误明细，没有就省略；`data` 载业务数据，失败时为 null。
- `request_id`：每次响应必带，与日志里的 `request_id` 同源，排障靠它串。
- HTTP 状态码用语义码（200/401/403/429/503），信封 `code` 与 HTTP 状态分工：HTTP 给网关和浏览器看，`code` 给前端分支用。
- 信封取值域（`code` 字符串、成功 `"OK"`、失败用错误码总表 `UPPER_SNAKE`、`fields`/`data` 分工）**已定（总监 11-17）**；`message` 文案走语言包 key（T-9），1.0 只出中文、不在代码里写死长文案。
- **禁止**在响应体返回 token：登录、改密等一律服务端会话 + `Set-Cookie`；清 Cookie 用精确属性 `Path=/; HttpOnly; Secure; SameSite=Lax; Max-Age=0`，不写 `Domain`。
- **非 v1 请求不出契约信封**：`config/exception.php` 的 handler 按路径分流——`/api/admin/v1` 前缀出契约信封，其余委托原 handler；不得宣称"全局信封统一"。

## 四、业务异常

- 业务拒绝必须抛**自带 render 的契约异常**（内核 `ApiException` 类，类名 `Sandadmin\Infra\Contract\ApiException`，签名 `(string $message, string $code)`——命名已定（总监 11-17）；等价于旧 `plugin\saiadmin\exception\ApiException` 的角色）：`throw new ApiException('精确中文提示', 'CODE')`。
- **禁止**抛无 `render` 的裸 `RuntimeException`/未渲染异常——`debug=false` 时会被吞成 `Server internal error`，前端看不到真实原因。
- 权限/登录中间件里的业务拒绝同样抛契约异常，不自己拼 Response。
- 对外提示只写用户能懂的事实，**禁止**把堆栈、SQL、内部路径写进 `message`；生产环境信封不带堆栈。

## 五、路由与中间件

- 全部后台接口走 `/api/admin/v1` 前缀；`server/config/route.php` **手写路由**，按"方法 + 精确路径"登记，未明示默认拒绝。
- 路径命名：全小写、多词连字符、资源复数、动作放末段、参数 `{<名词>_id}`（例 `/users/{user_id}/reset-password`）；JSON 字段 snake_case，与列名一致，前端不转驼峰。
- 鉴权中间件链只挂 v1 组，顺序固定：**CSRF → ErrorMiddleware → AuthMiddleware**；非 v1 请求不进这条链。
- 三组路由表必须维护精确清单（以 `api-conventions.md` 为准）：
  - **公开清单**：`GET /healthz`、`POST /auth/login`、`GET /auth/captcha`、`POST /auth/captcha/verify`，加找回端点族——按鉴权 A-12：`GET /auth/recovery/capability`（登录前查渠道显隐）、`POST /auth/recovery/sms-codes|email-links|verifications|password`（路径族已定（总监 11-17））；找回发生在登录前所以公开，但写端点进敏感限流桶（每 IP 每分钟 10 次、每账号每小时 20 次）+ 防枚举（同文案同耗时）；
  - **must_change 放行表**：会话有效但强制改密时放行的最小集合（改密、登出、`GET /me` 系列）；
  - **数据范围受限表**：凡返回他人数据的接口逐行登记范围规则（本人/部门及下级/持权限码）；新增返回他人数据的接口**必须**登记受限表或声明走仓储层数据范围入口，否则 CI 门禁失败。
- 每个动作都要标权限码——权限点以 `openapi.yaml` 的 `x-permission` 扩展字段为准、注解 `#[Permission('<域>:<资源>:<动作>')]`（如 `system:user:read`）作代码侧标注；**权限点、路由、MCP 工具表一律构建期从 OpenAPI 生成注册表，不在运行期反射/注解扫描发现**（M-12）——启动期只做「生成物与代码/契约一致性校验」，不一致拒绝启动；显示名归菜单扩展点声明、不进注解。菜单/按钮权限点经内核扩展点声明，**禁止** SQL 直插内核表。
- 插件接口挂 `/api/admin/v1/plugin/<name>/...`——同一条 v1 鉴权链和限流/审计，`plugin/` 段做插件命名空间隔离（前缀形态已定（总监 11-17））。
- CSRF：v1 组写操作校验 `Origin` 与 `security.site_origin` 精确相等（scheme+host+port）；`Origin: null` 拒绝；仅 Origin 缺失才回落本站 Referer；不任意带凭据 CORS。
- 统一安全响应头中间件：`nosniff`、`X-Frame-Options: DENY`、`Referrer-Policy`、HSTS（仅 HTTPS）、CSP `default-src 'self'` 起步。

## 六、控制器写法骨架

```php
<?php

declare(strict_types=1);

namespace Sandadmin\Http\Controller\System;

use Sandadmin\Http\RequestContext;
use Sandadmin\Infra\Contract\ApiException;
use Sandadmin\Infra\Contract\Permission;
use support\Request;
use support\Response;

final class RoleController
{
    #[Permission('system:role:read')]
    public function index(Request $request): Response
    {
        // 取参数 → 调模块服务 → 返回契约信封
        $query = RoleListQuery::fromRequest($request); // 带类型的查询对象，不裸传数组
        $data = $this->roleService()->search($query);
        return contract_response('OK', 'ok', $data);
    }

    #[Permission('system:role:save')]
    public function save(Request $request): Response
    {
        $input = RoleSaveInput::fromRequest($request); // 校验失败内部抛 ApiException
        if ($this->roleService()->nameTaken($input->name)) {
            throw new ApiException('角色名已被占用', 'ROLE_NAME_TAKEN');
        }
        return contract_response('OK', 'ok', $this->roleService()->save($input));
    }
}
```

要点：控制器薄、校验进带类型的输入对象、业务在模块服务、写操作包事务（涉及一致性的必须声明事务边界与回滚策略）、拒绝抛 `ApiException`。

导入导出（S-04）：导入/导出行数超过阈值（配置项，按压测定）时不在 Web 进程同步执行，转后台任务、完成后发通知；下载走文件记录的 nanoid 链接并带过期时间；导出沿用受限读面 + 公式注入转义。

## 七、部署形态（后端必须知道的边界）

- **生产环境不安装、不编译**：制品在开发/CI 环境构建打包后上传部署；「未编译」判定看构建 manifest 的 `build_kind` 字段，不看文件存不存在。
- 生产上插件动作只有**查看/启用/停用**；安装、升级走离线包 + CLI。
- 未编译版（开发/构建环境）的网页申请通道：HTTP 只登记 `sand_system_lifecycle_request`，由独立生命周期进程执行，网页进程不解包不执行；默认关闭、仅超管、先验签再解包、提交强制二次验证、绝不暴露公网。
- 发版门禁：发布构建必须 `SAND_DEV_BUILD` 未定义、dev 链整条剔除、产物 grep 零残留、中间件链生产守卫断言通过，缺一不出包。
- 发布制品只在 CI 构建并带签名与来源证明，个人电脑产物不发布；发版保留上一版制品（流程归 ai-loop skill）。
- 不做生产网页安装器：`/setup/*` 首次设置完成后永久 410；未初始化的实例拒绝启动。

## 八、常驻进程与故障口径

- webman 常驻多进程不开协程；计数、快照、限流状态全部落库，**禁止**依赖进程内存。
- DB、权限、计数、验证码存储故障一律 **fail-closed** 拒绝，不静默放行。
- 会话存储走 `SessionStoreInterface` 唯一入口（默认 Redis，私有部署可选 db 驱动，file 仅开发）；会话存储故障返 503 + `Retry-After`，**不返 401、不清登录态**。
- 出网调用统一走出口并强制证书链 + 主机名校验；生产构建禁 `verify=false`/`VERIFYPEER=0`/`VERIFYHOST=0`。
