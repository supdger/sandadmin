# SandAdmin 后端

此目录是 SandAdmin 的完整 Webman 后端项目，可在安装依赖后直接启动。SandAdmin 基于 SaiAdmin 6.x 修改和维护，是独立的 PostgreSQL fork；它不是 SaiAdmin 官方发行版，也不代表获得上游背书。

核心和插件安装器由独立仓库 `supdger/sand-core`、`supdger/sand-package` 通过 Composer 安装，并将各自的 Webman 插件文件发布到 `plugin/sandadmin/`、`plugin/sandpackage/`。这些运行目录不是本仓库的长期源码权威。

`install/` 保存宿主 PostgreSQL 配置模板；`tests/` 和 `scripts/` 保存宿主验证与维护入口。Composer 安装不创建数据库、不执行迁移、不启停服务。后端安装、配置和首次验证请阅读[本地运行与首次安装](../docs/getting-started.md)。

Webman 是本项目使用的底层框架；其框架文档可在 [Webman 文档](https://webman.workerman.net/) 获取。框架的来源和授权不能替代 SandAdmin、SaiAdmin 及其他第三方组件的署名要求，完整说明见根目录的 [NOTICE](../NOTICE) 和[上游来源、署名与许可证说明](../docs/upstream-and-licensing.md)。
