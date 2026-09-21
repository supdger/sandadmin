## Purpose

定义 SandAdmin 后端作为可安装 Webman 组件的公开交付契约，使源码仓库无需携带完整运行骨架，同时保护消费者配置、持久数据和前端独立交付边界。

## ADDED Requirements

### Requirement: Composer 安装后端载荷
SandAdmin SHALL 提供可被 Webman Composer 插件钩子识别的 library 包，并将核心后台与通用包管理载荷安装到消费者的插件目录。

#### Scenario: 安装到标准 Webman 消费者
- **WHEN** 用户在受支持的 Webman 项目中通过 Composer 安装 SandAdmin
- **THEN** 消费者获得可加载的 SandAdmin 与 SandPackage 插件，而 SandAdmin 源码仓库不要求携带该消费者的 Webman 运行骨架

### Requirement: 保留消费者所有状态
SandAdmin 安装或更新 MUST NOT 覆盖消费者的环境文件、业务插件、上传文件、运行日志或 SandPackage 持久安装登记。

#### Scenario: 更新已有消费者
- **WHEN** 已安装 SandAdmin 的消费者执行 Composer 更新
- **THEN** 核心载荷按版本更新，消费者拥有的配置和持久状态保持不变

### Requirement: 前端独立交付
Composer 安装 SHALL 只负责后端载荷；`sandadmin-artd` MUST 作为独立前端源码安装和构建。

#### Scenario: 安装后端包
- **WHEN** 用户仅执行 Composer 安装
- **THEN** 安装过程不隐式运行 pnpm、不复制前端工作树，也不要求 Node.js 才能完成后端安装

### Requirement: 目录兼容
已发布的插件目录地址 MUST 在兼容迁移完成前继续可读取。

#### Scenario: 旧客户端读取目录
- **WHEN** 旧版 SandAdmin 请求仓库根 `plugins/catalog.json`
- **THEN** 目录仍返回有效清单，不因源码根目录精简而变成 404
