# 12x-addendum — PR #81 追加条款 RESULT

**载体**：PR #81 `feat/merge-gates`（supdger/sandadmin），保持 OPEN 未合并。
**新 head**：`2f93f05241cddb3818ff1b9128577d1724f96e62`（在 `f5c35c4` 之上一个提交 `2f93f05`，普通 push，无 force/reset/--no-verify，git add 均为具体文件）。
**远端**：`github`（https://github.com/supdger/sandadmin.git）。

## 任务 1：慢档三库矩阵调整

`.github/workflows/merge-gates-slow.yml`：

- 删除 `mysql:8.0` 档；保留 `mysql:8.4`（LTS，`known_failure: true` 槽位语义不变）。
- PostgreSQL 拆两档：`postgres:15`（最低版本）与 `postgres:17`（新主线，明确版本），均 `known_failure: false`。
- 矩阵每档新增 `family: pgsql|mysql` 字段，「应用仓内迁移」「判定集成测试库就绪」两处 step 的分支判断由 `matrix.db = "postgres-16"` 改为 `matrix.family = "pgsql"`，job 名/`continue-on-error: ${{ matrix.known_failure }}`/e2e 槽位约定不变。
- 注释同步更新（「MySQL 两档」→「MySQL 档」等）。
- 文档核对：README/RELEASE-CHECKLIST/docs 均无三库矩阵的具体版本描述，无需同步；`tools/pre-merge.sh --full` 的 `PRE_MERGE_DB=mysql80|mysql84` 是本地选项，与 CI 矩阵无关，未动。

## 任务 2：发版自检免登录入口门禁

- 新增 `tools/check-no-dev-login.php`：`--dir <发布包根>` 递归扫描**全部文件（含 vendor/plugin 载荷）**，符号表复用 `check-dev-refs.php` 的 `DEV_REF_PATTERNS`（dev-login 路由、`DevLogin*`、`DevPrincipal`、`devLogin`/`fetchDevLogin`、`devSurface`、mock worker、`SAND_DEV_*`/`SAND_MCP_DEV_TOKEN`、dev MCP 类等）。二进制文件按 NUL 字节跳过；白名单逐条注明理由（`ProductionDebugGuard.php` 启动守卫、`RELEASE-CHECKLIST.md`/`RELEASE-NOTES-0.2.4.md`/`web/README.md`/`tools/typephp-盘点.md` 门禁说明文、`web/vite.config.ts`/`web/eslint.config.js` 构建配置、`*.sand-core-payload-manifest.json`、`tools/` 目录段——与既有 vendor 剔除 grep 同口径）。
- `tools/build-release.sh`：在 step 4 剔除链 + `composer dump-autoload` **之后**调用一次（必须在类映射重建后，否则 classmap 里指向已删文件的 `DevLoginController`/`DevLoginGuard` 条目会误报——实测踩到并修正位置），step 7 `server/public` 前端产物并入后再扫一遍。
- `tools/dev-only-paths.txt`：登记 `rm tools/check-no-dev-login.php`（脚本自身含 dev 符号字面量，按 check-dev-refs.php 先例不进包；`check-dev-refs.php` 仓库侧扫描因此跳过它）。
- `tools/check-dev-refs.php`：`DEV_REF_WHITELIST` 增加 `RELEASE-CHECKLIST.md`（该文件随包发布且登记了断言本身的说明文字）。
- `RELEASE-CHECKLIST.md`：CI 门禁节新增免登录入口断言核对项。
- 本地实测：构造含 `dev-login` 的假包目录 → exit 1 并列出 `path:line pattern`；真实 `--self-check` 全包 8001/8012 文件 PASS。

## 任务 3：面向用户文字走语言包

调查结论：**语言包机制已存在**——`web/`（React 19）用 i18next，`web/src/i18n/locales/zh-CN/*.ts` 按命名空间分文件（`i18n.ts` 只认 zh-CN，fallback zh-CN）；`sandadmin-artd/`（Vue3）用 vue-i18n，`src/locales/langs/zh.json` + `en.json`。

本轮改动（workflow YAML、tools 脚本、清单、检查清单）**不产生任何产品用户可见文案**：tools/*.sh|php 的中文输出是开发者 CLI 输出，非产品 UI；workflow job 名是 CI 展示面；演示 PR 的探针文件为 dev-only 且随分支删除。因此无需新增语言包条目，也未引入任何用户可见中文字面量。

**存量记录（未动，如实上报）**：仓内硬编码中文存量很大——`web/src` 非 locale/非 mocks/非测试文件约 **201 个**含中文（多为注释，需逐条甄别是否用户可见）；`sandadmin-artd/src` 非 locale/非测试约 **308 个**；`web/src/mocks/**` 为 MSW mock 数据（dev-only，发布包剔除）。是否需要专项「硬编码中文 → 语言包」整改属另一工作包，本轮按要求未全仓扫改。

## 任务 4：PR #81 描述已更新

`gh pr edit 81` 已重写 body：修正 mysql:8.0→（已删，现 pg15/pg17+mysql8.4）、46 条→29 行、删除慢档 audit job（已并入 dependency-audit）、补四个必过 check 名、SBOM/provenance artifact、免登录断言、fail-closed 基线闸、M-27、已知槽位清单。旧 body 中「7 个快档 job」「慢档依赖审计」「本地 356 用例」等过时口径全部移除。

## 任务 5：baseline 闸 fail-open 修复

- 新增 `tools/check-baseline-shrink.sh <BASE> [文件...]`：判定口径收敛于此——`git rev-parse` 失败或 `git show BASE:tools/pre-merge.sh` 取不到/为空 → **判失败（fail-closed）**；BASE 脚本存在但不含 `strict-types-baseline` 字样 → bootstrap WARN 不拦；否则行数增长硬 fail。
- `tools/pre-merge.sh` 第 10 步委托调用并显式传三个受保护文件（**关键**：BASE 侧 bootstrap 判据 grep 的是 `pre-merge.sh` 本文，`strict-types-baseline` 字样必须留在该文件里，否则闸永远停在 bootstrap——已在注释与实参两处保证）。
- 新增 `tests/pre-merge-baseline-test.sh`（照 `tests/rollback-release-test.sh` 模式，临时 git 仓库 fixtures）：6 断言全过——BASE 缺 pre-merge.sh 判失败、BASE ref 不存在判失败、bootstrap 增长 WARN 放行、bootstrap 不增放行、闸生效后增长判失败、不增放行。
- 实跑验证：`bash tests/pre-merge-baseline-test.sh` → 6/6 PASS。
- 注意口径变化：本地 `origin/main` 指向 saithink 上游（无 pre-merge.sh），默认 `PRE_MERGE_BASE=origin/main` 现在会**判失败**（这正是 fail-closed 的目的）；CI 中 origin=github 故 `origin/main`=github/main（有脚本、无闸标记 → bootstrap WARN），快档 CI 已实测绿。`PRE_MERGE_BASE=github/main` 本地复跑快档除 typephp 噪音外全过。

## 改动文件清单（commit `2f93f05`，8 files，+302/−47）

- `.github/workflows/merge-gates-slow.yml`（矩阵调整）
- `tools/build-release.sh`（挂 check-no-dev-login 两处 + 注释）
- `tools/check-no-dev-login.php`（新增）
- `tools/check-baseline-shrink.sh`（新增）
- `tools/check-dev-refs.php`（白名单 +1）
- `tools/dev-only-paths.txt`（rm +1）
- `tools/pre-merge.sh`（基线闸委托）
- `tests/pre-merge-baseline-test.sh`（新增）
- `RELEASE-CHECKLIST.md`（核对项 +1）

## 验证命令与退出码

| 命令 | 退出码 | 备注 |
|---|---|---|
| `cd server && composer install` | 0 | 顺带改写 `server/webman`、`server/windows.php` 骨架，已 `git checkout` 还原未提交 |
| `vendor/bin/phpunit --testsuite unit` | 0 | 737 tests / 6877 assertions |
| `vendor/bin/phpstan analyse --no-progress` | 0 | 首跑因共享缓存 `/Users/supdger/.tmp/phpstan` 残留 11a worktree（已删 vendor）的 phar 路径报 internal error，`rm -rf` 该缓存后 0 错误——跨 worktree 共享 tmpDir 的环境坑，已在 RESULT 记录 |
| `tools/build-release.sh --self-check` | 0 | 全剔除链 + 免登录断言两遍 PASS（8001/8012 文件）+ SBOM/provenance 产出；首轮曾因检查点在 dump-autoload 前误报 classmap 残留、RELEASE-CHECKLIST 未白名单各 FAIL 一次，修正后绿 |
| `bash tests/pre-merge-baseline-test.sh` | 0 | 6/6 |
| `tools/pre-merge.sh --fast`（默认 origin/main） | 1 | 仅 typephp_check（35 条）+ 命名/terms diff 噪音——本地 `main`(a5dddc7)/`origin/main`(saithink 上游) 陈旧，diff 含整仓 fork 差异；且新 fail-closed 对缺 pre-merge.sh 的 origin/main 判失败（预期行为）。与 PR diff 交集为 0 |
| `PRE_MERGE_BASE=github/main tools/pre-merge.sh --fast` | 1 | 唯一 FAIL 仍是 typephp_check（它内部固定用本地 `main`/`origin/main`，不吃 PRE_MERGE_BASE）；命名三件套与基线闸（bootstrap WARN）全过 |
| `python3 tools/naming_check.py code/notes`（新文件） | 0 / WARN | check-no-dev-login.php 注释占比 17.1% WARN（门禁脚本文档密度，逐条说明：符号表与白名单理由必须写全） |

## CI 结果

- **#81 @ 2f93f05**（push + pull_request 双跑）：`phpunit`、`phpstan`、`security-regression`、`dependency-audit`（含新自检，全绿 1m8s/1m20s）、`快档：pre-merge --fast` —— 全部 SUCCESS。
- **演示 PR #119**：https://github.com/supdger/sandadmin/pull/119 （已关闭、远端/本地分支已删）
  - 注入 `server/src/Http/DevLoginProbe.php`（`DevLoginProbe` 类 + `'/dev-login'` 路径，未登记进 dev-only-paths，能穿过剔除链留在包里）。
  - **红 check 名：`dependency-audit`**（FAILURE，38s）——在 `Run release pipeline self-check` 步 `check-no-dev-login` 处判红：`FAIL 生产包残留免登录/dev 链符号：server/src/Http/DevLoginProbe.php:7 dev-login / :12 dev-login`。
  - `快档：pre-merge --fast` 同步判红（check-dev-refs 未登记 dev 引用，预期连带）；`phpunit`/`phpstan`/`security-regression` 绿。

## 遗留风险 / 存量

1. **PHPStan 共享缓存跨 worktree 污染**：`/Users/supdger/.tmp/phpstan` 被多个 worktree 共用，曾把 11a worktree（vendor 已删）的 phar 内部路径写进缓存导致本 worktree internal error；`rm -rf` 后恢复。CI 每 job 全新环境无此问题；本地多 worktree 并行跑 phpstan 需注意。
2. **本地 `main`/`origin/main` 陈旧**：typephp_check 与命名 diff 依赖本地 ref，本地复跑 `--fast` 的 FAIL 全部是陈旧基线噪音（CI 绿）；`origin/main` 指向 saithink 上游，建议本地用 `PRE_MERGE_BASE=github/main`（typephp_check 仍不吃该变量，属既有缺陷未动）。
3. **check-no-dev-login 口径**：`tools/` 目录段整体豁免（与既有剔除 grep 一致）；理论上 dev 入口藏进名为 tools 的目录可绕过，概率低且 dev-only-paths 门禁在源码侧另有把关。
4. **语言包存量**：web/src 约 201、sandadmin-artd/src 约 308 个非 locale 文件含中文（多为注释/存量文案），未专项甄别用户可见性，未动。
5. 慢档矩阵调整只改了 workflow；慢档不挂 PR，真实跑要等 main push/定时/手动触发，本轮未实跑三库（CI 慢档历史从未触发过，沿用既有口径）。
