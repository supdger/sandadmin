# 执行记录

## 源码基线与所有权

- 开始 revision：`3f9c141de0bec49ac62d0512325c8ea7fcbd46d9`。
- 保留既有分支 `codex/sandadmin-rename`；未提交、推送、部署。
- 开始时的未提交补丁保存在 `/private/tmp/sandpackage-before-upstream-20260915.patch`；
  安装主类原文另存 `/private/tmp/sandpackage-InstallLogic-before-upstream.php`。
- 原恢复主类及既有修复保存到 `LegacyInstallLogic.php`，只增加类名和新 driver 拒绝检查。
  普通主类不继承或调用它。未删除旧恢复文件。
- 主控单写后端；Sol 只写两个前端文件及中立包生成脚本；Astra 只读审查。
- composer.lock 和 vendor/composer/installed.json 均报告 SaiPackage 6.0.2 /
  `82043f83df45a6186ea872eef3bce388e0ad87e2`；vendor 未修改。

## 差异与调用方

逐项适配表、旧状态切换及回退说明见
`docs/architecture/SAIPACKAGE_POSTGRESQL_ADAPTATION.md`。

| 文件 | 改动 |
| --- | --- |
| InstallLogic.php | 上游派生安装主流程、SQL 三入口、状态与路径最小保护、终端回调桥接 |
| PostgresLifecycleSqlExecutor.php | 拒绝回滚后成功和未支持事务结束形式；注释按 SQL 分隔符识别 |
| LegacyInstallLogic.php、Recover.php | 原恢复实现隔离；CLI 只用旧类 |
| InstallController.php | 普通入口用新类；登记、撤回、恢复入口用旧类；列表区分两类 |
| TerminalRunner.php | 保留命令白名单、鉴权和进程退出检查；错误不再声称文件已经回滚 |
| 前端 api/index.ts、install/index.vue | 新 driver 类型及普通升级判断；新候选不进入旧撤回/恢复 UI |
| 旧恢复测试及控制器夹具 | 显式指向 LegacyInstallLogic，不冒充新普通安装测试 |
| build-neutral-package.php | 创建 v1/v2/坏 SQL 包，仅输出 ZIP |

## 已验证

命令均从本仓库执行；SQL 测试使用记录连接，不连接数据库。

- `php server/tests/SandPackage/UpstreamPostgresLifecycleTest.php`：
  真实 ZIP、真实上游文件部署/依赖服务、安装/升级 SQL 二选一、返回最终状态、
  旧状态和旧日志拒绝、路径/符号链接拒绝、旧正常状态升级、INI 引号、复制失败、
  卸载旧前端残留、依赖完成与失败。连接只记录 SQL。
- `php server/tests/SandPackage/PostgresLifecycleSqlExecutorTest.php`：
  22 项通过，包括函数体/注释/字符串、事务失败、注释分隔的 ROLLBACK/COMMIT AND CHAIN。
- `TerminalRunnerContractTest.php`、`TerminalRunnerOutcomeContractTest.php`：通过。
- 旧兼容线 `ProductionLifecycleV2ContractTest.php`：18/18 恢复子阶段、4/4 公共行为通过。
- 旧兼容线 backup-info、database-failure-diagnostic、upload-diagnostic-sanitize、
  candidate-manifest、upload-diagnostic-crash、backup-recovery-identity、
  lifecycle_non_db 套件：通过。
- 修改的生产 PHP 文件及生成器语法检查、`git diff --check`：通过。
- 前端两文件 ESLint、Prettier：通过。整体 vue-tsc 被现有
  `failed-upgrade-recovery.vite.config.mts` 模块解析与 WangEditor 类型导出问题阻断。
- Astra 独立源码审查提出的依赖备份目录、卸载残留、事务回滚误报和注释分隔识别问题已修补并回归；
  这不是独立宿主或浏览器验收。

旧 `discard-candidate-contract-v7.php` 的“构造器必须拒绝外部 runtime 符号链接”断言仍失败。
已用开始时保存的原 InstallLogic 在隔离目录复现相同结果，证明不是本轮引入；
未为了该旧构造器断言扩建兼容线。新正常入口在实际操作前拒绝不安全路径。

## 中立包

生成器：`server/tests/SandPackage/build-neutral-package.php`。
已生成并只读核对 7 个必需文件、版本、静态 app 配置与 SQL 差异。
输出：`/Users/supdger/.tmp/sandpackage-neutral-z6iSsRov`。

- `neutral-probe-1.0.0.zip`：`ad2be3ec86cad65dae1319e52022e9a2502c50ab8b1132baba63933c83209cf3`
- `neutral-probe-1.1.0.zip`：`303b4006958b6f39abe8dc56eb453f9e00a23296689de57517610e7d14695959`
- `neutral-probe-broken.zip`：`54e4dcfb8d4740b73adbbe0800a216e55558c5c5d71737473f9e711bddd36348`

## 剩余与授权边界

后端生命周期、异常验证及实际页面验证结果见下文。
早期失败包与后续修复包分别记录，不能将失败现场直接标记成功。
用户于 2026-09-15 明确允许临时独立宿主、专用 PostgreSQL 测试库、
临时服务及安装／升级／卸载验收；不包含提交、推送、生产部署或改写已有消费宿主。

### 2026-09-15 临时真实环境

- 临时目录：`/Users/supdger/.tmp/sandpackage-acceptance-oi64iB`。
- PostgreSQL 18.4 独立实例：本机 55439；专用库 `sandpackage_acceptance`。
- 当前源码复制到 `host/`，排除已有环境文件、runtime、Git 和构建输出；
  vendor/node_modules 采用本地副本。临时 Webman 单 worker，HTTP 8879、channel 22189；
  Vite 5198，均监听本机。
- `POST /core/install/install`，`dataType=pure`：code 200；数据库含 22 张基础表。
- 独立 Astra 使用保留验证码的标准登录接口成功，认证查询 `/core/system/user` 成功。
  前几次验证码失败为图像识别错误，不作为宿主配置缺陷。
- 已登录插件列表初始为空。中立包 1.0.0 上传与安装均 code 200，最终 state 1。
- 中立包 1.1.0 上传与准确确认升级均 code 200，最终 state 1；
  原始行 `1|installed by neutral-probe` 保留，新 label 为 `upgraded to 1.1.0`。
- HTTP JSON、独立登录脱敏证据保存在临时目录。后续真实页面证据见下节。
- 中立包真实卸载接口 code 200；表不存在、菜单计数 0、前后端目录均不存在。
  独立 Astra 在卸载后使用全新 cookie/验证码重新登录成功，认证用户查询成功，
  插件列表不再含 neutral-probe；证据 `astra-post-uninstall-evidence.json`。
- 独立 app `neutral-fail` 先创建测试表，再执行错误 SQL：真实安装接口返回 code 500；
  PostgreSQL `to_regclass('public.sand_package_fail')` 为空，证明同一隐式事务的前置 DDL 已回滚；
  登记 state 8 / operation_pending 1，运行目录不存在，没有误报安装完成。
  这不承诺数据库事务覆盖文件复制，也不承诺显式脚本中更早已提交的事务回滚。
- 在临时 registry 构造旧 state 6/7/8 的中立登记，通过真实安装 API 均被拒绝，
  三个 info.ini 均保持逐字节不变；没有创建对应运行目录。
- SandIAM 现有构建器在临时输出目录生成 0.7.2 review candidate，689 个文件，
  两次构建一致；SHA-256 `3917319c03fa9f44f4e20570a02ee46bea77bfc8df3ec96c078479e216fd9f48`。
  当前源码 config.json 无 sand_platform 扩展，真实上传通过。
- Astra 独立只读核对上述后端状态、数据、部署文件及失败证据，通过；
  候选关键源码与临时副本摘要一致。证据为临时目录的
  `astra-backend-lifecycle-evidence.json`。未以文件存在替代页面渲染。

### SandIAM 实际兼容核查及 PostgreSQL 适配修复

- 首次安装因执行器自行开启的事务与原包后续 BEGIN 冲突失败；领域表计数 0、
  插件未部署。保留首次失败登记为临时目录 `iam-first-failed-registry`。
- Astra 独立确认原包 422 条语句含 19 组完整显式事务及 164 条块外语句；
  没有真正嵌套。修复宿主 SQL 适配器，未修改 SandIAM：
  执行前扫描到显式 BEGIN 时，从第一句按脚本事务边界执行，块外自动提交；
  无显式事务仍整文件包装。拒绝接管调用者已持有的事务。
- 27 项执行器测试及普通生命周期回归通过。保留失败现场后仅重置临时夹具，
  用相同哈希原包重新上传安装；正式入口没有新增自动重试或恢复能力。
- 复测已通过事务适配，随后被原包 install.sql 第 3430 行的索引指纹断言主动拒绝：
  `SandIAM auth-rate-limit retention index fingerprint is incompatible`；
  实际索引为 `(window_start, id)`。错误返回 code 500、状态保持失败，未部署插件文件。
  此时前段已提交事务存在，不宣称全文件回滚，不直接重试。
- 最终执行器候选再次安装中立包 1.0.0，code 200 / state 1；保留供页面验收使用。
- 上述原始 SandIAM 0.7.2 候选未安装成功；后续修复候选见下节，不能混用包哈希。
- 独立只读查询确认：双列索引的 `pg_get_indexdef(indexrelid, 3, true)` 返回空字符串，
  原包要求 `IS NULL`，导致第 038 个迁移块主动失败；当前失败块已回滚，
  账本保留 revision 0–37（38 条）。只证明当前 PostgreSQL 18.4 上的兼容错误，
  不声称这是该版本新引入的行为。

`sand_platform` 自动依赖/登记扩展的包由正常入口明确拒绝；不得将当前候选直接同步给依赖该能力的消费者。
此限制已写入公共文档，不把拒绝安装计为 SandIAM 安装成功。

### 索引修复后的干净复测与真实页面

- 用户明确要求继续解决 SandIAM 索引校验和页面验收，源码修复位于权威工作区
  `/Users/code/project/sand_plugins/sand-iam`，没有把业务 SQL 放入宿主。
- 插件生成器及升级前置校验改用 `indnatts = 2 AND indnkeyatts = 2`，
  保留列名、列序、唯一性与有效性校验。历史 038 迁移及账本 checksum 不变；
  仅生成的新装内联 SQL 做精确替换，源片段漂移时拒绝生成。
- 7 个插件文件变更：生成器、完整性检查器、升级前置 SQL、root/plugin 的
  install/update SQL。生命周期幂等、菜单契约、语法及 diff 检查通过；
  完整性 25/26，剩余为 dirty 工作树不满足正式发布来源门。
- 修复包：`iam-fixed-artifacts/sand-iam-0.7.2-v10-20260915T115357Z/`，
  SHA-256 `48fd6222bd1cb5f42677bdf16b9dd54d311d0b6f6365f74fe4c0a55de859f3a8`，
  689 个文件，同一冻结快照重复构建一致。未发布。
- 干净复测宿主为临时目录下 `fixed-host/`，数据库 `sandpackage_acceptance_fixed`，
  后端 8880、channel 22190、前端 5199。旧失败库完整保留。
- 通过标准安装器完成零插件初始化；浏览器正常填写账号及可见验证码登录，
  关闭记住密码。经插件页面上传修复包、点击安装并确认，最终显示已安装。
- 真实数据库：迁移账本 40 条 / 最大 revision 39；retention 索引总属性数及键数均为 2。
  registry 为 state 1 / saipackage-pg-v1。
- 浏览器打开 SandIAM 管理总览、客户主体列表，通过表单创建
  `安装适配验证组织 / package-probe-org`，列表共 1 条。
  截图及 DOM：`iam-fixed-organization.png`、`iam-fixed-organization-dom.txt`。
- 独立 Astra 操作原中立宿主：从页面上传 1.1.0、确认升级、访问显示 1.1.0 的页面，
  核对原数据保留，再从 UI 卸载，菜单消失且工作台可用。
  同一独立 Agent 在干净宿主打开 SandIAM 总览及客户主体，核对上述记录。
  证据：临时目录 `ui-acceptance/report.md` 及截图。
- 未验证生产构建、旧失败数据库迁移或 SandIAM 全部业务功能；未提交、推送、
  改写已有消费宿主或部署。此次结果支持本安装兼容切片，不替代这些边界。
- 独立验收结束后已停止本任务两个 Webman、两个 Vite 和临时 PostgreSQL 实例；
  数据库目录、候选包和证据文件保留，未触及已有演示环境。

## 提交与消费方拉取授权

验收完成后，用户明确允许将宿主与 SandIAM 修复提交、推送到现有开发分支，
并让 `sand_plugins` 主动拉取固定宿主版本。该授权不包含生产部署、
演示数据库迁移或演示服务启停。消费同步须先 dry-run，保留消费方覆盖层，
最终 SHA 记录在消费方 `sandadmin-host.lock`。

## 模型记录

- 主控：gpt-6-astra / low / openai（当前任务记录）。
- astramedium__compatibility_review：请求 gpt-6-astra / medium；实际 gpt-6-astra / medium / openai。
- solmedium__upstream_upgrade_ui：请求 gpt-5.6-sol / medium；实际 gpt-5.6-sol / medium / openai。
- astrahigh__login_runtime：请求 gpt-6-astra / high；实际 gpt-6-astra / high / openai。
  完成登录诊断和独立后端运行核对，未改源码。
- astramedium__iam_index_fix：请求及实际均为 gpt-6-astra / medium / openai。
- astramedium__lifecycle_ui_acceptance：请求及实际均为 gpt-6-astra / medium / openai。
