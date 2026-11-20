# RESULT — 11c 日志留存修正（X5）

仓库：`~/agent-work/pub-sandadmin`（任务里写的 `server/config/log.php` 在本仓；
`~/Projects/business-server` 与 pet_retail 同名文件均不含 maxFiles=181）。
分支：`fix/log-retention`，从 `main` 切出，提交 `6e39287`。未推送、未合并。

## 各通道天数与换算方式

| 通道 | kind | 天数 | maxFiles | 换算方式 |
| --- | --- | --- | --- | --- |
| `default`（webman.log） | general | 183（缺省值，可下调到下限 30 天） | 184 | maxFiles = 天数 + 1（183 份滚动 + 1 份当天活动文件） |
| `security`（security.log，登录/操作/审计/找回/双因素/权限变更经 LogAuditSink 写入） | security | 184（下限，不可调低） | 185 | 下限 = 6 个自然月按最长跨度折天数：任意 6 个连续自然月最多 184 天（7–12 月、3–8 月、5–10 月、8–1 月均为 184；含闰年 2 月的跨度最长 182），故 days ≥ 184 保证任一文件留存满 6 个自然月；maxFiles = 184 + 1 |

换算依据写成 `LogRetentionPolicy::MIN_SECURITY_MONTHS = 6` 与 `MIN_SECURITY_DAYS = 184`，
单测 `testSecurityMinCoversAnySixNaturalMonths` 逐月遍历 2023–2028（含闰年）验证任意
6 个连续自然月跨度 ≤ 184。

## 改动内容

- `server/src/Infra/Log/LogRetentionPolicy.php`：`MIN_SECURITY_DAYS` 183→184，新增
  `MIN_SECURITY_MONTHS = 6`、`DEFAULT_SECURITY_DAYS`；security 通道缺省 days 时按 184
  （此前按 general 缺省 183，会低于新下限导致拒启）；拒启报错对 security 写明
  “184 天（6 个自然月）”。
- `server/config/log.php`：`default` maxFiles 181→184；`security` days 183→184、
  maxFiles 181→185；注释更新为 X5 口径。
- `server/app/bootstrap/LogRetentionGuard.php`、`server/config/bootstrap.php`：注释口径更新，
  自检逻辑不变（启动期 `assertValid` 低于下限即拒绝启动，已在 main 上存在）。
- `server/tests/Unit/Infra/Log/LogRetentionPolicyTest.php`：边界值更新（183 现被拒、184 通过），
  新增 security 缺省值、自然月跨度折算、出货配置 `assertValid` + maxFiles = 天数 + 1 三条用例。
- `server/tests/Unit/App/LogRetentionGuardTest.php`：合法配置与报错断言更新到 184。

## 验证

- `cd server && vendor/bin/phpunit`：804 tests / 3465 assertions，OK（Skipped 125 / Incomplete 100 为既有）。
  其中 LogRetention 相关 18 tests / 186 assertions 全过；守卫实测输出
  `[sandadmin] 日志通道 security 留存 30 天低于 security 类下限 184 天（6 个自然月）`。
- `cd server && vendor/bin/phpstan analyse`：level max，No errors。
- `./tools/pre-merge.sh --fast`：7/7 门禁全过（语法、契约一致性、危险函数、dev-only 引用、
  TypePHP 禁用写法、PHPUnit Unit 411 tests、PHPStan）。
- `naming_check.py code|notes --diff`：RC=0；1 条 WARN——本 diff 说明性注释占比 28.2%（22/78 行），
  内容为留存口径与折算依据的契约性注释，判断可接受。

## 未做/边界

- 未建库、未启库；未推分支、未开 PR、未合并（Fusion 审计另排）。
- 无新增豁免/白名单/baseline 条目；未动 git 历史、未用 `--no-verify`。
- 存量部署若自行把 security 通道 days 调到 184 以下，升级后按设计启动自检拒启。
