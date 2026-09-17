# HOST-202609-003 本地恢复验证

日期：2026-09-17。本轮未连接 PostgreSQL，未改消费方、未启停服务。

## 实现与验证

`FreshInstallRecovery` 只负责新装提交阶段与恢复证据；正常部署仍调用上游服务。
恢复 CLI 按 driver 分流，`inspect` 不再将新装失败送入旧升级恢复。
候选、SQL、数据库身份、目标表、登记和部署文件绑定后才能确认动作。
数据库指纹限定为候选声明的普通表及所属序列，不扫描或锁定整个宿主数据库。

失败测试使用记录连接及临时目录，不接触真实数据库。

```sh
php server/tests/SandPackage/FreshInstallRecoveryTest.php
php server/tests/SandPackage/UpstreamPostgresLifecycleTest.php
php server/tests/SandPackage/PostgresLifecycleSqlExecutorTest.php
php -l server/tests/SandPackage/FreshInstallRecoveryPostgresTest.php
```

| 检查 | 实现者与独立审查结果 |
| --- | --- |
| 新装恢复故障注入及官方 CLI | 51/51；其中 47 项故障边界，4 项 CLI 检查 |
| 原有上游文件生命周期与记录 SQL 连接 | 44/44 |
| SQL 解析及事务故障语义 | 27/27 |
| 待授权真实 PostgreSQL 脚本 | 仅语法检查通过，未执行 |

重点覆盖：SQL 前失败及 pending 写入窗口、事务回滚、提交确认丢失、部分提交、
空 SQL、部署再次失败、恢复清除 pending 窗口、同一连接、锁竞争、候选或数据库漂移、
核心及其他插件表拒绝、同批受限清理、清理提交 before/after 状态、归档中断续做、
重复动作及下次安装保留历史审计。

独立审查发现的两个中断窗口均已修复并复验。最终固定摘要：

- `FreshInstallRecovery.php`：`520f384b7241294c1c0ea91d1a211b53e2ff195650ba274c16382773bc2305c8`
- `InstallLogic.php`：`5df23b810f05252af50369d36d5bb98b410ca35ef282d940b68b99ecc118557e`

## 真实 PostgreSQL 回归计划：待一次性精确授权

脚本：`server/tests/SandPackage/FreshInstallRecoveryPostgresTest.php`。

SHA-256：`1b3445b625b581e2c488029f2e5da2662ebc6ba1fa54a5299faa841ffc74a12f`。

- 环境：现有 `sandadmin` 数据库，由操作者明确提供 `HOST003_TEST_DSN`、
  `HOST003_TEST_USER`、`HOST003_TEST_PASSWORD`，设置 `HOST003_EXPECTED_DATABASE=sandadmin`。
  脚本只读核对数据库名，错库拒绝；不加载宿主 `.env`，不输出凭据。
- 必须显式传入 `--authorize-schema=host003_recovery_probe_20260917`；
  参数是执行防误触条件，不能代替任务授权。
- 新建 schema：仅 `host003_recovery_probe_20260917`，已有即拒绝，不复用。
- 表：仅该 schema 的 `probe_one`、`probe_two`，测试列 `id bigint`；
  不写现有业务表，不建数据库。
- 故障点：SQL 解析前失败、事务内 `SELECT 1/0`、多事务部分提交、真实 COMMIT 成功后
  包装连接注入确认丢失、SQL 后模拟部署/缓存错误及重复继续部署不重放 SQL。
- 候选、登记、部署夹具：独立临时目录 `sandpackage-real-fresh-*`；
  不放入 SandAdmin 或消费宿主的实际插件目录。
- 退出清理：CREATE 在 `try/finally` 内；仅回滚活动事务，删除上述两表 `RESTRICT`，
  再删除指定 schema `RESTRICT`，不 `CASCADE`。清理失败显式报错，不能自动扩大范围。
- 不覆盖真实服务重载、真实 HTTP、完整跨角色会话或消费方 86 张关系/账本冻结现场。

2026-09-17 交接任务已明确确认现有授权不覆盖上述 schema，本轮只交付脚本与方案，
由消费方另行申请一次授权。不得把一般生命周期授权或先前业务夹具授权外推至本脚本。

## 关闭条件仍未满足

本轮证明源码和无数据库状态机行为。实际清理旧冻结现场、修复包再安装、
86 张关系、41 条账本/max 40、文件摘要、服务健康及共享菜单等业务残留核对尚未执行。
表级人工清理计划不删除共享菜单或任意其他关系，不能单独证明整个业务现场已恢复。

实现与独立审查分别使用 `astrahigh__fresh_recovery`、
`astrahigh__recovery_review`；请求及实际模型均为 `gpt-6-astra / high / openai`。
