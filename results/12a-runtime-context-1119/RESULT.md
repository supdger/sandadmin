# 12a：3I-5a 常驻内存安全基座（队列 1-4，M-11/M-12/M-13 非库部分）

日期 2026-11-19。交付物：**未合并 PR #114**（supdger/sandadmin），分支 `feat/runtime-context`。

- 基线 SHA（origin/main）：`5360129459241f4e0531fe3483f7c1f19119f7cd`（Merge PR #110）
- 实现提交：`08274d632e8a2b191b8597fa4b1de0fa3af02ee0`（52 files，+1205/−89）
- PR：https://github.com/supdger/sandadmin/pull/114 （state=OPEN，不合并；合并前置：#81 仍 OPEN，只卡合并不卡开工）
- #109 清单 skill 已合并进 main（e325e0e），清单编号按 main 上版本回答。

## 需求逐项回答（#109 清单编号）

| 编号 | 条目 | 本 PR 做了什么 | 剩什么（后续队列行） |
|---|---|---|---|
| P6 | 常驻内存安全 | `Domain/Runtime/ContextStoreInterface` + `Infra/Runtime/ScopedContextStore`（Webman\Context 纤程作用域，每请求隔离）；`RuntimeGuardMiddleware` 注册为全局最外层，`finally` 中 `assertResidueFree` 断言无残留、抛 `ContextResidueException`；web 组 `max_requests=10000`、ai_stream 组 0；`runtime.memory_watermark_bytes`（默认上限 80%，无上限兜底 256MiB，0 关闭）+ `ProcessRecycle` 软回收（计数/水位 → SIGUSR1 → workerman 平滑重启）；PHPStan `ResidentStateRule` 禁可变 static/`$GLOBALS`（ALLOWLIST 逐条登记理由）；`StartupConfigValidator`+`RuntimeConfigGuard` 启动拒启 | 24h 浸泡 RSS ≤10% 门禁在 3I-7；连接复用/残留事务断言在 3I-5b（需库）；规则未覆盖函数内 `static $x`、匿名类，豁免属性只读约定待后续工单 |
| H4 | 健康检查 | `/health/live` 已实现：contract/openapi.yaml 声明（x-permission: public）、`HealthController`、route.generated.php、语言包 `resource/translations/zh_CN/health.php`，实测 200 | `/health/ready`（查 DB/Redis/迁移版本）属 3I-5b（需库，队列 2-2） |
| H5 | 优雅停机 | 前置部分：web 组 max_requests=10000（HR6-a）、内存水位软回收 | stop_timeout=30s、onWorkerStop 排空、发版序列脚本属 HR6-b（队列 1-14）；kill -9 验收属 HR5-b（队列 1-13） |
| D2 | 连接池 | 未做（本任务只做 3I-5a 不用库部分） | 连接复用归 3I-5b / HR7-a（需库，队列 2-2） |

对应计划行：M-11（P2/P6/S10 部分）、M-12（HR5-a live 部分）、M-13（HR6-a 部分）。3i §8.3 十条规矩中本 PR 只落「可变静态/全局状态」与「请求上下文隔离」相关条款；禁业务层引用 `Workerman\` 的 deptrac 规则不在本任务范围。

## 验收硬指标复核（独立验收代理 dv-verifier 角色，subagent_general 新上下文，判定：通过）

| 指标 | 结果 | 证据 |
|---|---|---|
| a) 故意跨请求残留用例被断言拦下 | ✅ | `tests/Integration/Http/RequestPipelineTest.php::testResidueLeftInTheContextStoreFailsTheRequest`：往 `ScopedContextStore` 写 `leaked_probe`，收尾抛 `ContextResidueException`（含键名）；断言在 `RuntimeGuardMiddleware::process` 的 `finally` 真实执行。`vendor/bin/phpunit --filter …` → OK (1 test, 3 assertions) |
| b) PHPStan 规则对探针判红 | ✅ | `cd server && vendor/bin/phpstan analyse app/phpstan` → exit 1，2 errors 指向 `app/phpstan/ResidentStateProbe.php:18`（可变静态属性）、`:23`（`$GLOBALS`）；探针目录不在 phpstan paths 内，常规 analyse 0 errors |
| c) /health/live 实测 200 | ✅ | `SANDADMIN_SERVER_PORT=19151 SANDADMIN_WEB_WORKERS=1 SANDADMIN_TASK_WORKERS=1 SAND_IDGEN_NODE_RANGE=1-32 SAND_IDGEN_NODE_ID=1 SAND_IDGEN_MAX_CLOCK_BACKWARDS_MS=10 php start.php start` → `curl -i /health/live` → `HTTP/1.1 200`，body `{"status":"ok","message":"服务存活"}`（语言包生效）；`php start.php stop` 后端口释放 |
| d) 本地门禁 | ✅ | 见下方命令记录 |

## 门禁命令与结果（server/ 下，除注明外）

| 命令 | 退出码 | 关键输出 |
|---|---|---|
| `composer install` | 0 | vendor 齐全；`composer update --lock` 的 sand:publish 副作用已 `git checkout` 还原未入库 |
| `vendor/bin/phpunit --testsuite unit` | 0 | OK（751 tests, 6902 assertions） |
| `vendor/bin/phpstan analyse` | 0 | 0 errors |
| `tools/pre-merge.sh --fast`（仓根） | 0 | 8/8 全过（契约一致性、dev-only、TypePHP diff FAIL=0、命名自查等） |
| `python3 tools/naming_check.py code/notes/terms --diff` 及新增文件 | 0 | exit 0；WARN：注释占比/超长提示级（验收复核为提示级，非阻断） |
| Contract 套件 | 0 | 255 tests OK（102 incomplete 为既有 DB 门控）；Integration 套件需 `SAND_TEST_DB_URL` 连库，按约束未跑 |

## 裁决项（验收代理结论）

1. **PHPStan ALLOWLIST 扩到 5 处既有可变静态（6 属性）**：可接受、非违规。3i §8.3 只点名 2 个，但 main 实际有 5 个类带可变静态（`SnowflakeRuntime::$generator`、`StreamRuntime::$supervisors/$metrics`、`WeakPasswordRuntime::$list`、`SseDemoController::$sharedChannel`、`MemoryPluginRegistry::$locks`），逐个核实均为启动期挂载一次的进程级资源/句柄；不登记则 phpstan 必红、等于要求本 PR 顺手重构既有代码，超范围。规则对其余代码真实判红。**遗留建议**：`MemoryPluginRegistry::$locks` 是最弱一条（可增长 map）；ALLOWLIST 按属性豁免，将来往豁免属性塞请求态不会被拦，后续工单可加「豁免属性只读」约定或收窄检查。
2. **`web/src/api/v1/contract.generated.ts` 改动**：确认是 contract-build 强制再生产物（文件头 `<contract-generated>` + 源哈希与 openapi.yaml/route.generated.php/全部 Contract Generated 测试一致），契约一致性门禁要求入库；非手写前端改动。
3. ContextStore/请求末断言为真实实现（webman `App::onMessage` 每请求 `Context::reset`、`App::send` 时 `Context::destroy` 提供底层隔离，middleware 在 finally 断言）。
4. 水位默认推导符合约定（`ProcessRecycle::deriveWatermark`：取 profiles memory_limit_bytes 最大值 → 兜底 256MiB → ×80%）。
5. 启动校验真实拒启（实测注入 `memory_watermark_bytes=-1` → STDERR 报错 + 异常）。
6. 面向用户文字走语言包 zh-CN；新增中文仅在内部异常消息，500 对外统一文案不泄漏。

## 违规检查

未发现：无连库、无手写前端改动、无 baseline/豁免文件新增（`naming-allow.txt` 未动）、`server/phpstan` 与 `server/app/phpstan` 已登记 `tools/dev-only-paths.txt` 不进发布包、composer.json 仅 autoload-dev 增加 `Sandadmin\PHPStan\`（生产 --no-dev 不受影响）、单 commit 规整、未合并。

## 遗留与边界

- `/health/ready`、连接复用、残留事务断言 → 3I-5b（需库，队列 2-2）。
- systemd 模板 + kill -9 验收 → 1-13；stop_timeout/排空/发版序列 → 1-14。
- PHPStan 规则未覆盖：函数内 `static $x`、匿名类、豁免属性写入侧收窄（验收非阻断建议）。
- `RuntimeGuardMiddleware` 的 `finally` 中残留异常会掩盖下游原始异常（fail-closed，可接受，排障注意）。
- 无 `.env` 时启动需 `SAND_IDGEN_NODE_RANGE` 等发号器环境变量（既有 IdGeneratorGuard 要求，非本次新增）。

## 模型记录
- 主 Agent：SWE-2（本会话模型）
- 子 Agent：dv-implementer-sonnet = 请求 → 实际不可用（model no longer available）；dv-implementer = 请求 → 实际不可用（同上）；dv-scout 探针 = 请求 → 实际不可用（同上）；subagent_general（实现，agent ecfd47aa）= 跟随主模型 SWE-2 → 完成；subagent_general（独立验收，agent c200d5f1）= 跟随主模型 SWE-2 → 完成。dv-* 档案全部报 model unavailable，按"不用 Claude、能力不够停下报告"约束改用跟随主模型的内置档案完成，未使用任何 Claude 模型。

## 任务记录
- 已修改：supdger/sandadmin 分支 feat/runtime-context（提交 08274d6，52 files）；本目录新增 RESULT.md
- 已验证：phpunit unit 751 tests OK、phpstan 0 errors、pre-merge --fast 8/8、naming exit 0、残留用例与 PHPStan 探针判红、/health/live 19151 端口实测 200（独立验收代理复跑确认）
- 已提交/已部署：已提交并 push 分支、已开 PR #114（未合并、未部署）
- 未完成：合并需等 #81 合并后 merge main + CI phpunit/phpstan 绿 + Fusion 审计（本任务不合并）；/health/ready 与连接复用属 3I-5b
