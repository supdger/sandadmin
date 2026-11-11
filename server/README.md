# SandAdmin 后端

此目录是 SandAdmin 的完整 Webman 后端项目，可在安装依赖后直接启动。SandAdmin 基于 SaiAdmin 6.x 修改和维护，是独立的 PostgreSQL fork；它不是 SaiAdmin 官方发行版，也不代表获得上游背书。

核心和插件安装器由独立仓库 `supdger/sand-core`、`supdger/sand-package` 通过 Composer 安装，并将各自的 Webman 插件文件发布到 `plugin/sandadmin/`、`plugin/sandpackage/`。这些运行目录不是本仓库的长期源码权威。

`install/` 保存宿主 PostgreSQL 配置模板；`tests/` 保存宿主验证入口。Composer 安装不创建数据库、不执行迁移、不启停服务。后端安装、配置和首次验证请阅读[本地运行与首次安装](https://github.com/supdger/sandadmin/wiki/getting-started)。

Webman 是本项目使用的底层框架；其框架文档可在 [Webman 文档](https://webman.workerman.net/) 获取。框架的来源和授权不能替代 SandAdmin、SaiAdmin 及其他第三方组件的署名要求，完整说明见根目录的 [NOTICE](../NOTICE) 和[上游来源、署名与许可证说明](https://github.com/supdger/sandadmin/wiki/upstream-and-licensing)。

## 测试

- 单元测试：`vendor/bin/phpunit --testsuite unit`，不需要数据库。
- 集成测试：`vendor/bin/phpunit --testsuite integration`，从环境变量 `SAND_TEST_DB_URL` 读 PostgreSQL 连接串，格式 `pgsql://用户[:口令]@主机:端口/库名`（例：`pgsql://supdger@127.0.0.1:5432/sandadmin_itest`）。未设置或连不上时，依赖数据库的用例自动跳过。
- 目标库必须是已应用 0001 基线迁移的专用测试库：用例间会清空 `sand_system_*` 相关表并自建夹具账号，不要指向在用的开发库或生产库。
