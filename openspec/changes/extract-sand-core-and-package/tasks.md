## 1. 固定迁移基线

- [x] 1.1 记录 SandAdmin 当前修订、工作树、Composer 锁和核心/安装器/前端文件清单，并以校验和证明基线可复核
- [x] 1.2 检查 `/Users/code/project/sand_plugins` 的项目入口、工作树和既有权威源码，确认新增 `sand-core`、`sand-package` 不覆盖用户修改
- [x] 1.3 建立迁移映射，覆盖 `server/plugin/sandadmin`、`server/plugin/sandpackage`、兼容服务、完整核心前端及 SandPackage 专属前端，并通过无遗漏扫描验证

## 2. 建立 sand-core Composer 包

- [x] 2.1 创建统一的 `sand-core/server/`、`sand-core/sandadmin-artd/` 和 Composer 元数据，验证包名、许可证、依赖及归档排除项
- [x] 2.2 将当前核心后端和完整核心前端源码复制到 `sand-core` 权威目录，并以逐文件校验和证明与来源一致
- [x] 2.3 配置当前运行时命名空间、Webman 配置/路由/命令发现，使用独立 Composer 探针验证核心类和配置可加载
- [x] 2.4 实现显式前端源码发布入口、版本文件和基线清单，测试首次发布、幂等发布及未知修改拒绝

## 3. 建立 sand-package Composer 包

- [x] 3.1 创建统一的 `sand-package/server/`、`sand-package/sandadmin-artd/` 和 Composer 元数据，验证包名、许可证、依赖及归档排除项
- [x] 3.2 复制 SandPackage 后端、兼容服务、专属前端和测试，使用逐文件校验和及路径扫描证明映射完整
- [x] 3.3 配置当前运行时命名空间和 Webman 插件发现，运行现有 SandPackage 非数据库契约、状态机、仓库和恢复测试
- [x] 3.4 证明 Composer 安装/更新不会创建数据库、执行迁移或启停服务，并以安装日志和隔离探针记录结果

## 4. 切换 SandAdmin 宿主

- [x] 4.1 使用开发期 path repository 将 `server/composer.json` 改为依赖 `supdger/sand-core` 与 `supdger/sand-package`，执行 Composer validate/install 并核对锁文件
- [x] 4.2 在保留回滚副本的情况下移除宿主对内置核心和安装器的手工 PSR-4 权威映射，验证 autoload、路由、命令、配置和进程发现
- [x] 4.3 将宿主根前端切为 `sand-core` 发布基线并保留业务插件前端挂载能力，运行前端类型检查、Lint 和构建验证源码完整
- [x] 4.4 更新安装、开发、已有 Webman 接入、源码所有权和回滚文档，并用命令示例复核所有路径与包名

## 5. 消费者与生命周期验收

- [x] 5.1 在隔离的干净 Webman 消费者中仅通过 Composer 安装两个基础包，验证后端启动前静态加载和前端源码发布，不执行数据库写入
- [x] 5.2 在隔离 SandAdmin 宿主完成登录、菜单、权限与 SandPackage 管理页浏览器验收，记录后端、前端和锁定包版本
- [x] 5.3 使用中性 ZIP 插件验证安装、升级、失败回滚与卸载，区分源码、Composer、宿主运行和数据库生命周期证据
- [x] 5.4 运行 OpenSpec strict validation、相关 PHP 测试、Composer 检查、前端检查和变更质量扫描，审查无残留双权威源码后才标记迁移完成

## 6. 发布准备

- [x] 6.1 为 `sand-core` 与 `sand-package` 生成可发布归档并检查只包含预期源码、无 `node_modules`、缓存、密钥或本地配置
- [x] 6.2 形成独立仓库/版本发布清单和 SandAdmin 从 path repository 切换到稳定版本的步骤；未经明确授权不执行提交、推送或发布
