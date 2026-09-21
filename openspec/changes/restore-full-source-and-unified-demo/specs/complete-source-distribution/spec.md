## Purpose

规定 SandAdmin 作为完整前后端源码项目的可观察安装、启动、源码所有权和兼容边界，使新用户能够从一次仓库克隆开始完成依赖安装与初始化。

## ADDED Requirements

### Requirement: 完整源码克隆
SandAdmin 仓库 SHALL 在同一 revision 中提供可安装的 Webman `server/` 项目和可构建的 `sandadmin-artd/` 管理前端，并保留许可证、来源说明和必要公共文档。

#### Scenario: 新用户克隆仓库
- **WHEN** 用户克隆 SandAdmin 的一个已发布 revision
- **THEN** 仓库包含 `server/`、`sandadmin-artd/` 及各自的依赖清单
- **THEN** 用户不需要另外下载 SandAdmin Composer 后端包即可获得核心后端源码

### Requirement: 后端项目独立安装
`server/` SHALL 是一个完整 Webman project，其依赖安装 MUST NOT 要求 `supdger/sandadmin` 自依赖，且安装后 SHALL 能加载 SandAdmin 与 SandPackage 核心代码和维护命令。

#### Scenario: 安装后端依赖
- **WHEN** 用户在干净克隆的 `server/` 中执行 `composer install`
- **THEN** Composer 使用 `server/composer.json` 和锁文件安装依赖
- **THEN** Webman 命令清单能够发现 SandAdmin 与 SandPackage 命令

### Requirement: 前端项目独立安装
`sandadmin-artd/` SHALL 提供完整 Vue 管理端源码、依赖锁文件和环境模板，其默认开发代理 MUST 与后端默认监听端口一致。

#### Scenario: 安装并构建前端
- **WHEN** 用户在干净克隆的 `sandadmin-artd/` 中安装锁定依赖并执行生产构建
- **THEN** 构建成功且不依赖仓库外的未记录源码
- **THEN** 默认开发代理指向 SandAdmin 后端默认端口

### Requirement: 零业务插件宿主
SandAdmin 源码仓库 SHALL 只包含核心 SandAdmin、SandPackage 和通用扩展契约，MUST NOT 持久包含 SandAI、SandIAM、SandWorkflow 或其他业务插件运行副本。

#### Scenario: 检查发布源码
- **WHEN** 对发布 revision 执行零业务插件检查
- **THEN** `server/plugin/` 与管理端插件目录中不存在具体 Sand 业务插件源码或安装副本

### Requirement: 历史 Composer 发行兼容
停止 Composer 后端包路线后，项目 MUST 保留已发布标签、Release 和 Packagist 版本的历史可追溯性，并 SHALL 在当前文档中明确完整源码仓库是新的主安装入口。

#### Scenario: 既有消费者查看历史版本
- **WHEN** 既有消费者仍锁定 `supdger/sandadmin:6.1.5`
- **THEN** 对应历史标签和发布记录仍可获取
- **THEN** 当前安装文档不会再把新 Composer 后端包描述为主线交付方式
