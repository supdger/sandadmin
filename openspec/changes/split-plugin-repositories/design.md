## Context

见 proposal.md。当前 `plugins/catalog.json` 位于 SandAdmin，但下载实现把 Release 仓库固定为目录仓库 `supdger/sandadmin`。三个插件的已提交权威源码位于 `sand-plugins`，本地工作区同时存在大量未提交内容，不能作为公开迁移输入。

## Goals / Non-Goals

**Goals:**

- 保持单一插件目录和现有安装界面。
- 让每个目录条目绑定一个独立、可验证的公开 GitHub 仓库。
- 从干净已提交 revision 拆分插件历史，避免公开本地未提交内容。
- 迁移顺序允许失败关闭和回滚。

**Non-Goals:**

- 不建设独立市场服务、账号或订单系统。
- 不自动执行插件数据库迁移，不改变已安装插件状态。
- 不把私有 `sand_ai` 应用仓库直接公开；SandAI 插件只从 `sand-plugins` 中已提交的插件目录拆分。
- 不在本次宣称三个插件均完成全新安装、升级和完整业务链验收。

## Decisions

1. `plugins/catalog.json` 继续由 SandAdmin 维护，插件条目新增 `repository`，格式为 `owner/repository`。集中目录便于宿主统一展示兼容范围，同时避免独立市场平台。
2. Release URL 使用插件条目的 `repository`。仓库字段与其他目录字段一起由服务端验证和重新读取，前端请求仍只发送 app/version/sha256。
3. 解析器兼容缺少 `repository` 的旧目录条目，将其绑定到目录仓库，仅用于平滑升级；SandAdmin 当前目录和新文档全部使用显式字段。
4. 独立仓库名称使用 `sand-iam`、`sand-workflow`、`sand-ai`。插件标识保持 `sand-iam`、`sandworkflow`、`sand-ai`，不因仓库名称改变运行目录或数据库前缀。
5. 从 `sand-plugins` 远端已提交 revision 按目录拆分历史。每个仓库根直接对应原插件目录，不包含 monorepo 的演示宿主、缓存、构建产物或其他插件。
6. 复用已经验证的不可变 ZIP；上传到独立仓库后重新下载并核对 SHA-256。目录切换并验证后，才删除 SandAdmin 中对应的重复 Release。
7. `sand-plugins` 保留迁移记录，但不再作为已迁出插件的权威发布源；未提交工作另行整理，不混入本次公开基线。

## Risks / Trade-offs

- [独立仓库数量增加，主页更分散] → 统一命名、README 交叉链接和 SandAdmin 目录提供入口。
- [旧宿主版本只支持同仓 Release] → 先发布支持独立仓库的新宿主，保留目录仓库回退兼容；消费者升级宿主后再读取新目录。
- [拆分 revision 落后于本地未提交开发] → 明确迁移基线，仅发布远端已提交内容，后续把经审查的工作按插件分别迁入。
- [删除旧 Release 影响固定旧链接] → 只删除目录已不引用且新仓库资产摘要一致的重复发布，保留 Git 历史和迁移记录。

## Migration Plan

1. 扩展目录解析、下载 URL、契约测试和文档，验证旧目录兼容与恶意仓库拒绝。
2. 从 `sand-plugins` 已提交基线拆出三个插件仓库，补齐仓库级说明和来源记录。
3. 在独立仓库创建对应 Release，下载回读并核对摘要。
4. 更新 SandAdmin 目录为显式独立仓库，发布宿主 main，验证目录、文档和候选下载入口。
5. 删除 SandAdmin 中已被替代的插件 Release 与标签，更新 `sand-plugins` 权威来源说明。
6. 若任一步验证失败，保持当前目录和旧 Release，不执行后续删除；已经创建的新仓库可保留为未启用候选。
