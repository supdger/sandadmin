# 4a 修 PR #104 测试门禁 — RESULT

- 仓库：supdger/sandadmin（本机 `~/agent-work/pub-sandadmin`）
- 分支：`feat/test-gate`，基线 SHA `ee08b60cc48fcd388e035eb124b772a8186e1b31`
- 最终 SHA：`8530ce26e5b0ddc992a5bf1a06c951dacc62e788`（ee08b60 上一个新提交）
- PR：https://github.com/supdger/sandadmin/pull/104（目标 main，未合并）
- 工作方式：`git worktree` 隔离在 `~/agent-work/assistant/4a-fix-pr104-test-gate-1119/repo`（主仓当时有并行任务在别的分支）；全程无库、无 CI 改动、无豁免/白名单新增。

## 三件事的落点

### 1. 发布产物排除 web/e2e 与 playwright 配置

`tools/dev-only-paths.txt`（打包产物清单，发布剔除唯一权威）web/ 段新增两行：

```
rm web/e2e
rm web/playwright.config.ts
```

`build-release.sh` 第 2 步按清单物理剔除，第 6 步 `strip-dev.php --verify` 断言清单路径不存在。本次 `--self-check` 日志显示 `rm web/e2e/`、`rm web/playwright.config.ts` 已应用（清单共 194 项），第 6 步 `PASS 清单所列路径均不存在`。剔除后 web/ 前端 `pnpm typecheck` + `pnpm build` 在第 5 步照样通过（tsconfig include 只含 `src` 与 `vite.config.ts`，不引用 e2e）。

### 2. 四个模块的真实 Unit 测试（全部 sqlite 内存库 + `tests/Support` 夹具，不连库）

新增公共夹具：`tests/Support/LifecycleFixture.php`（生命周期表最小建表 + 一行默认值）、`tests/Support/NotificationFixture.php`（通知/公告/部门范围/已读四表）。操作人对象由 `tests/Support/Factory/UserFactory` 造 `User` 值对象再装配 `OrgActor`/`AnnouncementActor`。

各模块覆盖内容（断言落可复核形状：错误码、字段键、行值、阶段序列）：

| 模块 | 文件 | 测了什么 |
|---|---|---|
| Dict | `DictExceptionTest` | 8 个异常工厂的 status/errorCode/fields/message |
| | `DictServiceTest` | listTypes 的 source 三种（system/plugin/custom + plugin_label 查表与幽灵编码兜底）与 item_count；showType 的 used 三态（有引用 true/自建 false/插件组无引用 null）；createType 写行+审计（before/after changes JSON）；name/remark 长度与缺省校验（OrgException/DictException 分档）；updateType；removeType 拒删系统组与插件组；createItem 校验（label 长、色板外、is_default 非布尔、sort 非法）、新默认清旧默认、updateItem 不带 status 沿用库值（keptStatus）；setItemStatus 启停；removeItem 拒删系统项；sortItems 重排 |
| Lifecycle | `LifecycleRequestTest` | visibleTo（本人/陌生人/viewAll）；toApi 完整形状：public_id 对外、枚举序列化、cancelled 态原样带出、items 透传、DATE_ATOM 时间 |
| | `LifecycleHttpTest` | show：401 未登录、400 非 uuid、404 不存在、404 陌生人（与不存在同口径）、200 本人、200 viewAll；信封 code/request_id/fields |
| | `LifecycleRepositoryTest` | findByPublicId 水合：items JSON 解码（非对象条目丢弃、缺 state 落 queued）、空串 items→null、未登记 stage_key→tryFrom null、枚举与布尔归一 |
| | `UnavailableLifecycleActorProviderTest` | 默认实现恒返回 null |
| Notification | `NotificationExceptionTest` | 3 个工厂 + 默认 message/fields |
| | `NotificationRepositoryTest` | countUnread=未读通知+可见未过期未读公告合计；dept 范围公告按部门过滤（deptId null 只看 scope=all）；draft/过期/已读不计；markOneRead 盖 read_time、已读行算找到且不覆盖、他人/不存在 false；markAllRead 通知全标+公告按范围记已读行、他人不受影响、ON CONFLICT 幂等 |
| | `NotificationHttpTest` | unreadCount 信封；index/markRead/markAllRead 未登录与 userId≤0 401；markRead 编号正则（非数字/0/19 位上限外）与幂等键正则 400 字段；不存在 404；markAllRead 幂等键校验与信封；failure 信封 |
| Package | `PackageGateTest` | APP_ENV/SAND_COMPILED/SAND_PACKAGE_OPS 环境矩阵：production 定义、SAND_COMPILED 未设/空串按编译版、'0' 才关、生产无视 SAND_COMPILED=0、opsEnabled 双条件；setUp/tearDown 备份恢复环境变量 |
| | `PackageOpsTest` | overview 三键；market 关闸空列表/开闸目录项字段（installed/upgrade_available/task_state 带运行中申请）；installed 过滤；showPlugin 200/404；install 守卫顺序（401→403 非超管→409 忙）与关闸文案；install 未知码 404、已装 409；upgrade 404/未装 409/已最新 409；uninstall 未装 404；upgradeAll 无可升级 409；upload 拒 .phar/.so/大小写/compiled 标记/前 512 字节 NUL（403）、非 JSON 与未知码 422、已装码 409；advance 非法编号/无行/他人 executor/关闸全部 no-op、plugin_install 进度梯 30/60/90、done 后契约收集判冲突标 failed（error_code=PLUGIN_CONTRACT_CONFLICT、rolled_back=1）、system_upgrade 五阶段 check/backup/download/switch/verify |

方言边界（诚实留 Integration，注释逐处写明）：Dict 的 `ILIKE` 关键词与唯一约束名映射（sqlite 报列名不报约束名）、Notification `listInbox` 的 `::text` 转换、Package `enqueue` 的 `RETURNING public_id::text`、`updateStatus` 的 `public_id::text`、`apply` 的 `CAST AS jsonb`。sqlite 3.53.4 实测 `::`/`jsonb` 语法不通过。

布尔列约定：夹具行写整数 1/0（服务归一口径是 true/'t'/1/'1'，sqlite 存 'true' 文本不归一），服务写入 'true'/'false' 文本的路径不在 sqlite 断言真值（`testCreateItemClearsPreviousDefault` 只断言旧默认被清，新行真值归 Integration）。

### 3. test-gate.json

`diff_line_coverage_min: 80` 不变，新增 `diff_line_coverage_min_note: "已定（技术总监口径）"`——原「待总监确认」只是 2c 交付备注与提交说明里的口径，JSON 无可写注释位，用同键名 `_note` 字段落「已定」；`check-diff-coverage.php` 只读 `diff_line_coverage_min`，多余键不影响。

## 命令、退出码与关键输出

| 命令 | 退出码 | 关键输出 |
|---|---|---|
| `php tools/check-module-tests.php` | 0 | `模块测试目录检查：8 个模块` / `全部模块在 Unit 与 Integration 套件下均有测试目录` |
| `vendor/bin/phpunit --testsuite unit` | 0 | `OK (501 tests, 2991 assertions)`（新增 88 用例） |
| `vendor/bin/phpunit --testsuite unit --filter 'Module\\(Dict\|Lifecycle\|Notification\|Package)'` | 0 | `OK (88 tests, 321 assertions)` |
| `vendor/bin/phpstan analyse --no-progress` | 0 | `[OK] No errors`（level max，含全部新测试） |
| `bash tools/pre-merge.sh --fast` | 0 | 8 步全过：`== 1/8 模块测试目录检查` … `== 8/8 PHPStan` → `pre-merge --fast 全部通过` |
| `bash tools/build-release.sh --self-check` | 0 | 第 2 步 `rm web/e2e/`、`rm web/playwright.config.ts`、`PASS 清单已应用（194 项）`；**第 6 步** `PASS 清单所列路径均不存在` + `PASS vendor 无非运行文件残留（豁免 11 项）` + `PASS 类映射 5030 条全部指向存在的文件`；第 7/8 步 PASS → `OK 自检通过（sandadmin-0.2.4-49-gee08b60，未产出制品）` |
| `cd web && pnpm test:smoke` | 0 | `ok /`、`ok /system/user` … `ok /plugin/sand-hello` 13/13 页，`1 passed (21.1s)` |
| `php tools/check-dev-refs.php` | 0 | `PASS 未发现未登记的 dev-only 引用（1435 个文件）`（pre-merge 第 5 步同结果） |
| `python3 ~/agent-work/grokbot/devin-queue/naming_check.py code <新文件>` | 0 | 无 FAIL/WARN（曾 2 条单字母 WARN 已改名消除） |
| `python3 ~/agent-work/grokbot/devin-queue/naming_check.py notes <新文件>` | 0 | 仅 INFO：注释占比 2.1% |

## 环境事实

- 本机 PHP 8.4.18 + pdo_sqlite 3.53.4；`RETURNING`/`ON CONFLICT DO NOTHING`/`FALSE` 字面量在 sqlite 可用，`::` 转换与 `CAST AS jsonb` 不可用（实测）。
- worktree 内 `composer install` 会重写 `server/webman`、`server/windows.php` 骨架，已 `git checkout` 还原，未进提交。
- 主仓工作区当时有并行任务（3n）在 `feat/ai-loop-rollback` 上写同主题 Dict 测试草稿（未跟踪文件），本任务全程在独立 worktree 操作，未触碰其文件。

## 剩余项

- `--full` 的覆盖率段未跑：本提交只动 tests/Support 与 tools 清单，`check-diff-coverage.php` 对 `server/src` 无改动行会跳过；pcov 环境口径同 2c。
- PG 方言路径（ILIKE、`::text`、`CAST AS jsonb`、约束名映射、is_default 'true' 文本回读）按仓内分工归 Integration 套件，需 `SAND_TEST_DB_URL` 才跑，本任务无库不验。
- 提交未推送（任务未授权推送）；PR #104 分支新 SHA 待推送后反映。
