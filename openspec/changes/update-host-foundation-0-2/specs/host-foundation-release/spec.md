## Purpose

使下载 SandAdmin 源码的维护者获得与锁定基础包一致的前后端载荷，并由基础包维护方主动交接新发行的具体版本和验证结果；每次更新依然需要维护者验证宿主组合与消费环境。

## ADDED Requirements

### Requirement: 源码发行与基础包一致

SandAdmin 源码发行 SHALL 包含锁定 Sand Core 0.2.0 和 SandPackage 0.2.1 对应的受管前端载荷及清单，标准 Composer 安装 MUST 成功发布对应后端而不写入数据库。

#### Scenario: 全新源码安装
- **WHEN** 维护者下载源码或克隆仓库并在 server 执行 composer install
- **THEN** 安装记录、后端实际加载路径与前端清单分别对应 Core 0.2.0 / SandPackage 0.2.1
- **THEN** 宿主私有配置及数据库不会被初始化或改写

### Requirement: 基础包提供方主动交接宿主

基础包维护方完成修复并发布后 SHALL 主动向宿主交接版本、固定源码引用、变更内容、宿主兼容范围和验证结果。宿主 MUST 据此跟进依赖约束、锁文件及匹配的前后端载荷，组合验收后发布；测试和部署宿主 MUST 再同步明确版本并核对实际加载版本。

#### Scenario: 新基础包发行
- **WHEN** Core 或 SandPackage 的维护方完成修复并发布
- **THEN** 宿主维护者收到提供方的版本和验证交接，跟进实际依赖与发布载荷，完成组合验收后发布
- **THEN** 测试和部署宿主再同步该明确版本并核对实际加载版本
