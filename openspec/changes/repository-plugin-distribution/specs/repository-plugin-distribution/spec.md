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

### Requirement: Durable installation state
系统 SHALL 将新实例的安装登记、候选及恢复数据保存到server/storage/sandpackage，统一生命周期的存储根；GET读取不得迁移或补登记。

#### Scenario: Runtime cache removal
- **WHEN** 新实例已经在持久根保存插件安装数据，runtime目录被清理
- **THEN** 安装登记与恢复数据仍然存在，管理状态不因缓存清理而丢失

#### Scenario: Legacy storage compatibility and conflict
- **WHEN** 仅旧runtime/sandpackage存在数据
- **THEN** 整根兼容旧位置，使用显式维护迁移入口；新旧根同时含数据时报告冲突并拒绝生命周期写入，不静默合并或忽略记录

#### Scenario: Explicit storage migration
- **WHEN** 管理员在维护窗口显式执行迁移
- **THEN** 仅在锁可获得且无未结束事务、恢复或不安全路径绑定时原子搬迁完整旧根，保留数据内容；检查失败保持原根不变，干跑不写文件

### Requirement: Reconciled local plugin inventory
系统 SHALL 合并持久或兼容根登记与实际应用插件目录，对不一致状态明确呈现，不以目录存在证明安装完成。

#### Scenario: Unregistered application plugin
- **WHEN** 实际应用插件目录具有有效身份元数据但无安装登记
- **THEN** 管理列表显示未登记且禁止普通安装覆盖，不执行插件PHP或隐式注册

#### Scenario: Invalid or missing files
- **WHEN** 登记存在但运行文件缺失，或可识别插件元数据损坏
- **THEN** 显示对应异常，不漏列或误报已安装，不跟随符号链接读取目录外数据

### Requirement: Abnormal plugin cleanup and reinstall
系统 SHALL 为运行文件缺失的有效安装登记提供可理解的清理入口，完成数据库、菜单与文件登记处理后允许重新安装，不要求重装宿主。

#### Scenario: Confirm cleanup scope
- **WHEN** 管理员检查异常插件
- **THEN** 展示现存业务表、菜单及将归档文件，明确数据删除影响；未明确确认或现场指纹变化时不执行清理

#### Scenario: Cleanup and reinstall
- **WHEN** 管理员确认当前清理范围且不存在外部依赖冲突
- **THEN** 在事务内清理限定的表和菜单，归档插件残留文件及登记，返回未安装并允许重新安装；宿主和其他插件数据保持不变

#### Scenario: Interrupted cleanup
- **WHEN** 数据库提交回应丢失或后续归档未完成
- **THEN** 保留可继续清理的管理入口，依据现场与持久记录判断是否仅需归档，不能盲目重放SQL或以删除登记掩盖数据库残留

#### Scenario: Permission menus stored in slug
- **WHEN** 插件权限节点以 slug 保存权限标识而 code 为空
- **THEN** 可通过与插件标识严格绑定的 slug/component/path 条件选取，并继续证明路径或组件祖先归属及子节点覆盖；不得用空 code 或菜单名称扩大删除范围

### Requirement: Readable repository cards and menu labels
插件仓库 SHALL 在长名称和窄屏下保持标题、状态和操作清晰分组；权限菜单名称 SHALL 明确对象与动作。宿主新装 SHALL 不再预置默认文档外链。

#### Scenario: Long plugin metadata
- **WHEN** 插件名称、简介或作者较长
- **THEN** 状态不挤占标题，主次操作明确且不溢出；完整名称可查看

#### Scenario: Existing upstream document link
- **WHEN** 旧实例仍保留指向上游站点的默认 Document 外链
- **THEN** 可定向移除该记录并清理菜单缓存，不影响其他菜单及插件权限

#### Scenario: Orphan plugin permissions
- **WHEN** 插件历史权限记录的父节点不存在
- **THEN** 在插件权威来源修正安装生成和存量映射，保持逻辑权限标识及角色授权，不通过隐藏或盲删权限掩盖问题
