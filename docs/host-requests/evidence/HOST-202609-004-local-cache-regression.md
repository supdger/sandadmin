# HOST-202609-004 本地缓存并发证据

日期：2026-09-17。范围：SandAdmin 权威源码、零业务插件、中立控制器、PHP 文件缓存。
未启动或修改数据库、共享服务和消费工作区；未使用 JWT。

## 根因与实现

锁定依赖中的 `Webman\ThinkCache\driver\File::set()` 使用 `LOCK_EX` 写入，
`getRaw()` 却直接读取文件，不参与该锁；读者可能读取写入截断后的中间态，
反序列化后成为 `false`，由 `Driver::push()` 抛出
`only array cache can be push`。标签追加的读改写及标签清理也不是一个原子操作，
即使未抛异常仍可能丢失标签成员。

宿主通过现有 `type` 扩展点提供 `File`、`TagSet` 子类，保留上游文件格式、路径、
TTL 和调用入口，不改 vendor。所有该驱动的文件操作与标签 set/append/clear
共享位于缓存目录外的 store 锁；过期读取中的删除也参加锁。获取锁最多等待 5 秒，
异常路径释放锁，整个 store 清理不删除锁 inode。

损坏标签在锁内恢复，并失效当前文件缓存命名空间，避免遗留无法清理的权限值；
每次损坏恢复写一条不含 key、value、用户或凭据的告警。标签成员不使用上游通用
`push()` 的 1,000 项截断，确保标签清理覆盖全部成员。

`TagSet::remember()` 的业务闭包在 store 锁外执行，最终标签恢复、值重读与必要重建、
标签追加在锁内完成；热命中不刷新原 TTL。ReflectionCache 将空数组视为有效命中，
非数组视为 miss。

## 可复现命令

从仓库根目录运行：

```sh
php server/tests/SandAdmin/FileCacheConcurrencyTest.php --baseline --rounds=20
php server/tests/SandAdmin/FileCacheConcurrencyTest.php --rounds=1000
php server/tests/SandAdmin/FileCacheContractTest.php
```

脚本每次创建独立临时目录和 40 个 PHP 子进程，运行完退出全部子进程。
临时目录包含该轮 `result.json` 和去敏恢复日志。测试不载入宿主 `.env`，
不启动 Webman，不连接数据库或 Redis。

## 实测

环境：PHP 8.2.29，`pcntl`，本机文件系统。
源基线：`07d83d591b85deb83875473687a0d033a418c778` 加本轮宿主修改；
锁定 `webman/think-cache v2.1.6`（`936fe91fc5781a44f82262e805c60efbab262718`）、
`workerman/webman-framework v2.2.3`、`workerman/workerman v5.2.2`。

| 项目 | 结果 |
| --- | --- |
| 原生文件驱动，20 冷 + 20 热，40 进程 | 783 个 worker 异常或标签完整性检查失败事件；包含原始 `only array cache can be push` |
| 宿主驱动，1,000 冷 + 1,000 热，40 进程 | 80,000 worker 批次，0 失败 |
| 每个 worker 批次 | 3 个中立首屏方法的真实 ReflectionCache 属性读取、noNeedLogin、空注解、真实 CheckAuth 权限判断谓词与互斥角色权限夹具 |
| 并发标签成员 | 每轮 40 个不同成员全部存在，Reflection 标签 5 项完整 |
| 空权限数组 | 热命中未重写文件 |
| 1,005 个标签成员 | 无截断，清理后首末成员均不存在 |
| 并发清理与写入 | 100 轮，无未登记的存活缓存值；重复清理幂等 |
| 并发过期读取与新值写入 | 100 轮，新值未被过期路径删除 |
| false、字符串、非法数组、截断标签 | 全部恢复，成员完整，每次损坏恢复各 1 条告警 |
| 原生格式、压缩、prefix、remember、异常释放 | 最终 37 个行为断言通过；闭包等待另一个进程写同一 store 无互锁 |
| 源码语法、diff 空白检查 | 通过 |

最终候选完整压力回归耗时 104.655 秒；worker 批次延迟 p50 20.693 ms、
p95 44.092 ms、p99 48.649 ms。该延迟包含多次文件缓存操作和进程争用，
**不是 HTTP 延迟或生产 SLO**。
原生对照存在失败，不应将其耗时直接用于性能优劣结论。

独立审查发现压缩标签解码失败被上游视为缺失时，旧权限值可能残留。最终修复使用
唯一缺失标识并在锁内检查文件是否仍存在，区分真正缺失/过期和解码失败，
没有重写文件格式解析。补充普通/压缩格式下的截断与非法 payload、
缺失/过期不误报告警检查后，合约通过 37 断言；最终候选再跑
40 进程、100 冷 + 100 热、8,000 worker 批次及全部附加并发边界，
0 失败，耗时 16.995 秒。独立审查通过后，最终候选再完整重跑
1,000 冷 + 1,000 热、80,000 worker 批次与全部附加边界，0 失败；
表格及延迟均对应该最终候选完整重跑。

本地原始记录：

- 原生对照：`/Users/supdger/.tmp/sandadmin-file-cache-bb7b257fc2043cf3/result.json`
- 最终候选完整回归：`/Users/supdger/.tmp/sandadmin-file-cache-9adc7ec213ee4361/result.json`
- 最终候选影响范围回归：`/Users/supdger/.tmp/sandadmin-file-cache-a0d2068072b3e06f/result.json`
- 最终合约夹具：`/Users/supdger/.tmp/sandadmin-file-cache-contract-3399301fad72b178`

## 未验证与交接条件

本轮证明真实进程下的缓存行为和权限判断谓词；没有证明 HTTP 状态码、业务码、
真实 JWT、数据库授权加载、完整跨角色会话或消费宿主首屏。
消费方仍需固定 SandIAM 候选，以新宿主 revision 执行 40 worker、
至少 1,000 轮三个首屏 HTTP 接口冷/热并发复测后关闭请求。

锁只协调使用此宿主驱动、访问同一缓存路径的进程。切换宿主版本时应确保所有服务
worker 都已使用新驱动；新旧驱动混跑、外部直接写缓存文件、不同别名指向同一缓存
目录，以及网络文件系统锁语义不在本轮证据内。store 锁串行化本机文件操作，
真实业务负载的吞吐与延迟仍需消费宿主测量。

本轮子任务请求 `gpt-6-astra / high`；`current_model_record.py` 实测模型
`gpt-6-astra`、推理 `high`、provider `openai`。系统 `/usr/bin/python3`
受 Xcode 许可阻挡，未修改许可；通过现有 `/opt/homebrew/bin/python3.11`
运行同一模型记录脚本取得证据。
