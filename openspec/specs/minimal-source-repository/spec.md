# Minimal Source Repository Specification

## Purpose

定义 SandAdmin 公开源码仓库的最小边界，使产品源码、必要文档、回归测试和发布兼容资源可维护，同时排除完整演示运行副本和过程产物。

## Requirements

### Requirement: 最小产品目录
SandAdmin 仓库 SHALL 以 `server/`、`sandadmin-artd/` 和 `docs/` 为主要源码目录，并只在根保留安装、许可证、版本控制和兼容发布所必需的文件。

#### Scenario: 检查公开仓库根目录
- **WHEN** 用户浏览 SandAdmin 源码仓库
- **THEN** 不会看到完整 Webman 消费者骨架、演示运行副本、临时报告或历史设计素材

### Requirement: 工程资产就近归属
后端测试、打包脚本和静态边界检查 MUST 放在 `server/` 内与其验证的载荷共同维护。

#### Scenario: 运行后端验证
- **WHEN** 维护者检查 SandAdmin 后端
- **THEN** 可以从 `server/` 下定位相关测试和脚本，不依赖多个根级维护目录

### Requirement: 未完成事项不得伪装删除
仓库精简 MUST 保留或迁移未完成变更的任务语义和证据边界，不得仅为减少目录而宣称工作已完成。

#### Scenario: 处理未完成 OpenSpec 变更
- **WHEN** 过程目录不再作为长期公开内容保留
- **THEN** 未完成任务先迁入仍可追踪的权威入口，再删除公开过程副本
