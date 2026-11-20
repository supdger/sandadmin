# 模块测试规范（module-tests）

出处：顶层架构设计 v4 U-6「每个模块带测试」——新模块没有测试不合并；
「模块」定死为 `server/src/Module/<名>`（或插件包）+ 对应测试目录，
**测试目录缺失即门禁失败**。本页定模块层要求；接口层的契约权威是
`contract/openapi.yaml`，接口测试骨架由构建期从 OpenAPI 机械派生
（另有任务落地），不在本页范围。

## 两个测试目录的分工

| 目录 | 套件 | 依赖 | 写什么 |
| --- | --- | --- | --- |
| `server/tests/Unit/Module/<模块>` | `vendor/bin/phpunit --testsuite unit` | 纯内存，不连外部库 | 无依赖可达的逻辑：入参校验、异常工厂、纯计算、用 `tests/Support/` 的工厂、Fake 和 `Disconnected` 能盖住的服务路径；要走 SQL 的用例一律放 Integration |
| `server/tests/Integration/Module/<模块>` | `vendor/bin/phpunit --testsuite integration` | `SAND_TEST_DB_URL` 指向已应用基线迁移的 PostgreSQL | 必须真实库才能验的行为：方言 SQL（ILIKE、jsonb、RETURNING 语义）、约束违例映射、跨表事务 |

- Unit 套件必须对 `SAND_TEST_DB_URL` 无感——不设该变量也要全过。
  要库的行为写进 Integration，用 `setUpBeforeClass` 里
  `markTestSkipped('需要 SAND_TEST_DB_URL 指向 PostgreSQL')` 的既有写法。
- Unit 只用 `tests/Support/` 的工厂、Fake 和 `Disconnected`，不建任何库连接；
  要走 SQL 的用例一律放 Integration。

## 硬性要求

- **新模块必须带测试**：`src/Module/<名>` 落地时，
  `tests/Unit/Module/<名>` 同步给出；够不着 Unit 的部分
  （纯 SQL 行为）放 `tests/Integration/Module/<名>`，两个目录至少一个有内容。
- **测试数据用工厂**：复用 `tests/Support/` 下的工厂与 Fake
  （`FakeIdempotencyStore`、`FixedClock`、`HttpStack` 等），
  不手拼散落各测试里的私有夹具；缺哪个工厂就补进 `tests/Support/`。
- **改动行覆盖率阈值 80% 起步**：按 diff 行计，不达不合并。
  起步值 80% 是技术总监口径，可再调。
- **菜单页**：每个菜单页一条 Playwright 冒烟用例
  （打开、列表、新建、保存），归前端测试口径。

## 写法约定

- 测试类名 `<被测类>Test`，一个被测类一个文件；
  方法名 `test<做什么><什么情况>`，描述只用一种语言（本仓用英文方法名、
  简体中文注释）。
- `declare(strict_types=1)`，参数/返回值/局部变量类型标注完整
  （TypePHP 口径），许可证头跟随仓内现有测试文件。
- 断言要落可复核的形状：序列化结构用 `assertSame` 全等比对，
  异常断言状态码与 `fields` 键，不只断「抛了」。
- 依赖处理照既有样例：`OrgIdempotency(null)` 关掉幂等存储；
  构造参数是 Connection 的仓储/服务在 Unit 里用 `Disconnected::make()` 顶替，
  需要真实存储行为时用 `tests/Support/Fake*`，要建表走 SQL 的放 Integration。
