## Purpose

定义 SandAdmin 与 Sand 插件共享的唯一演示和验收宿主，使运行副本、源码权威和破坏性生命周期环境彼此可识别且不会反向污染。

## ADDED Requirements

### Requirement: 唯一演示宿主
SandAdmin 与 Sand 插件的日常演示入口 SHALL 使用 `/Users/code/project/sand_demo`，后端与前端分别位于 `server/` 和 `sandadmin-artd/`。

#### Scenario: 启动日常演示
- **WHEN** 维护者需要演示零插件 SandAdmin 或已安装的 Sand 插件
- **THEN** 使用 `sand_demo` 中锁定版本的消费者副本，而不是从 SandAdmin 或 sand_plugins 源码目录直接启动

### Requirement: 单向消费
`sand_demo` MUST 只消费 SandAdmin 与各插件权威仓库的明确版本，演示环境中的修改不得反向成为源码。

#### Scenario: 演示发现缺陷
- **WHEN** 演示宿主暴露需要修复的问题
- **THEN** 修复在对应权威源码仓库完成并重新安装或同步到 `sand_demo`

### Requirement: 区分日常演示与破坏性验收
安装、升级和卸载等破坏性验证 MUST 使用可恢复的隔离状态，不得无条件破坏长期演示数据。

#### Scenario: 验证卸载
- **WHEN** 发布候选需要执行插件卸载验收
- **THEN** 验收使用明确隔离或可恢复的宿主与数据库状态，并记录宿主和插件版本
