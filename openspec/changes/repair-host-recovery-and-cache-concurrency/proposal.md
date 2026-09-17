## Why

HOST-202609-003 的新装失败记录被普通入口与旧恢复入口同时拒绝，无法正式处置部分提交现场。HOST-202609-004 的文件缓存标签在多 worker 首屏请求中存在读写竞态，导致间歇业务 500。

## What Changes

- 增加仅适用于 PostgreSQL 新装的受锁恢复入口，区分未提交、提交未知、已提交待部署，绑定现场及候选身份并记录审计。
- 保留未知状态失败关闭；旧失败记录不得仅凭 `state=8` 自动重跑 SQL。
- 宿主文件缓存适配器保护标签集合与文件读写，损坏标签可安全重建，保留上游 vendor。
- 分别提供故障注入、40 进程冷/热并发验证及消费方官方复测命令；不改动消费工作区。

## Capabilities

### New Capabilities

- `postgres-fresh-install-recovery`：PostgreSQL 新装失败后的检查和明确确认恢复。
- `file-cache-tag-concurrency`：宿主文件缓存标签在多进程下的原子更新与损坏恢复。

### Modified Capabilities

无已归档的相关主规格。

## Impact

影响 SandPackage 的安装 SQL 结果记录、恢复 CLI，以及 SandAdmin 的文件缓存配置和实现。保持上游正常安装流程，不增加业务插件依赖、不修改 vendor、不扩展旧升级恢复框架。

本轮仅在 SandAdmin 工作区修改及提交；不检查或启动 PostgreSQL，不修改 `sand_plugins`，不执行其冻结现场恢复，不部署。真实 PostgreSQL 与带 JWT 的宿主 HTTP 验收须如实记录未执行项。
