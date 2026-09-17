# HOST-202609-003：PostgreSQL 新装失败恢复

来源：消费方 `sand_plugins` 的同编号宿主请求。冻结宿主为 `07d83d5`，失败包
SHA-256 为 `075708dbfebcbac2214881a4ad979b61171fa0b55f75f4d37ca67aa2d7648817`。
本文描述宿主修复；消费方冻结现场尚未在本任务执行恢复。

## 根因与边界

新安装流程只记录 `operation_pending` 与失败状态 8，不保留 SQL 提交结果；官方恢复命令又只调用旧恢复实现，旧实现拒绝新 driver，因此普通操作和恢复操作同时被锁住。

SQL 文件可以含显式多事务及事务外语句。后段失败不意味着前段没有提交；COMMIT 已发送后，即使 ROLLBACK 返回成功，也不能推断原提交被回滚。旧现场的表数量、账本和缺失部署目录不足以证明可以重跑。

## 修复契约

- 正常安装保留上游部署与依赖处理，增加提交边界记录，不引入业务插件依赖。
- `sql_not_committed` 只用于可以证明尚未提交的现场。
- `sql_committed_deploy_pending` 只继续部署阶段，不再次执行安装 SQL。
- `sql_commit_unknown` 默认只检查；清理须使用经审核的显式计划及当前现场指纹确认。
- 恢复复用宿主及应用锁，绑定候选载荷、SQL、数据库、登记及部署现场，保留审计和中断阶段。
- 人工清理不执行任意卸载 SQL、不使用 `CASCADE`、不按通配前缀删除，也不锁定全部宿主业务表。

旧现场可能没有原始 ZIP 摘要记录。当前载荷和 SQL 摘要与原始 ZIP SHA 是不同证据，不得混称；修复包只能在官方清理完成后重新上传。

## 冻结现场的官方入口

以下命令由消费方在拉取最终 clean revision 后，从其宿主 `server/` 执行。
本任务未执行这些命令，也未生成替现场作决定的清理计划。

```sh
php webman sandpackage:recover inspect sand-iam
```

没有历史提交证据的旧现场应返回 `sql_commit_unknown`，而不是“不能调用旧恢复入口”。
输出包含当前 `binding.candidate`、SQL 和数据库指纹，以及供归属审核的
`manual_plan_tables`。这份候选表列表不是删除授权。

操作者须审核原失败候选和当前对象的归属，为每个要清理的普通表填写计划，例如：

```json
{
  "decision": "fresh_install_partial",
  "app": "sand-iam",
  "candidate": "<本次 inspect 的 binding.candidate>",
  "reason": "<确认属于此次失败新装的依据>",
  "drop_tables": ["public.<已审核的表名>"],
  "ownership": {
    "public.<已审核的表名>": {
      "owner_app": "sand-iam",
      "evidence": "<失败候选声明及现场归属证据>"
    }
  }
}
```

此示例不可直接执行；必须列出全部实际批准目标并保存为普通 JSON 文件。核心表、其他插件声明表、
已知新装前存在的表和无归属证据的目标会被拒绝。共享菜单或其他业务残留不由这份表清理计划处理，
不能据此宣称全业务恢复完成。

```sh
php webman sandpackage:recover inspect sand-iam --plan /absolute/path/reviewed-plan.json
php webman sandpackage:recover manual-cleanup-fresh sand-iam \
  --plan /absolute/path/reviewed-plan.json \
  --confirmation '<上一条 inspect 原样返回的 confirmation>'
```

现场变化后原确认失效，必须重新检查。得到 `phase=cleaned` 后，失败候选被留存归档，
消费方才可通过正常上传/安装入口处理修复候选
`4e0ffc8c4c8a121d0fe067c345a9b7425129c26e23f7d93ccf3e59e5cc263827`。
候选表恢复、41 条账本/max 40、载荷摘要和服务健康仍须实际复测。

对于有新记录且明确未提交的现场，按检查返回的 `cleanup-fresh` 操作与确认执行；
对于已提交待部署现场，按 `continue-fresh` 执行，SQL 不会重跑。若输出要求服务重载，
需在既有授权范围使用 `--restart`；不传该选项不能将重载失败记作完成。

如果清理提交确认丢失或候选归档中断，重新执行检查。只有数据库后置状态和候选/归档身份
完全匹配时，检查才返回 `finish-cleanup-fresh` 及新的确认：

```sh
php webman sandpackage:recover inspect sand-iam
php webman sandpackage:recover finish-cleanup-fresh sand-iam \
  --confirmation '<本次 inspect 原样返回的 confirmation>'
```

该动作仅完成候选归档，不重跑清理 SQL。若检查仍返回未知或拒绝，不能用之前的确认
强行继续，也不能手改登记。

## 验证与交付

本次实现、独立审查、精确命令及真实验证缺口集中记录于
[执行记录](../../openspec/changes/repair-host-recovery-and-cache-concurrency/execution.md)。

真实 PostgreSQL 专项已在后续精确授权下通过，测试 schema 清理后独立核对无残留。
本任务不修改 `sand_plugins`，不直接清理其冻结现场，也不通过修改登记或目录绕过官方入口。
