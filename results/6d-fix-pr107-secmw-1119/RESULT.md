# RESULT — 修 PR #107 HTTP 安全中间件（site_origin 生产拒启 + CSRF_REJECTED 补登）

日期：2026-11-19　仓库：supdger/sandadmin　分支：`feat/http-security-mw`
基线 SHA：`977c396721e4ad475d85f111cbbe0b45b59ae89a`（PR #107 原 head）
最终 SHA：`c8fc46a11a22cf05e992746bd12988abb26385aa`（已普通 push 到 origin，未合并）
工作副本：`~/agent-work/assistant/6d-fix-pr107-secmw-1119/sandadmin`（新克隆，composer install 后 `git restore server/webman server/windows.php` 还原理架覆盖）

## 变更内容

1. **CSRF_REJECTED 补登契约**：`contract/openapi.yaml` `x-error-catalog` 新增
   `CSRF_REJECTED {http_status: 403, summary: 请求未通过来源安全校验, common: true, note: 全局 CsrfMiddleware 对 /api/admin/v1 写操作发出}`；
   通用码注释 4→5，`contract/api-conventions.md` 同步。派生产物全部由
   `php tools/contract-build` 重新生成（ErrorCodeRegistry 增常量/HTTP_STATUS/ALL/COMMON，
   contract.generated.ts 增联合成员，其余产物仅源哈希行变化），不手改。
2. **生产 site_origin 拒启**：新增 `server/app/bootstrap/SiteOriginGuard.php`
   （参照 ProductionDebugGuard：`assertConfigured` 纯判定 + `start` 内
   stderr 打印 + `haltMaster` + 重抛；`?array $securityConfig`/`?callable $halt`
   测试注入，先例 LogRetentionGuard）。注册进 `server/config/bootstrap.php`
   LogRetentionGuard 之后。判定：APP_ENV=production|prod 且 site_origin 为 null
   → RuntimeException 拒启；形态非法由 CsrfConfig 抛 InvalidArgumentException
   同路拒启；开发环境/无 APP_ENV 放行，中间件原行为不变（未配置跳过源校验、
   标记头仍必查）。`server/config/security.php` 与 `server/.env.example`
   注释同步为强制口径。
3. **测试**：`server/tests/Unit/App/SiteOriginGuardTest.php` 7 用例——
   production/prod 别名缺配置拒启+halt、生产已配置放行、development 与无
   APP_ENV 放行、非法形态拒启、bootstrap 注册断言。
4. 全局挂载 + 中间件内按 `/api/admin/v1` 前缀自限作用域的做法未动
   （`server/config/middleware.php`、`server/config/route.php` 无改动）。

## 门禁结果（逐条）

| 命令 | 退出码 | 关键输出 |
| --- | --- | --- |
| `python3 tools/naming_check.py code server/app/bootstrap/SiteOriginGuard.php server/tests/Unit/App/SiteOriginGuardTest.php server/config/bootstrap.php server/config/security.php contract/openapi.yaml` | 0 | 无 FAIL/WARN 输出 |
| `python3 tools/naming_check.py notes <同上>+server/.env.example` | 0 | INFO 注释占比 *.php 13.9%（23/166），*.example 0.0%，无 FAIL/WARN |
| `python3 tools/naming_check.py terms tools/naming-terms.tsv SiteOriginGuard.php SiteOriginGuardTest.php openapi.yaml api-conventions.md` | 0 | 无 FAIL/WARN 输出 |
| `cd server && vendor/bin/phpunit --testsuite unit` | 0 | `OK (470 tests, 2731 assertions)`（PHPUnit 11.5.57 / PHP 8.4.18）；新用例 7 个含在内，stderr 可见拒启文案 |
| `cd server && vendor/bin/phpstan analyse` | 0 | `[OK] No errors`（phpstan.neon.dist，451 文件） |
| `php tools/contract-build` | 0 | `PASS 契约构建完成（19 个产物，100 个 operation）` |
| `php tools/contract-build --check` | 0 | `PASS 产物与契约源一致` |
| `bash tools/pre-merge.sh --fast` | 0 | 7/7 全过：PHP 语法、契约一致、危险函数白名单、dev-only 引用、TypePHP diff（FAIL=0 WARN=0）、PHPUnit Unit（470/2731 OK）、PHPStan（No errors），结尾 `pre-merge --fast 全部通过` |

## 提交

`git push origin feat/http-security-mw`：`977c396..c8fc46a` 普通推送成功，未合并 PR。

## 说明

- 未建库、未启任何数据库、未改 CI/门禁脚本、未加白名单/豁免条目、无强推、
  无 reset --hard、无 --no-verify、无 git add -A（逐文件 add）。
- composer install 用骨架覆盖了 `server/webman`、`server/windows.php`
  （文件内注释已注明该行为），已 `git restore` 还原，未入提交。
