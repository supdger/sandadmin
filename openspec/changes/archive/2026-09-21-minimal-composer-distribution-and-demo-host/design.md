## Context

当前 `server/` 是可直接启动的 Webman 消费者工程，同时保存 SandAdmin 与 SandPackage 插件源码；`sandadmin-artd/` 是完整 Vue 前端。SaiAdmin 6.x 上游则将后端发布为 Composer library，由 `Install` 类复制插件载荷到已有 Webman 消费者。`plugins/catalog.json` 已成为旧客户端硬编码的公开地址，不能随根目录整理直接移动。`sand_plugins` 工作树存在大量在途修改，旧演示宿主路径又被多处测试和文档引用；新建的 `sand_demo` 当前为空。

## Goals / Non-Goals

**Goals:**

- 让 SandAdmin 根仓库成为 Composer 可识别的后端包，同时保留独立前端源码。
- 将 Webman 运行骨架和统一演示职责迁到 `sand_demo`。
- 把有效测试和维护脚本归入 `server/`，减少根目录维护入口。
- 保持 PostgreSQL 默认、安全路由、Request 参数扩展和目录下载兼容。

**Non-Goals:**

- 本次不创建、删除或迁移数据库。
- 本次不自动安装、升级或卸载业务插件。
- 本次不把 `sand_demo` 变成 SandAdmin 或插件源码权威。
- 本次不移动 `plugins/catalog.json` 的公开 URL。

## Decisions

### 根 Composer 包，后端载荷继续位于 server

根新增 `composer.json`，包类型为 `library`，自动加载安装器；安装器从 `server/plugin/sandadmin` 与 `server/plugin/sandpackage` 复制到消费者。这样常规 VCS/Packagist 能发现包，同时保持用户认可的 `server/` 后端主目录。备选的根 `src/` 会制造与现有目录并存的第三个主要源码入口，因此不采用。

### Webman 骨架只属于 sand_demo

`start.php`、`webman`、`windows.*`、通用 `app/`、`support/`、宿主 `config/`、本机环境示例和项目锁文件迁到 `sand_demo/server`。删除前将 SandAdmin 必需的 PostgreSQL配置、禁止默认路由、ThinkORM 启动和 Request `more()` 扩展纳入安装载荷或安装器，并用新消费者验证。备选的“继续保留可运行 server”无法解决源码与演示宿主混用问题。

### 前端不进入 Composer 安装副作用

`sandadmin-artd/` 保留在源码仓库和 `sand_demo` 消费副本中，使用 pnpm 独立安装构建。Composer 不执行 Node 命令，避免后端安装引入额外环境要求。

### 兼容目录暂留根路径

`plugins/catalog.json` 是发布协议而不是过程文件。本次保留，待客户端支持新地址并完成过渡后再单独迁移。

### sand_plugins 只改活动路径，不清理其在途工作

仅对确认属于演示路径契约且不改变业务逻辑的活动文件做定向替换；不整理 `.artifacts`、历史执行记录或已有未提交业务实现。演示宿主创建和 SandAdmin 改造先完成，插件侧路径迁移随后验证。

## Risks / Trade-offs

- [Composer 安装器遗漏宿主级行为] → 为 PostgreSQL 默认、路由禁用、Request 扩展和插件加载分别建立安装后断言。
- [更新覆盖消费者文件] → 安装器只复制明确拥有的核心插件文件；环境、storage、业务插件和前端均不在复制范围。
- [旧客户端目录 404] → 本轮保留 `plugins/catalog.json` 原路径和内容。
- [sand_plugins 脏工作树冲突] → 只修改目标字符串匹配且先记录基线；冲突文件保留未迁移状态并明确列出。
- [公开根目录仍暂有 openspec] → 本变更实施和验证完成前保留任务来源，完成后把长期规则收敛到 docs 并删除已结束过程记录。

## Migration Plan

1. 从最新 `main` 建立 feature 分支并冻结当前目录、测试和演示路径引用。
2. 新增 Composer library 元数据与安装器，补齐安装/更新保护测试。
3. 将根测试、脚本、工具迁入 `server/` 并修正引用。
4. 在 `sand_demo` 创建标准 Webman 消费者并安装当前 SandAdmin 包，复制前端消费副本。
5. 通过新消费者验证后，从 SandAdmin 删除 Webman 骨架并更新用户文档。
6. 定向迁移 sand_plugins 的活动演示路径；保留无法安全合并的脏文件清单。
7. 完成静态、Composer、后端测试、前端构建和可用环境下的安装/登录验证。

回滚时恢复 SandAdmin 删除的骨架文件，并将 `sand_demo` 保留为未启用候选；不触碰数据库和旧演示数据。
