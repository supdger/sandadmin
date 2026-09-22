# 实施验证结果

日期：2026-09-22

## 已通过

- `sand-core` 与 `sand-package` 均采用顶层 `server/`、`sandadmin-artd/` 布局。
- 两个 `composer.json` 均通过 `composer validate --strict --no-check-publish`。
- 核心、SandPackage 后端及前端来源逐文件校验和一致；SandPackage 构建脚本遗漏在测试中被发现并补入包。
- 两个前端发布器的首次发布、幂等发布和本地修改拒绝测试通过；组合发布与宿主前端逐文件一致，宿主仅多一个不发布的 `.DS_Store`。
- SandPackage 非数据库契约、状态机、仓库、恢复、PostgreSQL SQL 执行器和上游兼容测试通过。
- SandAdmin 已通过 path repository 锁定 `supdger/sand-core:0.1.0`、`supdger/sand-package:0.1.0`，宿主不再手工声明两个插件及兼容层 PSR-4。
- 包接管 `plugin\sandadmin\`、`plugin\sandpackage\`、`Saithink\Saipackage\` 自动加载；`php webman list` 可发现 `sand:*` 与 `sandpackage:*` 命令，并完成路由配置装载。
- 在隔离 Webman 消费者中仅用 Composer 安装两个包，安装钩子未创建数据库、执行迁移或启停服务。
- `corepack pnpm build` 通过，包含 Vite 生产构建与 `vue-tsc --noEmit`。
- `corepack pnpm lint` 已通过；原有 59 个错误已在权威包源码中修复并重新发布核对，其中 46 个为机械格式问题，13 个为未使用项或异常空白。
- OpenSpec strict validation 通过。
- 综合门禁复跑通过：Git diff 检查、Composer dry-run/strict validate、包探针、前端发布器测试、SandPackage 全部 `*Test.php` 非数据库测试、宿主自动加载与命令发现、前端 Lint/构建及 OpenSpec strict validation。真实 PostgreSQL 集成测试明确排除。
- `/Users/code/project/sand_demo` 已按真实 Composer 消费者方式锁定 `supdger/sand-core:0.1.0` 与 `supdger/sand-package:0.1.0`；前端源码由两个包的发布器组合发布到同级标准目录 `sandadmin-artd`，未使用包内构建产物。
- 临时 PostgreSQL `demo` 库完成全新安装；浏览器登录、基础菜单、超级管理员权限、SandPackage 插件管理与仓库页均通过。零插件宿主不再登记未安装的 SandIAM 菜单，默认头像也由 Vite 解析后的资源 URL 正常加载。
- 中性插件损坏包安装进入“安装失败”，数据库事务回滚后 `sand_package_probe` 不存在、`NeutralProbe` 菜单计数为 0、前后端运行目录不存在；内置 `sandpackage:recover inspect-fresh` 判定 `sql_not_committed`，随后 `cleanup-fresh` 以 `sql_executed:false` 清理候选。
- 中性插件 `1.0.0` 经浏览器上传安装后，数据库行、菜单、`server/plugin/neutral-probe` 与 `sandadmin-artd/src/views/plugin/neutral-probe` 同时存在；`1.1.0` 使用精确确认串升级后新增 `label` 列且值为 `upgraded to 1.1.0`。
- `1.1.0` 经浏览器卸载后，测试表与菜单均清除，后端、前端及 SandPackage 登记目录均不存在，完整生命周期闭环通过。
- 真实宿主验收发现并修复两处核心源码问题：零插件初始化 SQL 不应预置 SandIAM 菜单；默认头像不能在绑定属性中使用未解析的 `@` 别名字符串。
- 当前开发版本统一采用 `0.1.0`，不再跟随 SaiAdmin `6.x` 版号；该版本号只表达早期开发成熟度，不扩大既有验证结论。

## 当前边界

- SandAdmin 已取消跟踪原有 197 个核心、SandPackage 与兼容层文件，并在 `server/.gitignore` 中忽略对应 Composer 运行目录。三个运行副本仍保留在磁盘，逐文件比对与两个包的权威源码一致；Git 已不再把它们作为宿主权威源码。
- 未提交、未推送、未发布、未部署。

## 候选归档

- `/private/tmp/sand-package-archives.cRJ6uZ/sand-core-0.1.0.zip`
  - SHA-256 `a6ce68e1091037ce61d71c0bf7aaa514e70fe647cee4b2a4945d85dcb9285ef8`
- `/private/tmp/sand-package-archives.cRJ6uZ/sand-package-0.1.0.zip`
  - SHA-256 `fe643f50f8a4c44fb20b13c5a050324aa0a58d73b032c7c871aef87e648ed1ac`

归档扫描未发现 `node_modules`、`vendor`、`runtime`、`.git`、`.env`、`.idea` 或 `.vscode`。
