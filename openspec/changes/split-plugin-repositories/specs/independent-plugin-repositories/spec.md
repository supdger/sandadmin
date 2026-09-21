## Purpose

让 SandAdmin 通过一份统一可信目录发现插件，同时从各插件自己的公开仓库获取版本、文档和安装包，使宿主源码与可选插件发布生命周期保持独立。

## ADDED Requirements

### Requirement: Independent repository declaration
目录中的每个插件 SHALL 明确声明其公开 GitHub 仓库；宿主 SHALL 只使用服务端目录中已验证的仓库标识构造 Release 地址。

#### Scenario: Read an independent plugin release
- **WHEN** 目录条目声明合法仓库、版本、标签、附件和摘要
- **THEN** 系统从该插件仓库下载对应 Release 附件，不从 SandAdmin 仓库猜测同名资产

#### Scenario: Reject an unsafe repository
- **WHEN** 插件仓库标识为空、包含路径穿越、URL、查询参数或不符合 owner/repository 格式
- **THEN** 系统拒绝整个目录，不发起附件下载或写入安装候选

### Requirement: Unified trusted catalogue
SandAdmin SHALL 维护统一目录和兼容信息；浏览器 MUST NOT 提交任意仓库或附件 URL 来改变下载来源。

#### Scenario: User selects a plugin version
- **WHEN** 管理员选择目录内版本并确认安装、升级、文档或清理包操作
- **THEN** 服务端重新读取目录，核对插件仓库、版本和摘要后再访问已声明的 Release

### Requirement: Host remains plugin-free
SandAdmin 主体源码和发行内容 SHALL 不包含业务插件源码、运行副本或插件 Release 附件，未选择插件的用户可只获取并运行宿主。

#### Scenario: Download or clone SandAdmin
- **WHEN** 使用者获取 SandAdmin 主体源码或发行包
- **THEN** 内容只包含宿主、安装器、统一目录和公共契约，不携带 SandIAM、SandWorkflow、SandAI 的业务实现

### Requirement: Traceable plugin repositories
每个独立插件仓库 SHALL 包含自身源码、README、许可证、生命周期文件、版本元数据和可追溯发布记录。

#### Scenario: Inspect a plugin repository
- **WHEN** 维护者或使用者访问独立插件仓库
- **THEN** 可从仓库和 Release 确定插件版本、宿主兼容范围、来源提交、安装升级卸载说明及已知限制

### Requirement: Safe repository migration
仓库迁移 SHALL 先发布并验证独立仓库资产，再切换统一目录；只有新来源可读取且摘要一致后，才可删除宿主中被替代的插件 Release。

#### Scenario: Successful migration
- **WHEN** 新仓库 Release 可下载、包身份与摘要通过校验且统一目录已经发布
- **THEN** 系统使用新仓库，旧 SandAdmin 插件 Release 可按明确清单删除，已安装插件及数据库状态保持不变

#### Scenario: Migration verification fails
- **WHEN** 新仓库不存在、附件不可读、摘要不一致或目录尚未发布
- **THEN** 停止切换和旧资产清理，保留当前可用来源
