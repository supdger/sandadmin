## Purpose

确保 SandAdmin 文件缓存标签在多个工作进程同时读写、过期及清理时保持集合一致性，让后台首屏接口可靠读取对应权限属性，损坏缓存只触发安全失效重建而不会成为业务异常或跨角色污染。

## ADDED Requirements

### Requirement: File cache tags remain consistent across workers

宿主 SHALL 保证标签首次写入、追加、过期和清理在并发访问下不暴露部分文件或丢失集合成员，保留不同角色和缓存键之间的隔离。

#### Scenario: Cold and hot concurrent access

- **WHEN** 40 个工作进程执行至少 1,000 轮冷缓存与热缓存并发读写
- **THEN** 不出现 `only array cache can be push`，完整属性保持可读取，标签清理不遗漏已登记成员

#### Scenario: Expiration and clear race

- **WHEN** 标签写入与过期或清理并发发生
- **THEN** 返回一致的缓存命中或缺失，不出现非数组中间态和跨角色数据

### Requirement: Corrupt tag values recover safely

宿主 SHALL 在遇到损坏标签时安全失效相关缓存后重建，并记录一次去敏告警，不将旧权限缓存遗留为无法清理的数据。

#### Scenario: Damaged tag file

- **WHEN** 标签缓存无法解析或不是数组
- **THEN** 请求可以重建缓存，告警不包含令牌、缓存内容或角色敏感信息
