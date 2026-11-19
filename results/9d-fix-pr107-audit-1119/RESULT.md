# PR #107 合并前审计返修（按 8c 审计「必须修改点」逐条落实）

- 仓库：supdger/sandadmin（本地 `/Users/supdger/agent-work/assistant/6d-fix-pr107-secmw-1119/sandadmin`）
- 分支：`feat/http-security-mw`，返修前 head `c8fc46a`
- **返修后 head SHA：`b878b0df985f3dfd066580e9084f2a8a8f459b0a`**（已普通 push：`c8fc46a..b878b0d`）
- 合并时 origin/main：`a18fef94351a8f1be985d3b4158b3379afa1c27a`（审计后 main 又前移，merge-base 977427a → a18fef9）
- 新增提交（3 个，均在原 head 上叠加，无改写历史）：
  - `14bd9a5` Merge origin/main —— 并入最新 main，契约产物按合并后 openapi.yaml 全部重生成
  - `81ed8f1` fix(web): admin v1 客户端统一携带 CSRF 标记头
  - `b878b0d` chore(contract): 合并后 connection-budget 产物按新源哈希重生成

## 必须修改点落实

### 1. 前端没发 CSRF 标记头（阻断）→ 已修

- `web/src/config/env.ts`：新增 `csrfHeaderName`/`csrfHeaderValue`，分别读 `VITE_CSRF_HEADER_NAME`/`VITE_CSRF_HEADER_VALUE`，默认 `X-Requested-With`/`XMLHttpRequest`，注释标明必须与服务端 `SAND_CSRF_HEADER_NAME`/`SAND_CSRF_HEADER_VALUE` 一致（服务端只要求存在时值任意非空即可）。
- `web/src/lib/adminClient.ts`：`request()`（:222）、`requestDownload()`（:334）、`adminUpload()`（:444）三处统一注入 `headers[env.csrfHeaderName] = env.csrfHeaderValue`——约 60 个 v1 写调用、下载与 multipart 上传全覆盖。
- `web/src/lib/adminClient.test.ts`：新增 2 用例——5 种方法常规请求均带配置头值（:156）；multipart 上传带标记头（:183）。
- `web/.env.example`：补 `VITE_CSRF_HEADER_NAME`/`VITE_CSRF_HEADER_VALUE` 占位与「与 SAND_CSRF_* 对齐」说明。
- 旁证：`web/src/lib/apiClient.ts` 只打 `/api/<path>` 旧端点（`/api/auth/*` 等），不在 `/api/admin/v1` 前缀内，不受 CSRF 影响；仓内除 adminClient 外无其他 v1 调用方。

### 2. 与最新 main 冲突、契约产物需重生成（阻断）→ 已修

- 采用 merge（任务禁强推，不能 rebase）：`git merge origin/main`，20 处冲突 = 19 个 `<contract-generated>` 产物 + `server/.env.example`。
- `server/.env.example` 手工并集：保留本 PR 的 A-5/N3 配置段 + main 的 SAND_CRYPTO_* 段。
- 19 个生成产物全部经 `php tools/contract-build` 依合并后 openapi.yaml 重生成（输出：PASS 契约构建完成（20 个产物，101 个 operation））；`--check` 逐字节比对 PASS，源哈希 `0e05c742…`，与审计预测一致。
- `connection-budget.generated.json`（main 新增产物）源哈希行随重生成更新，单独提交 `b878b0d`。
- `system/update/probe` 合并后实测（本机 webman，127.0.0.1:19130，HTTP）：无挑战 → 404 且响应带全部 6 项安全头 + CSP-Report-Only；带 `X-Forwarded-For` → 404。端点行为不变、安全头在。
- 合并后 `gh pr view`：mergeable=MERGEABLE（原 UNKNOWN/冲突已消除）。

### 3. PR 描述过期 → 已重写

`gh pr edit 107 --body-file` 重写：基线改 `a18fef9`、最终 SHA 改 `b878b0d`；删除已过期的「CSRF_REJECTED 未登记」置顶待办（c8fc46a 已登记 `common: true`）；`site_origin` 策略写明「生产缺失即拒绝启动（SiteOriginGuard）」；变更清单补 `process.php`、`static.php`、SiteOriginGuard+bootstrap 注册、SecurityConfigFileTest、api-conventions.md、openapi.yaml 登记、web/ 前端三件套与契约产物重生成；测试口径改「5 个新测试类」；门禁结果按最终 head 实测更新；待确认项移除已决的第 1/5 条。

## 门禁复跑（最终 head b878b0d，工作树干净）

| 命令 | 退出码 | 关键输出 |
| --- | --- | --- |
| `cd server && vendor/bin/phpunit --testsuite unit` | 0 | `OK (645 tests, 3487 assertions)`，1.08s |
| `cd server && vendor/bin/phpstan analyse --no-progress` | 0 | `[OK] No errors` |
| `tools/pre-merge.sh --fast` | 0 | 7/7 全过（php -l、contract-build --check、危险函数、dev-only、typephp diff、phpunit 645 过、phpstan OK），`pre-merge --fast 全部通过` |
| `php tools/contract-build --check` | 0 | `PASS 产物与契约源一致` |
| `tools/assert-security-headers.sh 19107` | 0 | 21/21 PASS（v1 API、/、favicon 各 7 项；含段内监听断言） |
| `cd web && pnpm vitest run src/lib/adminClient.test.ts` | 0 | 18/18（含 2 条新 CSRF 标记头用例） |
| `cd web && pnpm typecheck` | 0 | `tsc --noEmit` 无输出 |
| `cd web && pnpm exec eslint src/lib/adminClient.ts src/lib/adminClient.test.ts src/config/env.ts` | 0 | 无输出 |
| `python3 tools/naming_check.py code web/src/config/env.ts web/src/lib/adminClient.ts web/src/lib/adminClient.test.ts` | 0 | 0 FAIL / 0 WARN |
| `gh pr checks 107`（新 head 重跑） | — | composer audit pass、frontend prod audit pass、release self-check pass（3/3） |

## 边界与未做

- 未合并 PR、未评论、未动 CI/门禁脚本/豁免白名单；无 force push、无 `git add -A`（全部按文件 add）。
- 未建库、未启数据库；本机 webman 仅 127.0.0.1 临时起服做 probe/安全头实测，已 `start.php stop` 收尸（端口 19130/19131）。
- 审计「建议项」a–d 按任务要求未做。
- 集成套件（`--full`）需 `SAND_TEST_DB_URL`，受「不启数据库」约束未跑。
