# RESULT — 1.x 编号策略之雪花发号器（不含节点租约表），无库

日期：2026-11-19　仓库：supdger/sandadmin　分支：feat/id-generator
基线 SHA（origin/main）：`977427acc285da074a443e8206309289a9bd94fb`
最终 SHA：`a7067c35afd4ed6a4c82f29c0020f6504f498038`
PR：https://github.com/supdger/sandadmin/pull/105 （目标 main，未合并，MERGEABLE）

## 位布局与回拨处理依据

| 决策 | 依据 |
| --- | --- |
| 41 位毫秒 + 13 位节点 + 9 位序列（共 63 位，正数 < 2^63）；每进程每毫秒 512 号 | v4 J-3 节（`顶层架构设计-v4.md:1281` 附近）：「位布局定为 41 位毫秒 + 13 位节点（8,192 个）+ 9 位序列」；L605 编号策略落地行、L212 正文同口径 |
| 纪元 2026-01-01 UTC（epoch ms 1_767_225_600_000） | 仓内 `.agents/skills/sandadmin1x-backend/references/database.md:41`（PR#98 内容已并入 main，为仓内唯一给出纪元处） |
| 节点号按部署分段，`idgen.node_range` 声明号段、`idgen.node_id` 写死节点号；启动自检不在号段内拒绝启动 | v4 J-3（:1281 附近「号段分配写在部署配置里……`idgen.node_range` 声明……启动自检不在本部署号段内拒绝启动」）；database.md:46 钦定配置键名 |
| 时钟回拨：<阈值等待时钟追上再发号；≥阈值拒绝发号并告警；阈值做配置项 | v4 J-3 节点登记表子节：「小于阈值先等待到时钟追上再发号；超过阈值拒绝发号并报警（阈值做配置项，默认值待技术总监定）」 |
| 阈值 `idgen.max_clock_backwards_ms` 必填、无编造默认值、未配置 fail-closed 拒启 | v4 明确「默认值待技术总监定」，未找到明确毫秒数 → 不自行拍板，列为待决项 |
| 接口输出字符串防 JS 精度丢失；nanoid 单列不当主键；可预见 int 自增 / 不可预见雪花 bigint | 总表第 17 条（`老板已定事项总表.md:28`）；v4 :1274、:212；database.md:42–45 |
| 不用 godruoyi/php-snowflake（已装未用） | 源码核对（lock 3.2.3）：位布局 41+5+5+12 硬编码、`self::` 常量绑定子类不可覆盖、节点超界被 `random_int` 静默随机化、无回拨检测 → 无法配置成 41+13+9，自写 |
| 两类表逐表归类以《编号两列表-修订》为准 | `idlist-tech-followup-1118/idlist-approved-sync-1118-readonly/`：2026-11-18 老板批准 210 张（自增 68/雪花 142） |
| 节点租约表 `sand_system_id_node` 不在本任务 | v4 :658 明确「编号行 0.5–1 只含发号器与两类表约定，不含本项」 |

## 改动范围（14 个文件）

新增：`server/src/Infra/Id/`（SnowflakeGenerator、SnowflakeConfig、SnowflakeRuntime、SnowflakeConfigException、ClockBackwardsException）、`server/app/bootstrap/IdGeneratorGuard.php`、`server/config/idgen.php`、`server/docs/id-generator.md`、`server/tests/Unit/Infra/Id/`（2 个测试类）、`server/tests/Unit/App/IdGeneratorGuardTest.php`
修改：`server/config/bootstrap.php`（登记 Guard）、`server/.env.example`（3 个 `SAND_IDGEN_*` 空占位，注释标阈值待决）、`server/phpstan.neon.dist`（paths 仅 +1 行登记新 Guard——原有 Guard 逐文件登记，属扩大 max level 覆盖面、无放宽）
未触碰：contract/openapi.yaml、现有插入代码、composer.json/lock、tools/、CI、任何迁移/DDL；无库无 Redis。

## 验证（命令 → 退出码 → 关键输出）

| 命令 | 退出码 | 结果 |
| --- | --- | --- |
| `cd server && composer install` | 0 | vendor 装好 |
| `vendor/bin/phpunit --testsuite unit` | 0 | 426 tests / 3674 assertions OK；新增 Id/Guard 测试 `--filter` 复跑 18 tests / 1061 assertions OK |
| `vendor/bin/phpstan analyse` | 0 | level max，449 文件，No errors |
| `bash tools/pre-merge.sh --fast` | 0 | 7/7 步全过（php -l、contract-build --check、危险函数扫描、check-dev-refs、typephp diff、phpunit unit、phpstan） |
| `python3 tools/naming_check.py code|notes|terms`（新增文件及 --diff） | 0 | 0 FAIL / 0 WARN |
| typephp | 0 | 由 pre-merge --fast 的 typephp_check diff 模式覆盖通过 |
| 验收方手工 `php -r` 实测 | — | 纪元实算一致；发号 decode 与 `(delta<<22)|(node<<9)|seq` 手算一致；回拨 4ms（阈值 10）等待后无告警、seq 连续；回拨 20ms 抛 `ClockBackwardsException` 且告警回调触发 1 次 |

独立验收（dv-verifier-swe，未参与实现上下文）：契约 9 条全 PASS，总结论**通过**；phpstan.neon.dist 改动核对属实（仅 +1 行登记、无豁免/降级）；PR OPEN/base main/head=a7067c3/未合并。

## 剩余项（明确不做 / 待决）

1. **回拨阈值默认值待技术总监定**（v4 原文）：当前 `SAND_IDGEN_MAX_CLOCK_BACKWARDS_MS` 必填、缺失拒启；需拍板后给出部署默认。
2. **webman 多 worker 共享 node_id 的同毫秒重号风险**：同一部署内各 worker 进程共用配置节点号，并发发号可能重号；「按进程细分节点号」属规格层待决（方案乙已被否、租约表未做），已写入 docs/id-generator.md 末节与 PR 剩余项。
3. 节点租约表 `sand_system_id_node`、租号/心跳续期/过期回收（v4 :658 独立条目）。
4. 外键同型静态检查（需读库结构）、PG/MySQL 两库测试。
5. 死依赖清理建议：godruoyi/php-snowflake 基线即无引用，建议后续切片移除（本次未动 composer 文件）。
6. 非阻断观察：SnowflakeRuntime::mount() 重复挂载以最后一次为准，建议后续改告警或拒绝。

## 未定事项回查

- 回拨阈值默认值：见剩余项 1（v4 明示待技术总监定，未拍板）。
- 任务列出的其他未定事项（国际化、workflow 归属、MySQL 最低版本、双因素适用范围、CLA/DCO）本切片未触及。
