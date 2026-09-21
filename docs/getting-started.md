# 本地运行与首次安装

本文适用于全新的 SandAdmin PostgreSQL 开发或验证环境。它不提供 MySQL 原地迁移方案，也不替代生产变更流程。

## 前置条件

- PHP `>= 8.1`，版本要求以[根 composer.json](../composer.json)为准。
- PostgreSQL 实例和一个可供初始化的目标数据库。
- Node.js `>= 20.19.0`、pnpm `>= 8.8.0`，版本要求以 [前端 package.json](../sandadmin-artd/package.json) 为准。

## 配置后端

1. 在消费目录创建标准 Webman：`composer create-project workerman/webman server`。
2. 执行 `composer require supdger/sandadmin:6.1.5-rc.1`，从 Packagist 安装已发布版本，并提交消费者的 `composer.lock`。
3. 确认消费者 `config/database.php` 和 `config/think-orm.php` 使用包内模板；首次安装前不要创建 `.env`，因为安装页会把它视为“已经安装”。
4. 使用 Webman 标准方式启动，例如 `php start.php start`，在 `/install` 填写 PostgreSQL 连接并由安装器生成 `.env`。已有实例或非交互部署才参考 `server/install/.env.example` 合并环境字段。

核心安装路由由 `server/plugin/sandadmin/config/route.php` 提供。仅对全新或已按自身流程备份并确认可初始化的数据库访问 `/install`。

## 配置前端

1. 从锁定的 SandAdmin revision 取得 `sandadmin-artd/` 消费副本。
2. 执行 `corepack pnpm install` 安装依赖。
3. 开发时执行 `corepack pnpm dev`；需要构建验证时执行 `corepack pnpm build`。

默认前端目录是 `sandadmin-artd`。服务和 Channel 端口分别由 `SANDADMIN_SERVER_PORT`、`SANDADMIN_CHANNEL_PORT` 控制；未配置时以后端配置的默认值为准。

## 首次验证

至少确认以下事项，再把环境视为可用于下一阶段测试：

1. 后端可启动，安装页可访问。
2. PostgreSQL 初始化完成后，以 `admin` / `123456` 登录管理后台，并在首次登录后立即修改默认密码。该初始凭据只适用于当前安装器创建的全新数据库；已安装实例不会被安装器重置管理员密码。
3. 前端生产构建通过，或开发服务能加载后台。
4. 若安装插件，按该插件 README 完成安装、权限授予和业务路径验证。

这些是本地/验证环境检查，不构成生产部署或业务验收结论。
