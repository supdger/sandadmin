# 本地运行与首次安装

本文适用于全新的 SandAdmin PostgreSQL 开发或验证环境。它不提供 MySQL 原地迁移方案，也不替代生产变更流程。

## 前置条件

- PHP `>= 8.1`，版本要求以 [server/composer.json](../server/composer.json) 为准。
- PostgreSQL 实例和一个可供初始化的目标数据库。
- Node.js `>= 20.19.0`、pnpm `>= 8.8.0`，版本要求以 [前端 package.json](../sandadmin-artd/package.json) 为准。

## 配置后端

1. 复制 `server/.env.pgsql.example` 为未纳入版本控制的 `server/.env`。
2. 在 `server/.env` 填写目标 PostgreSQL 连接信息；不要提交该文件或任何密码、密钥、令牌。
3. 进入 `server/` 后执行 `composer install`。
4. 使用 Webman 的标准启动方式启动服务，例如 `php start.php start`；守护或生产运行方式应由部署环境决定。

核心安装路由由 `server/plugin/sandadmin/config/route.php` 提供。仅对全新或已按自身流程备份并确认可初始化的数据库访问 `/install`。

## 配置前端

1. 进入 `sandadmin-artd/`。
2. 执行 `corepack pnpm install` 安装依赖。
3. 开发时执行 `corepack pnpm dev`；需要构建验证时执行 `corepack pnpm build`。

默认前端目录是 `sandadmin-artd`。服务和 Channel 端口分别由 `SANDADMIN_SERVER_PORT`、`SANDADMIN_CHANNEL_PORT` 控制；未配置时以后端配置的默认值为准。

## 首次验证

至少确认以下事项，再把环境视为可用于下一阶段测试：

1. 后端可启动，安装页可访问。
2. PostgreSQL 初始化完成且能以创建的管理员身份登录。
3. 前端生产构建通过，或开发服务能加载后台。
4. 若安装插件，按该插件 README 完成安装、权限授予和业务路径验证。

这些是本地/验证环境检查，不构成生产部署或业务验收结论。
