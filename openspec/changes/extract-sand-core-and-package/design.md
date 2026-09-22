## Context

当前 SandAdmin 在一个仓库内保存完整 Webman `server/`、`server/plugin/sandadmin`、`server/plugin/sandpackage`、兼容服务和完整 `sandadmin-artd`。宿主通过根项目的手工 PSR-4 映射加载两个内置插件；SandPackage 已包含 PostgreSQL 生命周期、仓库下载、失败恢复等扩展，不能按上游小包直接替换。

现有业务插件采用 ZIP 根载荷和 SandPackage 生命周期。此次拆分必须保持 PostgreSQL、权限、路由、命令和插件恢复行为，不把 Composer 安装误作数据库或运行时验收。

## Goals / Non-Goals

**Goals:**

- 让 `sandadmin` 成为只消费已锁定基础包的标准 Webman 宿主。
- 让 `sand-core`、`sand-package` 使用一致的 `server/`、`sandadmin-artd/` 源码布局。
- 让已有 Webman 通过 Composer 取得完整且版本一致的核心前后端源码。
- 保持当前 PHP 命名空间、数据库契约和业务插件 ZIP 生命周期，降低首轮拆分风险。

**Non-Goals:**

- 本次不全面重命名 `plugin\sandadmin`、`plugin\sandpackage` 或兼容命名空间。
- 本次不将 SandIAM、SandAI、SandWorkflow 转换为 Composer 包。
- 本次不自动创建数据库、执行迁移、启动服务、部署、提交或推送。
- 本次不以静态检查替代真实宿主安装、浏览器或插件生命周期验收。

## Decisions

### 1. 包内采用目标布局，不使用顶层 `src`

`sand-core` 与 `sand-package` 都使用：

```text
<package>/
+-- server/
+-- sandadmin-artd/
+-- composer.json
```

后端在各自 `server/plugin/<name>/` 下，前端使用实际目标结构。这样与业务 ZIP 的目标布局一致，导出和差异核对不需要在不同目录语义之间转换。

备选方案是 PHP 放 `src/`、前端放同级目录；因用户明确要求目录语义统一且现有代码大量依赖插件目标路径，不采用。

### 2. Composer 包包含前端源码，但不处理依赖安装或编译

Composer dist 同时包含后端和 `sandadmin-artd` 源码，不包含 `node_modules`，也不运行 `pnpm install`、`pnpm dev` 或构建。包提供显式发布命令，将前端源码复制到指定的非 `vendor` 目录。

发布命令记录包版本和文件清单；重复发布仅在目标仍匹配上一基线或用户显式选择新目录时进行，防止覆盖开发者修改。

备选方案是前端只留在 SandAdmin 仓库；这会迫使已有 Webman 消费者另行克隆并自行匹配版本，不采用。

### 3. 首轮拆分保持运行时命名空间

独立包的 Composer autoload 暂时继续映射当前 `plugin\sandadmin`、`plugin\sandpackage` 及必要兼容命名空间，优先证明来源拆分前后行为等价。新的品牌命名空间迁移需要独立变更。

### 4. SandAdmin 用本地 path repository 完成迁移验证后再切远端版本

第一阶段在工作区以 Composer path repository 接入 `sand-core`、`sand-package`，生成锁文件并完成干净安装验证。通过后才能发布独立版本并将宿主切到稳定版本约束。

path repository 只用于开发验证，不作为公开发行的最终配置。

### 5. 源码迁移采用复制、比对、切换、再删除

先复制当前权威内容到新包，生成文件清单并逐字节比对；再切换 Composer 加载并执行回归；只有新链路通过且回滚点明确后，才从宿主移除内置副本。不会直接移动后再排错。

### 6. SandPackage 前端只抽取插件管理载荷

完整核心 `sandadmin-artd` 归 `sand-core`；当前 `sandadmin-artd/src/views/plugin/sandpackage` 及其专属 API/路由依赖归 `sand-package`。共享前端基础设施归 `sand-core`，避免两个包各自携带完整前端副本。

## Risks / Trade-offs

- [Composer 包体积因包含前端源码增大] → 排除 `node_modules`、缓存、构建输出和测试产物，并检查归档清单。
- [Webman 对 vendor 包配置发现不足] → 先复用 Webman Composer 插件安装约定，增加干净宿主配置、路由、命令和进程发现测试。
- [大量代码依赖 `plugin/sandadmin` 运行路径] → 首轮保持命名空间和目标布局，建立路径扫描及运行回归，不同时做品牌重命名。
- [前端发布覆盖本地开发修改] → 使用基线清单和冲突拒绝，默认不自动覆盖。
- [SandPackage 扩展与上游兼容层遗漏] → 对当前 `server/plugin/sandpackage`、兼容目录、测试和前端载荷建立完整映射，逐项比对。
- [跨工作区写入或仓库状态冲突] → 写入前检查 `/Users/code/project/sand_plugins` 的项目规则与工作树，保留现有用户修改，不自动提交或推送。

## Migration Plan

1. 固定 SandAdmin 当前修订、文件清单、Composer 锁和直接回归基线。
2. 在 Sand 插件权威工作区创建 `sand-core`、`sand-package` 目录和 Composer 元数据。
3. 复制核心后端、完整核心前端、安装器后端、安装器前端及兼容代码；逐字节核对来源。
4. 实现安全的前端源码发布入口与版本/清单记录。
5. 使用 Composer path repository 让 SandAdmin 宿主消费两个本地包，更新锁文件。
6. 在保留原副本的情况下验证 autoload、配置、路由、命令、测试和前端类型检查。
7. 建立干净 Webman 消费者，验证只通过 Composer 获取后端和前端源码发布能力。
8. 完成 SandAdmin 登录、SandPackage 页面和一个中性 ZIP 插件的安装/升级/卸载验收。
9. 验证通过后移除宿主内置副本和临时 path repository；发布稳定包版本并锁定。

回滚时恢复原宿主 Composer 映射和内置目录，使用迁移前锁文件；不回滚或修改业务数据库。
