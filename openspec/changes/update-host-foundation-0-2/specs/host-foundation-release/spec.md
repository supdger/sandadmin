## Purpose

使下载 SandAdmin 源码的维护者获得与锁定基础包一致的前后端载荷，并通过可审查的依赖更新请求及时了解基础包新发行；每次更新依然需要维护者验证宿主组合与消费环境。

## ADDED Requirements

### Requirement: 源码发行与基础包一致

SandAdmin 源码发行 SHALL 包含锁定 Sand Core 0.2.0 和 SandPackage 0.2.1 对应的受管前端载荷及清单，标准 Composer 安装 MUST 成功发布对应后端而不写入数据库。

#### Scenario: 全新源码安装
- **WHEN** 维护者下载源码或克隆仓库并在 server 执行 composer install
- **THEN** 安装记录、后端实际加载路径与前端清单分别对应 Core 0.2.0 / SandPackage 0.2.1
- **THEN** 宿主私有配置及数据库不会被初始化或改写

### Requirement: 基础包通过审查请求跟进

宿主 SHALL 每个工作日（周一至周五）北京时间 04:00 调度检查两个基础 Composer 包并将可用更新提出为分组 PR，MUST 保留依赖约束和锁文件的可审查变化，MUST NOT 自动合并、发布或同步消费者。

#### Scenario: 新基础包发行
- **WHEN** Core 或 SandPackage 有可用更新
- **THEN** 宿主维护者收到依赖 PR，并可在回归后决定是否发布新的宿主组合
