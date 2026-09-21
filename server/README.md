# SandAdmin 后端

此目录是 SandAdmin 的 Composer 后端载荷，不是可直接启动的 Webman 项目。SandAdmin 基于 SaiAdmin 6.x 修改和维护，是独立的 PostgreSQL fork；它不是 SaiAdmin 官方发行版，也不代表获得上游背书。

`Install.php` 将 `plugin/sandadmin/` 和 `plugin/sandpackage/` 安装到已有 Webman 消费者；`install/` 保存 PostgreSQL 配置模板；`tests/` 和 `scripts/` 保存后端验证与维护入口。后端安装、配置和首次验证请阅读[本地运行与首次安装](../docs/getting-started.md)。

Webman 是本项目使用的底层框架；其框架文档可在 [Webman 文档](https://webman.workerman.net/) 获取。框架的来源和授权不能替代 SandAdmin、SaiAdmin 及其他第三方组件的署名要求，完整说明见根目录的 [NOTICE](../NOTICE) 和[上游来源、署名与许可证说明](../docs/upstream-and-licensing.md)。
