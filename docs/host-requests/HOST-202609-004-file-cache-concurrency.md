# HOST-202609-004：文件缓存标签并发

来源：消费方 `sand_plugins` 的同编号宿主请求，冻结宿主为 `07d83d5`。

## 根因

ThinkCache 文件写入使用排他锁，读取却可能在文件截断重写时读到不完整内容。标签追加是未加整体锁的读改写操作，还会造成并发成员覆盖；前者触发 `only array cache can be push`，后者使标签清理遗漏条目。中立 ReflectionCache/CheckAuth 夹具可以复现，不依赖业务插件。

## 最小修复

宿主文件驱动子类保留上游文件格式，在缓存目录外使用稳定锁文件，保护文件读写、过期、清理及标签复合操作。损坏标签在锁内失效缓存后重建并记录去敏告警，防止留下无法按标签清理的权限缓存。`remember` 的业务回调不在缓存锁内执行。

`ReflectionCache` 将合法空数组作为缓存命中。默认 file store 指向宿主适配器；vendor 和 Redis 配置保持原样。

## 验证与消费

40 个独立 PHP 进程直接运行中立反射和真实权限判断方法；该证据覆盖缓存并发及角色隔离，不等于带 JWT 的 HTTP 验收。具体轮次、结果、耗时和审查见
[执行记录](../../openspec/changes/repair-host-recovery-and-cache-concurrency/execution.md)。

消费方已拉取 `2425803`，在 40-worker 宿主使用有效 JWT 对
`/core/system/user`、`/core/system/dictAll`、`/core/system/menu` 完成冷/热各 1,000 轮回归：
80,000 批次、240,000 次请求，HTTP 200/业务码 200，零失败。已核对最终 JSON 报告；
消费方反馈新日志无原缓存异常。驱动使用每 worker 持久连接，未将请求串行化。

源码拉取不代表长驻进程已切换驱动；服务重载须由消费方在既有授权范围内执行。本任务不改写或重载消费宿主。
