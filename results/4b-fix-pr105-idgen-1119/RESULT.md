# RESULT — 修 PR #105 发号器多 worker 重号

- 仓库：`supdger/sandadmin`（本地工作区 `~/agent-work/assistant/2e-id-generator-1119/sandadmin`）
- 分支：`feat/id-generator`，在 `a7067c3` 上加提交 → 新 SHA **`d44a048`**，普通 push（非强推）：`a7067c3..d44a048 feat/id-generator -> feat/id-generator`
- 提交：`fix(idgen): 节点号细分到进程消除多 worker 重号，回拨阈值定 10ms`

## 改动

**1. 节点号细分到进程**

- 新增 `server/src/Infra/Id/SnowflakeNodePlan.php`：把全部会启动的进程按 webman 启动顺序（`config('process')` 在前、`config('plugin')` 的 `plugin.{firm}[.{name}].{process}` 在后，`enable=false` 不占槽）排成连续槽位；`resolveNodeId(基值, 进程名, worker序号) = 基值 + 槽位偏移 + 序号`，进程不在布局内或序号越界即抛 `SnowflakeConfigException`。
- `SnowflakeConfig`：`node_id` 语义改为本机节点基值（属性改名 `nodeBase`），`fromArray` 新增 `workerTotal` 参数，校验「基值 + 进程总数 − 1 ≤ rangeEnd」，越界拒启。
- `IdGeneratorGuard`：启动时建布局 → 校验总数在号段内 → 从 `$worker`（webman 对象取 name/id，测试可传数组）解析进程身份 → 按槽位取号挂载。web、task_worker（任务消费者）、monitor、log_retention、插件自定义进程各占一段互不冲突；失败路径对真实 Worker 仍 `haltMaster` 拒启。
- `config/idgen.php`、`server/.env.example`：注释同步基值语义与号段宽度要求（至少容纳全部发号进程）。

**2. 跨进程唯一性测试**

- `tests/Unit/Infra/Id/SnowflakeCrossProcessTest.php` + `tests/Support/snowflake-child.php`：按布局给 4 web + 2 task_worker + 1 log_retention 共 7 个槽位各起一个真实 PHP 子进程并发发号（`proc_open`，不起服务、不连库），每进程 300 个号写入临时文件，父进程合并断言零重复、每号反解 node_id 与分配一致。

**3. 回拨阈值定 10ms**

- `config/idgen.php`、`.env.example`、`docs/id-generator.md`、`SnowflakeConfig` 注释全部改「待技术总监定」为已定：推荐值 10，理由是 NTP/虚拟机时钟抖动在毫秒级、10ms 内可等待追上、更大回拨按时钟异常拒发；配置保持必填。
- `docs/id-generator.md`：节点号小节改为描述槽位方案，「多 worker 节点号细分方案」从"本任务不含"移除。

**4. 测试更新**

- `SnowflakeNodePlanTest`（新）：槽位顺序、enable=false 不占槽、插件进程命名、越界/未知进程拒分。
- `IdGeneratorGuardTest`：改为新签名，覆盖 web/task/log_retention 各进程取号、五进程号互不相同、总数越界拒启、未知进程拒启。
- `SnowflakeConfigTest`：`nodeId→nodeBase`，新增 `workerTotal` 界内/越界两例。

## 跨进程测试结果

`vendor/bin/phpunit --testsuite unit --filter SnowflakeCrossProcessTest --testdox`：

```
Snowflake Cross Process (Sandadmin\Tests\Unit\Infra\Id\SnowflakeCrossProcess)
 ✔ Ids across child processes are unique
OK (1 test, 2123 assertions)
```

7 个子进程并发发 2100 个号，合并后 `array_unique` 计数 = 2100，零重复；每个号 `decode()` 的 node_id 均等于该子进程分到的槽位号（100–106）。

## 门禁命令、退出码与关键输出

| 命令 | 退出码 | 关键输出 |
| --- | --- | --- |
| `cd server && vendor/bin/phpunit --testsuite unit` | 0 | `OK (439 tests, 5823 assertions)` |
| `vendor/bin/phpstan analyse --no-progress` | 0 | `[OK] No errors` |
| `bash tools/pre-merge.sh --fast` | 0 | 7/7 步全过，末尾 `pre-merge --fast 全部通过` |
| `python3 tools/naming_check.py code`（diff + 新文件） | 0 | 首轮 WARN `$tmpDir`（tmp 含糊修饰）→ 已改 `$outDir`，复跑零命中 |
| `python3 tools/naming_check.py notes`（diff + 新文件） | 0 | 仅 INFO：注释占比 12.3% / 4.1%（低于 15% 提醒线） |
| `python3 tools/naming_check.py terms tools/naming-terms.tsv`（diff + 全部改动文件） | 0 | 无命中 |
| `php tools/typephp_check.php`（diff 模式） | 0 | `FAIL=0 WARN=0 豁免=0` |

## 备注

- `IdGeneratorGuard::start` 失败路径对 `is_object($worker)` 才 `haltMaster`（posix_kill 主进程）；测试一律传数组形态 worker 身份，避免测试进程误杀。
- 插件进程枚举与 `support\App::run()` 完全同口径（含 `plugin.{firm}.{name}.{proc}` 与 `plugin.{firm}.{proc}` 两级命名），插件 crontab `task` 进程在本机（macOS，`SANDADMIN_CRONTAB_ENABLED` 默认关）不占槽、在 Linux 生产自动占槽。
- 未做（沿用禁止项）：未建库、未起服务/库、未改 CI 与门禁脚本、未动豁免/白名单、未合并、未强推、未 `--no-verify`、未 `git add -A`。
