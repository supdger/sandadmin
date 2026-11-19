改后可合并

# PR #105 合并前独立审计结论

- 仓库：supdger/sandadmin（本地 `/Users/supdger/agent-work/assistant/2e-id-generator-1119/sandadmin`）
- PR：#105 `feat(idgen): 雪花发号器 + 两类主键约定文档 + 启动自检`，`feat/id-generator` → `main`，OPEN
- **审计时 head SHA：`d44a048450370ac5a2bec78b2e9d70f298d82196`**（与派单 d44a048 一致；本地分支与 origin 同指向，工作树干净）
- 基线：`977427a`；2 个提交（a7067c3、d44a048）；18 个文件，+1234 / −0，无 lockfile 改动
- 审计方式：只读。读 diff、跑只读命令都交给本机 SWE-2 只读子代理（dv-scout），主代理只判断、写结论。没有合并、推送、评论，也没有改仓库文件。

## 必须修改点（改完即可合并）

1. **环境变量 `SAND_IDGEN_NODE_ID=0` 被当成"未填"，服务拒绝启动**（已复现）
   - 位置：`server/config/idgen.php` 三项都写成 `env('SAND_IDGEN_…') ?: ''`。`env()`（illuminate `Env::get`）在值为 0 时返回字符串 `'0'`；PHP 里 `'0'` 是假值，被 `?:` 换成 `''`，`SnowflakeConfig::intValue` 就抛出"idgen.node_id 必填且必须是 0 或正整数"。
   - 复现：`SAND_IDGEN_NODE_RANGE=0-1023 SAND_IDGEN_NODE_ID=0 SAND_IDGEN_MAX_CLOCK_BACKWARDS_MS=10`，结果是 `node_id => ""`，抛 `SnowflakeConfigException`。对照组把基值改成 1，正常得到 `nodeBase=1`。
   - 影响：号段从 0 开始、基值为 0 是文档允许、也最常见的首个部署配置，按现在的写法必定起不来。不会发出重复号（失败时直接拒启），但属于正确性缺陷，而且错误提示自相矛盾（提示说可以填 0）。
   - 修法：改成 `env('SAND_IDGEN_NODE_ID', '')`。其余两项照同样写法改，或用 `?? ''`。补一条回归测试：经 env/config 字符串 `'0'` 配基值 0 能正常启动。
2. **PR 描述的"剩余项"已过时**：描述里仍把"多 worker 节点号细分"列为待办，但 d44a048 已经实现（SnowflakeNodePlan + 跨进程测试）。合并前请同步描述，免得范围说明和实际 diff 对不上。

## 审计项逐条结论

### 1. 范围是否与描述一致：一致（描述"剩余项"过时，见必须修改点 2）
- 改动全部落在发号器相关位置：`src/Infra/Id/*`（发号器、配置、节点规划、运行时挂载、两个异常类）、`app/bootstrap/IdGeneratorGuard.php`、`config/idgen.php`、`config/bootstrap.php`（+2 行注册自检）、`.env.example`（+10 行）、`docs/id-generator.md`，以及对应测试。
- 删除 0 行，没有无关改动。基线里已有、但仓内没有引用的 `godruoyi/php-snowflake` 依赖未动（不属于本 PR 范围，可另开任务清理）。

### 2. 安全与正确性
- 位布局：41 位毫秒 + 13 位节点 + 9 位序列 = 63 位，id 小于 2^63，PHP int64 放得下。纪元是 2026-01-01 UTC，约可用到 2095 年；时钟早于纪元时拒绝发号。
- 序列溢出：同一毫秒内用完 511 个号后，等到下一毫秒再发，有测试覆盖。
- 时钟回拨：回拨小于 10ms 时等时钟追上，达到 10ms（含等于）时告警并拒发。与总表第 17 条、v4 J-3 定的 10ms 一致，有测试覆盖。
- 多进程重号：节点号 = 基值 + 进程槽位 + worker 序号。已核对 webman 运行时的进程命名（`config('process')` 的键名原样作为 worker 名；插件进程名为 `plugin.{firm}.{name}.{process}` 和 `plugin.{firm}.{process}`），与 SnowflakeNodePlan 的槽位键一致。7 个真实子进程共发 2100 个号，没有重复。
- 配置失败时拒绝启动：号段或基值越界、阈值缺失、未知进程、worker 序号越界都会抛异常，并调用 `haltMaster`，与仓内已有的几个启动自检同一做法。
- 门禁文件改动只是把新文件加进 phpstan 检查路径（收紧）。没有密钥、没有新的外部依赖、没有放宽任何检查。
- 本地复跑：`vendor/bin/phpunit tests/Unit/Infra/Id tests/Unit/App/IdGeneratorGuardTest.php`，结果 OK（31 个测试，3210 条断言）。

### 3. 门禁文件只减不增：通过
- 没有改到 `.github/workflows/*`、`tools/`（pre-merge.sh、naming_check.py 等）、任何白名单、豁免名单或基线文件。
- 唯一碰到的检查配置是 `server/phpstan.neon.dist` +1 行，把 `app/bootstrap/IdGeneratorGuard.php` 加入分析路径。这是扩大检查范围、属于收紧；该文件没有 `ignoreErrors` 或基线段。不是"往豁免里加条目"。

### 4. 与《老板已定事项总表》是否冲突：不冲突
- 第 17 条（11-17 改定，取代第 4 条和 char(21)）：数量可预见的表用自增；不可预见、持续增长的表用雪花 64 位 bigint（41+13+9），接口输出字符串；nanoid 只单独一列给对外链接用，不当主键。PR 的实现和 `docs/id-generator.md` 都与此一致。
- 全局 Sandpet 规则里 char(21)+nanoid 主键的约定只适用于 Sandpet 项目（pet_retail、pet_circle）；本仓是 sandadmin，且第 17 条已明确作废这条，不构成冲突。
- 备注（不影响合并）：PR 描述引用的"编号两列表-修订（2026-11-18 老板批准）"，总表本身（11-17 整理）里没有记录。从总表无法复核这次批准，以技术总监的复验为准。

### 5. `gh pr checks 105`（只读）：全部通过
```
composer audit        pass  (run 44598129928 / 44598145943)
frontend prod audit   pass  (run 44598129928 / 44598145943)
release self-check    pass  (run 44598129928 / 44598145943)
```
说明：CI 只有依赖审计和发版自检两类，没有 phpunit 或 phpstan。PR 自报的全量 426 个测试、phpstan level max、pre-merge 结果不是 CI 证据；本次只本地复跑了与发号器相关的 31 个测试。

## 建议项（不阻塞合并，可后续单独处理）
- `waitUntil()` 没有等待上限（回拨小于阈值的等待、序列溢出的等待都是如此）。时钟长时间停住时 `next()` 会一直阻塞，建议加上限，超过后拒发。
- 真实 Workerman Worker 对象的身份提取（`get_object_vars($worker)` 取 name 和 id）没有单测，现有测试都传数组。建议补一次真实 webman 启动冒烟。
- 槽位统计偏保守：monitor、log_retention 这类不发号的进程也占槽，webman 默认有 `cpu_count()*4` 个 worker，所以号段要配得比较宽。建议在部署文档里给出号段宽度怎么算。
- 两个部署分到重叠号段时目前查不出来；节点租约表已明确不在本次范围内，后续任务要跟上。
- `SnowflakeRuntime::mount` 第二次挂载会静默覆盖，序列状态跟着复位。建议禁止重复挂载，或至少告警。
- 建议把 phpunit 和 phpstan 加进 CI（属 CI 变更，须另行授权，本次不动）。

## 审计边界与模型说明
- 没有跑：全量单测、phpstan、pre-merge、真实 webman 启动、PG/MySQL 实测。没有建库，也没有启动任何数据库。
- 派单背景写的是"本地 Opus 审计通过视为批准"。本次按本周临时额度规矩执行：读取和执行由 SWE-2 子代理（dv-scout）完成，没有派任何 Claude 子代理。主代理实际是什么模型，本次没有核对会话记录。这份结论能否算作"Opus 审计"，请技术总监或老板判断。
