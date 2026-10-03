## Why

公开主线已经锁定 Sand Core 0.2.0 和 SandPackage 0.2.1，但下载源码内的前端清单仍停留在 0.1.7，公开宿主版本仍为 0.1.1。需要发布可复核的 0.2.0 宿主组合，并让维护者能及时收到基础包更新。

## What Changes

- 将标准 Composer 安装实际发布的 Core 0.2.0 / SandPackage 0.2.1 前端源码和清单纳入宿主发行。
- README 与 CHANGELOG 声明 0.2.0 组合、系统更新前提及验证边界。
- 新增只检查两基础包的 Dependabot 分组 PR，不自动合并或发布。
- 保留既有宿主覆盖层；不写数据库、不修改消费宿主或基础包源码。

## Capabilities

### New Capabilities

- `host-foundation-release`: 宿主源码发行与锁定基础包载荷一致，基础包新版通过可审查 PR 跟进。

### Modified Capabilities

无。

## Impact

影响管理前端受管载荷、发布文档和依赖更新配置。Composer 约束和锁文件已在公开主线对齐，本轮不重复变更。后台升级执行仍由已发布基础包实现。
