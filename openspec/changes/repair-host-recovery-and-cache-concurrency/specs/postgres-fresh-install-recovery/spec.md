## Purpose

为 PostgreSQL 插件全新安装提供可检查、可确认且保留证据的失败恢复能力，区分数据库提交与文件部署阶段，保护其他插件和宿主数据，避免操作者被迫手改登记文件或猜测重跑安装。

## ADDED Requirements

### Requirement: Fresh installation records recoverable outcomes

宿主 SHALL 区分 `sql_not_committed`、`sql_commit_unknown` 和 `sql_committed_deploy_pending`，并将恢复动作绑定应用、版本、候选载荷、生命周期 SQL、数据库、登记及部署现场。无法证明提交结果时 MUST 保持未知。

#### Scenario: Transaction failure without any earlier commit

- **WHEN** SQL 事务内失败且宿主确认回滚成功、此前没有提交或事务外写入
- **THEN** 检查返回未提交，并允许在精确确认后清理候选使其可重新上传

#### Scenario: Lost commit acknowledgement

- **WHEN** 提交已发送而结果丢失，或旧失败记录没有提交边界证据
- **THEN** 检查返回提交未知，常规重试和继续部署被拒绝

#### Scenario: Deployment or reload fails after SQL success

- **WHEN** 全部 SQL 已明确提交，但部署或服务重载未完成
- **THEN** 经身份与现场复核后只能继续剩余阶段，不再执行 SQL

### Requirement: Official recovery is locked and auditable

宿主 SHALL 提供官方检查与精确确认恢复入口，在宿主及应用锁内重新验证现场，保留操作者、动作及结果审计。并发或重复请求 MUST 不重复执行已完成的破坏性步骤。

#### Scenario: Two recovery operators

- **WHEN** 两个操作者对同一现场同时恢复
- **THEN** 只有一个取得操作锁，另一个不执行 SQL 或移动文件

#### Scenario: Candidate or database drift

- **WHEN** 检查后候选、SQL、数据库对象、登记或部署目录发生变化
- **THEN** 原确认失效，恢复拒绝且要求重新检查

#### Scenario: Manual decision for an old partial installation

- **WHEN** 旧失败新装记录提交结果未知，操作者提供经过归属审核的显式清理计划并确认当前指纹
- **THEN** 官方入口仅处理计划中可验证的目标，不执行任意卸载 SQL、不级联删除其他插件对象，并保留失败和再次执行的证据
