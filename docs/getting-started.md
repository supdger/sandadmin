# 本地运行与首次安装

本文适用于全新的 SandAdmin PostgreSQL 开发或验证环境。它不提供 MySQL 原地迁移方案，也不替代生产变更流程。

> SandAdmin 当前基线为 `0.1.0`，仍处于早期验证阶段。文档中的可运行步骤不等同于生产就绪或 1.0 稳定性承诺。

## 前置条件

- PHP `>= 8.2`，版本要求以[后端 composer.json](../server/composer.json)为准。
- PostgreSQL 实例和一个可供初始化的目标数据库。
- Node.js `>= 20.19.0`、pnpm `>= 8.8.0`，版本要求以 [前端 package.json](../sandadmin-artd/package.json) 为准。

## 配置后端

1. 克隆 SandAdmin 仓库并进入 `server/`。
2. 执行 `composer install`，使用已提交的 `composer.lock` 安装后端依赖以及锁定版本的 `supdger/sand-core`、`supdger/sand-package`。
3. 首次安装前不要创建 `.env`，因为安装页会把它视为“已经安装”；`server/.env.example` 只用于字段参考和非交互环境。
4. 执行 `php start.php`，在 `/core/install` 填写已准备好的 PostgreSQL 数据库连接并由安装器生成 `.env`。

核心安装路由由 `server/plugin/sandadmin/config/route.php` 提供。仅对全新或已按自身流程备份并确认可初始化的数据库访问 `/install`。

Composer 安装阶段只发布后端 Webman 插件文件，不创建数据库、不执行 SQL、不启停服务。数据库初始化仍只能由明确访问安装流程触发。

## 配置前端

1. 进入同一 SandAdmin revision 的 `sandadmin-artd/`。
2. 按需将 `.env.example` 和 `.env.development.example` 复制为对应 `.env` 文件。
3. 执行 `pnpm install` 安装依赖。
4. 开发时执行 `pnpm dev`；需要构建验证时执行 `pnpm build`。

默认前端目录是 `sandadmin-artd`。服务和 Channel 端口分别由 `SANDADMIN_SERVER_PORT`、`SANDADMIN_CHANNEL_PORT` 控制；未配置时以后端配置的默认值为准。

## 首次验证

至少确认以下事项，再把环境视为可用于下一阶段测试：

1. 后端可启动，`http://localhost:8787/core/install` 可访问。
2. PostgreSQL 初始化完成后，以 `admin` / `123456` 登录管理后台，并在首次登录后立即修改默认密码。该初始凭据只适用于当前安装器创建的全新数据库；已安装实例不会被安装器重置管理员密码。
3. 前端生产构建通过，或开发服务能加载后台。
4. 若安装插件，按该插件 README 完成安装、权限授予和业务路径验证。

这些是本地/验证环境检查，不构成生产部署或业务验收结论。
