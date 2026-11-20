已合并｜PR #105 与 main 冲突已解决并通过检查｜新 head：e581f9937f7a8b75b866944cb8b5f77e60f9ab99｜main 新 SHA：b8cfcfa8913eeff83acc8cf3a31a78a649f39fd5

# PR #105 解冲突合并结果

- 仓库 / PR：supdger/sandadmin #105，`feat/id-generator` → `main`
- 工作副本：`~/agent-work/assistant/2e-id-generator-1119/sandadmin`
- 操作：`git merge origin/main`（未 rebase、未强推），解冲突只做合并取舍，未动发号器逻辑

## 冲突文件与解决方式（2 个文件，均为同位置追加冲突，两段都保留）

1. `server/.env.example`：main 在 `SAND_WEAK_PASSWORD_PATH=` 后追加了 A-5 本站源/CSRF/CORS、N3 安全响应头、N5/M11 字段加密密钥三段注释块；本 PR 在同一位置追加 `SAND_IDGEN_*` 三项。解决：main 的三段在前、idgen 段在后，全部保留。
2. `server/config/bootstrap.php`：main（PR #107）在 `LogRetentionGuard` 后加了 `SiteOriginGuard`，本 PR 在同一位置加了 `IdGeneratorGuard`。解决：两个 Guard 都保留（SiteOriginGuard 在前、IdGeneratorGuard 在后）。

核对：`git diff origin/main..HEAD` 对这两个文件为纯新增（只加 idgen 的 env 段和 IdGeneratorGuard 一行），main 侧内容零删减；发号器逻辑无任何改动。

## 检查结果

- `server/ vendor/bin/phpunit --testsuite unit`：OK（737 tests，6877 assertions）
- `vendor/bin/phpstan analyse`：OK，No errors
- `tools/pre-merge.sh --fast`：全部通过（8/8）

说明：首次跑 pre-merge 时第 6 项 TypePHP diff 扫描误报 `server/config/database.php` 两处 FAIL——原因是本地 `main` 引用停在旧基线 977427a，扫描把 main 新增提交也算进了分支 diff；已将本地 main 快进（fast-forward）到 origin/main 05efc92 后重跑，全绿。未改门禁脚本与白名单。

## 合并结果

- 分支新 head：`e581f9937f7a8b75b866944cb8b5f77e60f9ab99`（merge commit，普通 push）
- `gh pr checks 105`：6 项全 pass（composer audit / frontend prod audit / release self-check，push 与 pull_request 两次运行均绿；runs 44653356099、44653361255）
- `gh pr view`：mergeable=MERGEABLE、mergeStateStatus=CLEAN
- `gh pr merge 105 --merge`（未删分支）：MERGED @ 2026-11-20T01:25:29Z
- main 新 SHA：`b8cfcfa8913eeff83acc8cf3a31a78a649f39fd5`

## 边界

未建库、未启动数据库（仅跑 Unit 套件，Integration 需 SAND_TEST_DB_URL 未跑）、未改 CI/门禁/豁免/白名单、未强推、未用 --admin。未删 feat/id-generator 分支。
