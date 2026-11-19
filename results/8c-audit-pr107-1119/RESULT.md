改后可合并

# PR #107 合并前独立审计（只读）

- 仓库：supdger/sandadmin（本地 `/Users/supdger/agent-work/assistant/6d-fix-pr107-secmw-1119/sandadmin`）
- PR：#107 `feat(server): HTTP 安全中间件（N3 响应头 + v1 Origin/CSRF + CORS 白名单）`，OPEN，`feat/http-security-mw` → `main`
- **审计时 head SHA：`c8fc46a11a22cf05e992746bd12988abb26385aa`**（与任务给定 @c8fc46a 一致）
- merge-base：`977427acc285da074a443e8206309289a9bd94fb`；审计时 origin/main：`0c992e32cec458ad5b9b2f80b347cbb0a81acc2d`（main 已前移 14 个提交）
- 规模：41 个文件，+1903 / −28，4 个提交（d4e8bb0、77ed4e4、977c396、c8fc46a）

## 必须修改点（全部改完、复验通过后才可合并）

1. **前端没有发 CSRF 标记头，合并后后台所有写操作都会被拦成 403（阻断）。**
   `CsrfMiddleware` 要求 `/api/admin/v1` 下所有 POST/PUT/PATCH/DELETE 都带 `X-Requested-With`。但 `web/src/lib/adminClient.ts`（v1 唯一客户端，常规请求 :221-231、上传 `adminUpload` :438-441）在 PR head 和 origin/main 上都没有设置这个头；`git grep -i x-requested-with` 在 head 上只在 server/ 里命中，在 main 上 0 命中。受影响的是 web/src/api/v1 下约 60 个写调用：用户、角色、部门、岗位、字典、菜单、公告、文件上传、插件安装、系统更新、改密码、会话管理等。合并后后台增删改会全部 403 `CSRF_REJECTED`。
   改法：在 `adminClient.ts` 的常规请求和 `adminUpload` 里统一带上配置的标记头（默认 `X-Requested-With`，如果配了 `SAND_CSRF_HEADER_VALUE` 要带上相同的值），并补前端单测；和本 PR 一起合入，或者先合入再合本 PR。
   PR 写的"端到端实测"只用 curl 测了 v1 POST，没有测前端真实调用，所以没发现这个问题。技术总监复验时也没覆盖到。

2. **和当前 main 合并有冲突，契约产物要重新生成（阻断）。**
   `git merge-tree --write-tree origin/main c8fc46a` 退出码为 1，19 个 `<contract-generated>` 产物都冲突：route.generated.php、plugin-routes.generated.json、ErrorCodeRegistry/McpToolRegistry/PermissionRegistry、13 个 ContractTest、contract.generated.ts。`contract/openapi.yaml` 能自动合并（main 新增了 `/api/admin/v1/system/update/probe`），但合并后源哈希按 SourceHash 算法应为 `0e05c742…`，与两侧产物头的 `2833307d…`（main）和 `cda7bc00…`（PR）都不同。不重新生成的话，`tools/contract-build --check` 会失败，`ContractIntegrityGuard` 也会让服务拒绝启动。
   改法：rebase 或合并最新 main，重跑 `tools/contract-build` 生成全部产物，再跑 `tools/pre-merge.sh --fast`。rebase 后 CI 要在新 head 上重新跑过。
   另外 main 新增的 `system/update/probe` 是一个只认回环来源的 GET 公开端点，不受 CSRF 影响，但 rebase 后要确认它的响应仍然带安全头、行为不变。

3. **PR 描述和实际范围不一致，要更新描述。**
   - 描述里的"最终 SHA d4e8bb0"已经过期，实际 head 是 c8fc46a，后面还有 3 个返修提交。
   - 置顶待办"CSRF_REJECTED 未登记 openapi.yaml"已在 c8fc46a 完成（已登记，`common: true`），描述却仍写着未完成、"任务约束禁止改 openapi.yaml"。
   - "site_origin 未配置策略待总监确认"已改为生产环境缺失即拒绝启动（新增 `app/bootstrap/SiteOriginGuard.php` 和 bootstrap.php 注册），描述没写。
   - 变更清单漏了 `server/config/process.php`（新增 `SANDADMIN_SERVER_LISTEN` 覆盖项）、`server/config/static.php`（静态链挂 SecurityHeaders）、`SiteOriginGuard` 及其测试、`SecurityConfigFileTest`、`contract/api-conventions.md`。
   - "3 个测试类 28 用例"和实际的 5 个新测试文件不符。
   描述要按最终 head 重写，并补上合并后的门禁结果。

## 审计项结论

### 1. 范围是否与描述一致
代码范围本身是聚焦的：安全响应头、v1 Origin/CSRF、CORS 白名单、配置视图、启动自检、契约登记错误码、测试和断言脚本。没有夹带无关业务改动。`process.php` 的 listen 覆盖项是给断言脚本只绑 127.0.0.1 用的，属于配套改动。描述的过期和遗漏见必须修改点 3。

### 2. 安全与正确性
已核实成立的部分：
- 路径绕过：workerman `Request::path()` 不解码；FastRoute 用同一个原始串精确匹配；`config/route.php` 已 `disableDefaultRoute()`；`unsafeUri` 会拦 `/../`，含 `%xx` 的路径在 findFile 解码后会再查一次。按这些推演，`/api/admin/%76%31/...`、`//api/admin/v1/...`、`/v1/../v1/...` 都到不了 v1 控制器，前缀自限和路由所见一致。（反代层是否会归一化请求行，本仓无法验证。）
- 挂载覆盖：100 条生成的 v1 路由加 dev-login 两条，控制器都在 `Sandadmin\Http\*` 或 `app\controller\*`（plugin=''），都会经过 `''` 键的三件套。没有 v1 写路由漏掉 CSRF。
- CSRF：Origin 存在时做严格形态校验并要求与 site_origin 相等（`null`、带 userinfo、带通配都会被拒）；没有 Origin 才退回 Referer；两者都没有就拒绝。本站源只认配置，不从 Host 或转发头推导。生产环境缺 site_origin 时拒绝启动。
- CORS：精确白名单，回写规范化后的条目，不回显请求原值；永不签发 Allow-Credentials；加 `Vary: Origin`；预检独立应答。"白名单≠CSRF 放行"的口径正确。
- 安全头：放在最外层，短路响应也带头；同名头按大小写不敏感覆盖（workerman Response 是原地修改，忽略返回值的写法正确）；HSTS 只看直连 ssl，不信转发头。
- 403 信封 `{code,message,fields,request_id}` 加 `X-Request-Id`，符合 api-conventions §2.2。

正确性阻断见必须修改点 1、2。

以下建议项不阻断合并，可后续跟进：
- a. 旧插件路由 `/core/*`、`/tool/*`、`/app/sandpackage/*`（plugin=sandadmin/sandpackage）不经过主 `''` 键，所以没有 N3 安全头。这些接口用 Bearer JWT，不受 CSRF 威胁，但安全头覆盖面不包括它们。要全覆盖，可以改用 `'@'` 键或在插件侧挂载，跟进时要和"框架/插件分开"（第 10 条）的口径一起定。
- b. 生产环境走 TLS 反代时，`transport` 不是 ssl，HSTS 不会输出。要等 N2 trusted_proxies 工作包，或在反代层下发。
- c. CSP 默认是 Report-Only 且没有 report-uri/report-to，灰度期收不到违规报告。建议转强制前补上上报地址。
- d. `POST /api/admin/v1/auth/dev-login`（只在 dev 构建注册）也在 CSRF 作用域内，仓内没有调用方，不影响。

### 3. 门禁文件只减不增
**符合。** 本 PR 没有改动 `.github/workflows/*`、`tools/pre-merge.sh`、`tools/contract-build`、`tools/typephp-allow.txt`、`tools/dev-only-paths*.txt`、`server/tests/security/dangerous-functions-allowlist.txt`、`naming-terms.tsv`、phpstan 配置，也没有动任何 baseline、豁免、白名单文件。
`contract/openapi.yaml` 新增的 `CSRF_REJECTED`（`common: true`）是契约错误码登记，不是门禁豁免：它不放宽任何检查，只表示全局中间件发出的这个码不必在每个接口逐一声明，和既有四个通用码同性质。生成产物里只有源哈希行和新码条目，没有删除任何门禁规则。新增的 `tools/assert-security-headers.sh` 是新加的检查工具，没有改既有门禁。

### 4. 与《老板已定事项总表》是否冲突
**不冲突。** 总表 58 条里没有专门讲 HTTP 安全头、CORS、CSRF、中间件的条目。相关口径如下：
- 第 6 条"安全不低于 saiadmin 6.x"：本 PR 是加强，符合；但必须修改点 1 不修会让后台写操作不可用。
- 第 41 条"安全和纯技术选型由技术总监定"：描述里的待确认项（Referrer-Policy 取值、标记头名、CSP 指令集、挂载方式）由总监定即可，不用上报老板。
- 第 24 条"AI 改代码须自动测试，不过不合并"：rebase 后自动测试要在新 head 上重新通过（对应必须修改点 2）。
- 第 18 条"开发环境保留免登录，生产编译剔除"：本 PR 没动 dev 哨兵段，不冲突。
- 第 43 条禁用 Cursor、第 52 条不碰 pet_retail：本 PR 不涉及。
- 第 10 条"框架是框架，插件是插件"：本 PR 只改宿主，不改插件，符合。

### 5. gh pr checks 107（只读）
```
composer audit        pass  8s
frontend prod audit   pass  14s
release self-check    pass  1m3s
```
在 head c8fc46a 上 3/3 通过。但这些检查是基于旧 base 跑的，没覆盖和最新 main 的合并结果，也不跑 phpunit 或 contract-build。GitHub 返回的 mergeable/mergeStateStatus 是 UNKNOWN，本地 merge-tree 显示有冲突（见必须修改点 2）。

## 审计方式与边界
- 只读：用 `gh pr view/checks/diff`、`git merge-tree --write-tree`（不改工作区）、`git grep`/`git show`，并阅读 vendor 源码（webman-framework v2.2.5、workerman v5.2.3、fast-route 1.3.1）。没有起服务、没有跑测试、没有建库。
- 没有合并、推送、评论，也没有改仓库文件；只写了本 RESULT.md。
- 没覆盖到的：反代层行为；浏览器端真实运行验证。必须修改点 1 是按代码静态核实的结论（客户端代码里确实没有这个头），没有在浏览器里实际跑过。
