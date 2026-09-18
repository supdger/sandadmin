# 插件开发与发布约定

本页适用于计划随 SandAdmin 发布或独立分发的插件。无需独立市场平台的清单、打包和下载安装流程见[仓库插件分发](repository-plugin-distribution.md)。具体业务约束由插件自己的 README 补充。

当前源码候选的普通安装已回归 SaiPackage 上游流程，数据库适配及旧记录切换见
[SaiPackage PostgreSQL 适配](architecture/SAIPACKAGE_POSTGRESQL_ADAPTATION.md)。
下述 `sand_platform.required_plugins` 自动安装扩展属于旧安装线；新普通入口会明确拒绝
依赖该扩展的包。消费者须保留原锁定宿主，完成兼容验证后再切换。

## 必备交付物

每个可发布插件至少应包含：

- `README.md`：用途、非目标、宿主兼容范围、安装/升级/卸载、配置、权限、验证步骤和已知限制。
- 版本元数据与依赖声明，例如 `info.ini`、`config.json`。
- 对应的安装、升级、卸载生命周期实现和 PostgreSQL SQL。
- 后端与前端载荷的实际目录说明；若有宿主镜像副本，说明唯一权威来源与同步方式。
- 适用的许可证、来源与第三方署名文件，确保单独分发后仍可追溯。

若插件依赖另一个可选插件，`config.json` 的 `sand_platform.required_plugins` 必须声明依赖包名、最低兼容版本、发布包内相对路径和 SHA-256。发布构建负责写入实际校验值；源码占位值不能发布。安装器先复用已安装的兼容依赖，缺失时才安装校验通过的随包工件。依赖安装及跨插件服务目录注册须幂等，不能自动创建业务 application、credential 或 grant，也不能在安装失败时自动卸载共享依赖。

## PostgreSQL 规则

- 新业务表使用 `sand_<domain>_*` 前缀，并使用 PostgreSQL 语法和类型。
- 不将 MySQL 方言、会话变量或原地迁移承诺混入 PostgreSQL 安装包。
- 安装、升级、卸载必须对应实际版本生命周期；卸载的影响和数据保留策略要在 README 中写明。

## 发布前检查

1. 版本号、README、发布说明和实际包内容一致。
2. 在干净 PostgreSQL 验证环境完成安装；升级和卸载按插件风险单独验证。
3. 验证菜单、权限、后端加载和至少一条核心业务路径。
4. 对比发布包与权威源码，确认未漏掉前端、SQL、文档或许可证文件。
5. 在插件 README 与宿主兼容矩阵中更新可支持的版本范围，并记录消费工作区验证宿主的 SandAdmin revision。

静态检查或构建通过不能替代真实宿主安装和业务路径验收。
