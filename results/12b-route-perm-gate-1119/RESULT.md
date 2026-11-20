# 12b HR4-a 路由权限静态门禁 + IDOR 模板 — RESULT

**载体**：PR #113 `feat/route-permission-gate` → `main`（supdger/sandadmin）
**URL**：https://github.com/supdger/sandadmin/pull/113
**状态**：OPEN 未合并（合并等 #81 合并后再议；本任务不合并）
**基线**：`origin/main` = `5360129`（开工时写明）
**交付 HEAD**：`7e70541d600de2f704b3f838b49146a39805ae4c`（验收返工后，普通 push，无强推）
**提交链**：`2180ad7` 门禁主体 → `33c8bc8` 挂 pre-merge → `4975db3` IDOR 样板 → `c34353d` dev-only 登记 → `7e70541` 验收返工收紧

## 交付内容

1. `tools/check-route-permission.php`（新）：纯 PHP CLI 门禁，exit 0/1/2，中文 PASS/FAIL 风格同 `check-dev-refs.php`。
   - 每条生成路由（host `server/config/route.generated.php` + manifest 发现的 `server/plugin/*/config/route.generated.php`）对账 `contract/openapi.yaml`：方法+路径必须命中 operation，`x-permission` 必须存在且形状合法，`x-handler` 指向的类/方法必须存在。
   - 按权限形态核 handler 可达代码证据：`authenticated` 要身份判定、`super` 要超管判定、单码/`{any_of}`/`{all_of}` 要权限判定、`public` 要公开门禁证据；证据沿 `->` 链跟进 helper 方法与被调类。
   - 生产路由表与插件路由入口不得出现 `tools/dev-only-paths.txt` 清单内 dev 路径；`server/config/route.php` 的 dev 段必须包在 `DevBuild::enabled()` 块内。
2. `tools/pre-merge.sh`：`--fast` 在 `contract-build --check` 之后插入本门禁（9 步序列）。
3. `tools/dev-only-paths.txt`：登记 `rm tools/check-route-permission.php`（门禁自身含 dev 符号，随发布剥离，属 rm 清单而非豁免）。
4. IDOR 四象限模板：`server/docs/module-tests.md` 补「越权（IDOR）测试口径」一节 + `server/tests/Unit/Module/Lifecycle/LifecycleIdorTest.php` 样例（无身份→401/拒绝、他人资源→404/拒绝、owner→放行、viewAll→放行）。未改 Lifecycle 业务逻辑。

## 验收返工（独立验收反馈后三项收紧，HEAD `7e70541`）

- **全量 `Route::` 调用点形态兜底**：新增 `routeCallSites()`（`token_get_all` 口径，注释/字符串天然不产生标识符），枚举生成路由文件里每一个 `Route::<name>(` 调用点；凡不符合「动词 + 单引号路径 + `[Class::class,'m']`」规范形态的一律 `未识别的 Route::X 形态，门禁无法对账` 判 FAIL（覆盖 `Route::any/match/group`、双引号路径、`Class@method`、闭包 handler 等）。
- **证据只认可执行 token**：`indexPhpFile` 拼方法体文本时跳过 `T_COMMENT`/`T_DOC_COMMENT`/`T_CONSTANT_ENCAPSED_STRING`/`T_ENCAPSED_AND_WHITESPACE`；签名/参数解析（`paramsText`）不受影响。
- **public 门禁去宽泛词**：`PUBLIC_GUARD_EVIDENCE` 删除 `/TOKEN/`、`/Challenge/`、`/challenge/`、`/FORWARD/`、`/forbidden/i`、`/unauthorized/i` 等裸词，只保留调用形态（`Response(404/403`、`UpdateChallenge::`、`->answer(`、`DevBuild::enabled`、`getRemoteIp(`、`isLoopback`、`deny(`）。`GET /api/admin/v1/system/update/probe`（唯一合法 public）复验仍绿。

## 验证记录（全部实跑）

| 命令 | 退出码 | 关键输出 |
|---|---|---|
| `php tools/check-route-permission.php` | 0 | 契约 operation 101 条，生成路由 101 条；全部声明 x-permission 且 handler 有判定，无 dev 泄漏 |
| 负向1：删 `POST /files` 的 `x-permission` | 1 | `FAIL contract: host: POST /api/admin/v1/files 缺 x-permission` + `x-permission 缺失或形状非法`；已 `git checkout` 恢复 |
| 负向2：`route.generated.php` 尾部追加 `Route::any('/api/admin/v1/x', function () {});` | 1 | `route.generated.php:136 未识别的 Route::any 形态，门禁无法对账`；已恢复，复跑绿 |
| 负向3：`MeController::me` 注入 `// ->can(` 注释与 `'->can('` 字符串、契约 meShow 改 `super` | 1 | `x-permission=super…未见对应判定调用`（注释/字符串不计证据）；已恢复，复跑绿 |
| `cd server && vendor/bin/phpunit --testsuite unit` | 0 | `OK (741 tests, 6882 assertions)` |
| `cd server && vendor/bin/phpstan analyse --no-progress` | 0 | `[OK] No errors`（level max） |
| `bash tools/pre-merge.sh --fast` | 0 | 9/9 全过，含本门禁与命名自查 |
| `php -l tools/check-route-permission.php` | 0 | No syntax errors |
| naming_check code/notes/terms `--diff` | 0/0/0 | 初报 `$l` 单字母 WARN → 改名 `$openParen` 后清零 |

## 已知边界（如实声明）

- 门禁是「证据存在性」静态核对：能确认 handler 可达代码里出现权限/身份/超管/公开门禁调用形态，不能证明判定逻辑语义正确（如 `can()` 参数是否匹配契约权限码）。语义正确性由 Integration 测试与 IDOR 模板兜底。
- `routeCallSites` 识别 `Route` 短名/命名空间限定/全限定三种写法；生成器当前只产 `Route::` 短名形态，其他形态按设计判「未识别」而非静默跳过。
- 未建库、未启库、未碰 `web/`、未新增任何豁免/baseline 条目（dev-only-paths 的 `rm` 行是发布剥离清单，非豁免）。

## 合并前置

- #81（CI 门禁）合并后：`git merge origin/main` → CI `phpunit`/`phpstan` 双绿 → Fusion 审计 → 才可合。本任务按要求不合并。
