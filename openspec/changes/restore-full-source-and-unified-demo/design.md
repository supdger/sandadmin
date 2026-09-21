## Context

参见 `proposal.md` 的 Why。当前 `main` 在 `6.1.5` 中把完整 Webman 项目收敛为根级 Composer library；完整宿主骨架仍可从提交 `a005596` 与当前 `/Users/code/project/sand_demo` 找到。当前核心插件目录在 `sandadmin` 与 `sand_demo` 字节一致，但 `sand_demo` 还包含依赖、构建产物和运行状态。`sand_plugins` 工作区存在大量未提交插件工作，其旧演示目录多数未被 Git 跟踪。

## Goals / Non-Goals

**Goals:**

- 在不回退当前核心修复的前提下恢复完整 Webman 项目结构。
- 建立 `sandadmin -> sand_demo` 和独立插件仓库 `-> sand_demo` 的单向同步。
- 以可恢复方式退役 `sand_plugins` 旧演示宿主。
- 保留历史 Composer 发行的可追溯性并清理无用远端功能分支。

**Non-Goals:**

- 不删除 Packagist 包、`6.1.5` 标签或历史 GitHub Release。
- 不把 `sand_plugins` 的未提交插件实现合并进 SandAdmin。
- 不在职责迁移过程中创建数据库、执行迁移、卸载插件或启动/重启服务。
- 不重写 `main` 历史。

## Decisions

### 1. 以已提交宿主骨架为基线，保留当前核心实现

从提交 `a005596` 恢复 Webman 项目级文件，再用当前 `main` 的 `server/plugin/sandadmin`、`server/plugin/sandpackage`、兼容层、测试和通用脚本覆盖对应核心区域。这样既避免从含运行状态的 `sand_demo` 反向复制源码，也不会丢失 `6.1.5` 之后的核心修复。

备选方案是整体复制 `sand_demo/server`。不采用，因为其中包含消费者锁文件、生成配置、依赖和运行状态，容易把演示宿主误当成源码权威。

### 2. Composer 元数据回到 `server/` project

删除根级 Composer library 主入口，建立 `server/composer.json` 和锁文件。它直接加载仓库内的 SandAdmin、SandPackage 及必要兼容命名空间，不依赖 `supdger/sandadmin` 自身；package-only 的安装复制入口不再作为主线。

历史 tag/Release 保留，避免破坏已经锁定 `6.1.5` 的消费者。后续完整源码版本不再作为新的 Composer library 发布。

### 3. `sand_demo` 是消费副本，不是源码镜像仓库

同步工具使用明确清单更新 Webman 骨架、核心插件和前端源码，同时排除 `.env`、`vendor/`、`node_modules/`、`runtime/`、`dist/`、本地候选、备份和业务插件运行目录。同步后记录 SandAdmin revision，并由插件安装/同步流程单独管理业务插件。

### 4. `sand_plugins` 采用有界退役

旧 `sandadmin-demo`、`sandadmin-demo-rebuilt` 等未跟踪宿主目录先移动到工作区外的时间戳备份；只修改明确的 README、宿主锁和同步脚本。其余脏工作区内容保持不动。已迁移到独立仓库的插件权威性另行处理，本变更不借机清理插件源码。

### 5. 远端分支只删除，不重复合并

`codex/plugin-catalog-preview` 已是 `main` 祖先；`codex/sandadmin-rename` 的唯一补丁经 `git cherry` 判定已在 `main` 等价吸收。两者都直接删除，最终只保留 `main` 和本次变更在合并前所需的 feature branch。

## Risks / Trade-offs

- [恢复旧骨架可能带回过期配置] → 逐文件恢复宿主骨架，并与当前 `sand_demo` 和当前核心配置做差异审查。
- [根级 Packagist 元数据撤销后容易让用户误解历史包状态] → 保留历史发布并在 README/CHANGELOG 明确“历史兼容、停止演进”。
- [`sand_demo` 同步覆盖本机运行状态] → 使用排除清单、同步前 dry-run 和可恢复备份，禁止全目录覆盖。
- [`sand_plugins` 脏工作区误删] → 不执行 reset/clean，只移动已确认旧宿主目录并单独审查目标文件。
- [完整源码仓库再次积累依赖和产物] → 根和子项目 `.gitignore` 明确排除依赖、运行、构建、IDE 与浏览器测试产物。

## Migration Plan

1. 在 feature branch 恢复并校验完整 SandAdmin 结构。
2. 完成 Composer 安装、PHP/契约测试、零插件检查和前端锁定构建。
3. 对 `sand_demo` 做同步 dry-run，备份将被替换的源码文件，再执行单向同步并记录 revision。
4. 在不操作数据库和服务的前提下验证 `sand_demo` 依赖、命令加载与前端构建。
5. 可恢复地退役 `sand_plugins` 旧演示宿主并更新同步入口。
6. 删除已吸收的远端旧功能分支。
7. 提交、推送、PR 审查并合并；如失败，恢复源码备份并保留原演示宿主，不改数据库。
