# PR #100 发版自检修复 — 结果记录

- 仓库：supdger/sandadmin，分支 feat/mcp-business-tools
- 基线：927b7f3（git fetch 后确认 HEAD 一致，工作树干净）
- 新提交：44aaff41fccca667b3c22a4702f0da13d8a7d75d
  - `fix(mcp): OrgActorSource 注释改为不点名 dev-only 类`
- 已推送：927b7f3..44aaff4 → origin/feat/mcp-business-tools（普通 push）

## 改动内容

`server/src/Infra/Mcp/Biz/OrgActorSource.php` 文档注释第 18、21 行：
`DevPrincipal`（dev-only 符号表内类名）改写为「开发期身份」，仅注释，代码零改动。

修复前 `php tools/check-dev-refs.php` 输出（退出码 1）：

```
FAIL 发现未登记的 dev-only 引用：
  server/src/Infra/Mcp/Biz/OrgActorSource.php:DevPrincipal@18
  server/src/Infra/Mcp/Biz/OrgActorSource.php:DevPrincipal@21
```

## 全仓复查

对 `server/src/` 用 dev-only 符号表全量 grep，其余命中全部落在
`tools/dev-only-paths.txt` 已登记项内：`rm` 整文件剔除（DevBuild.php、
DevPrincipal.php、DevTokenVerifier.php、DevCatalog、McpController、
McpGate、WindowRateLimiter、LogAuditSink、DevTaskActorProvider、
DevAnnouncementActorProvider、DevLifecycleActorProvider、Module/DemoNote 等）
或 `strip` 文件的 SAND-DEV-BEGIN/END 哨兵块内（TaskController、BizKernel、
LifecycleRuntime、AnnouncementRuntime、NotificationRuntime）。无未登记引用，
未往豁免/清单加任何条目。

## 验证命令与结果

| 命令 | 退出码 | 关键输出 |
| --- | --- | --- |
| `php tools/check-dev-refs.php` | 0 | `PASS 未发现未登记的 dev-only 引用（1437 个文件）` |
| `cd server && vendor/bin/phpunit --testsuite unit` | 0 | `OK (432 tests, 2868 assertions)` |
| `cd server && vendor/bin/phpunit --testsuite contract` | 0 | `Tests: 243, Assertions: 608, Incomplete: 100`（Incomplete 为环境依赖跳过） |
| `cd server && vendor/bin/phpstan analyse --no-progress` | 0 | `[OK] No errors` |
| `bash tools/pre-merge.sh --fast` | 0 | `pre-merge --fast 全部通过`（7/7 步全绿） |
| `naming_check.py notes --diff -` / `terms --diff -` | 0 | 无 FAIL/WARN，仅 INFO 注释占比 0.0% |

## 为什么本地 pre-merge --fast 没拦住

pre-merge.sh 第 4/7 步跑的就是同一个 check-dev-refs.php，跑过必拦；但它是
手动脚本：`.git/hooks/` 只有 sample、`core.hooksPath` 未设置、composer
脚本与仓库内无任何钩子/引用把它接进 commit 或 push 链路，推不推全看作者
是否手动执行。927b7f3 带着点名注释直接提交并推送，本地没跑（或跑时该注释
尚未写入），最终由 CI 的 release self-check（`build-release.sh --self-check`，
在剔除后的发布树上跑 `check-dev-refs.php --dir`）拦下。

## 边界确认

- 未改 tools/dev-only-paths.txt 及任何豁免、白名单、CI、门禁脚本；
- 未建库、未启动任何数据库（integration 套件未运行，--fast 本就不含）；
- 未合并、未强推、未 reset、未 --no-verify、未 git add -A；
- 未用云端 / Cursor / 下级会话；未读取或打印任何秘密。
