## Why

现有插件市场依赖 SaiThink 独立平台的账号、订单和下载接口，SandAdmin 没有该平台。用户需要直接读取自己的 GitHub 仓库及版本安装包，完成按需安装和升级。

## What Changes

- 新增公开 GitHub 仓库清单读取、Release ZIP 校验下载及既有安装器衔接。
- **BREAKING**：移除 SaiThink 商店代理、账号、购买与订单界面，改为插件仓库。
- 仓库显示本机真实安装状态与允许动作，一次确认串联下载和现有安装/升级；本地页改为插件管理。
- 从校验过的版本包只读加载README文档，异常安装状态也可查看。
- 增加单插件打包命令和清单格式；清单初始为空，不伪造可用插件。
- 同仓可保存可选插件发布源码，但不自动安装；现有业务插件权威来源迁移另行实施。

## Capabilities

### New Capabilities
- `repository-plugin-distribution`: 无独立市场服务的插件发现、版本选择、校验下载和安装衔接。

### Modified Capabilities

无。

## Impact

影响 SandPackage 控制器、路由、仓库下载组件、管理界面、打包脚本及仓库治理文档。不增加数据库表，不执行迁移、宿主同步、重启、提交或发布；复用现有 PostgreSQL 安装生命周期。
