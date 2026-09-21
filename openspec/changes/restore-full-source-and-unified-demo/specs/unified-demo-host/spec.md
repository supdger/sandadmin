## Purpose

规定 `sand_demo` 作为 SandAdmin 与独立 Sand 插件唯一演示和验收宿主的同步、版本锁定、运行数据保护及旧演示宿主退役行为。

## ADDED Requirements

### Requirement: 单一演示宿主
`/Users/code/project/sand_demo` SHALL 是 SandAdmin 与 Sand 插件的默认演示、浏览器验证和真实生命周期验收宿主；`sand_plugins` 内旧宿主 MUST NOT 继续作为并行演示入口。

#### Scenario: 选择演示目标
- **WHEN** 需要演示 SandAdmin 或任一独立 Sand 插件
- **THEN** 执行者使用 `sand_demo`
- **THEN** 不在 `sand_plugins` 的旧宿主启动第二套并行演示

### Requirement: 单向源码同步
SandAdmin 核心源码 SHALL 从 `sandadmin` 单向同步到 `sand_demo`，插件源码或不可变发布包 SHALL 从各插件权威仓库单向进入 `sand_demo`，MUST NOT 从演示宿主反向修改权威源码。

#### Scenario: 更新演示版本
- **WHEN** `sand_demo` 更新 SandAdmin 或插件
- **THEN** 更新记录包含来源仓库、revision 或版本及同步方向
- **THEN** 演示目录中的临时修复不会被当作权威源码发布

### Requirement: 运行状态保护
职责迁移 SHALL 保留 `sand_demo` 的本机环境配置、数据库、运行存储、插件候选、备份和验收证据，MUST NOT 隐式创建数据库、执行迁移、卸载插件或重启服务。

#### Scenario: 同步完整宿主源码
- **WHEN** 将 SandAdmin revision 同步到 `sand_demo`
- **THEN** 只更新明确归属源码的文件
- **THEN** 本机 `.env`、数据库、运行存储及已安装插件状态保持不变，除非另有明确授权

### Requirement: 旧演示宿主安全退役
`sand_plugins` 内旧演示宿主、宿主锁和同步入口 SHALL 在确认 `sand_demo` 接管后退役；退役 MUST 避免覆盖或删除 `sand_plugins` 中未提交的插件源码和历史验收材料。

#### Scenario: 处理脏的 sand_plugins 工作区
- **WHEN** `sand_plugins` 存在未提交插件工作或未跟踪验收材料
- **THEN** 迁移仅触及已确认的旧演示宿主与同步入口
- **THEN** 其他修改和未跟踪材料保持原状
- **THEN** 被移除的本地宿主先进入明确的可恢复备份位置

### Requirement: 分层验收证据
演示宿主同步后 SHALL 分别记录源码同步、依赖安装、静态检查、启动、数据库连接、登录、插件安装升级卸载和业务路径的证据，不得以较低层证据替代较高层验收。

#### Scenario: 仅完成无数据库验证
- **WHEN** 只完成依赖安装、命令加载和前端构建
- **THEN** 交付报告明确标为源码或构建验证
- **THEN** 不宣称数据库初始化、登录或插件生命周期已经通过
