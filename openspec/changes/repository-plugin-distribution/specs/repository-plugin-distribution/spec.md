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
- **THEN** 仓库界面在用户确认后串联候选准备和既有安装或升级入口，并刷新真实安装状态；无需切换页面再次点击安装

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

### Requirement: Local state aware actions
系统 SHALL 以本机实际安装记录、运行目录与已有生命周期闸门计算仓库动作，插件管理集中提供适用的卸载、依赖和恢复入口。

#### Scenario: Installed or blocked plugin
- **WHEN** 已安装同版本、已安装更高版本，或记录与运行文件不一致
- **THEN** 分别显示已安装、禁止降级或需处理状态；禁止重复安装或覆盖异常记录，显示原因并可跳转管理

#### Scenario: Direct installation or upgrade
- **WHEN** 管理员选择可操作版本并确认
- **THEN** 串联既有下载及安装HTTP请求，核对候选身份和升级源版本；全程防重，真实状态未达到已安装时不报告成功

### Requirement: Read-only version documentation
系统 SHALL 在无写入权限状态下仍允许已认证超级管理员读取所选版本的校验后README文本。

#### Scenario: Display package documentation
- **WHEN** 用户选择查看文档
- **THEN** 从已配置仓库的对应Release核验包后返回有界UTF8文本，不创建候选或执行SQL；界面不执行文档HTML，显示加载、失败、空内容状态且不会串页

### Requirement: Accurate and understandable plugin presentation
系统 SHALL 区分安装文件缺失与数据库升级失败，默认视图围绕浏览及管理任务展示，技术恢复流程仅主动展开。

#### Scenario: Missing runtime is not failed database upgrade
- **WHEN** 普通安装记录被阻断或插件运行目录缺失，且没有真实失败升级证据
- **THEN** 显示实际异常原因，不显示数据库升级未完成、空来源版本箭头或升级恢复流程；该插件普通写入仍被禁止

#### Scenario: Recovery details are opt-in
- **WHEN** 用户打开插件仓库或插件管理默认视图
- **THEN** 不展开恢复步骤；只有主动选择真实失败升级的恢复处理时才打开对应详情，刷新使条件失效时清除旧操作视图
