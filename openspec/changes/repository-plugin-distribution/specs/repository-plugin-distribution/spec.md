## Purpose

让 SandAdmin 使用者无需独立插件市场账号或服务，即可从维护者指定的公开仓库发现可选插件，选择兼容版本，验证发布包并进入既有安装升级流程，保持宿主零业务插件可用及安装恢复边界。

## ADDED Requirements

### Requirement: Repository discovery
系统 SHALL 从服务器配置的公开 GitHub 仓库清单展示插件及版本，不要求商店登录、购买或订单。

#### Scenario: Empty or unavailable repository
- **WHEN** 清单为空、不存在或网络请求失败
- **THEN** 页面区分暂无插件与读取失败，允许重试，不伪造插件或回退 SaiThink 平台

### Requirement: Verified package staging
系统 SHALL 仅允许超级管理员选择清单内版本，下载指定仓库的发布附件，校验 SHA-256、ZIP 内插件标识、版本及宿主兼容范围后准备安装候选。

#### Scenario: Valid package
- **WHEN** 管理员选择兼容且校验通过的版本
- **THEN** 该插件进入本地安装列表，用户继续通过既有安装或升级确认操作完成生命周期

#### Scenario: Invalid or changed package
- **WHEN** 清单变化、校验值不匹配、包身份不一致、宿主版本不兼容或下载超限
- **THEN** 拒绝候选写入并报告具体失败，不执行 SQL 或重启服务

#### Scenario: Existing recovery state
- **WHEN** 宿主存在需恢复状态或无法确认本地列表
- **THEN** 界面禁止下载写入，服务端保留安装器已有的状态和锁检查

### Requirement: Independent package publication
项目 SHALL 支持单插件源码生成安装器可接受的 ZIP 和版本清单条目，并保持主体无需安装任何业务插件即可运行。

#### Scenario: Build a plugin package
- **WHEN** 对具备元数据、前后端载荷和生命周期文件的合法插件执行打包
- **THEN** 输出根部具有 info.ini 的独立 ZIP、SHA-256 和版本清单条目，不自动上传或安装

#### Scenario: Declared runtime dependencies
- **WHEN** 插件发布契约明确声明插件本地运行依赖目录及文件数量、树摘要
- **THEN** 打包器 SHALL 仅在源码及最终ZIP均匹配声明且满足原有数量、体积、安全路径边界时包含这些依赖；缺少声明或内容变化必须拒绝
