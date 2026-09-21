## 1. 恢复完整 SandAdmin 源码结构

- [x] 1.1 从提交 `a005596` 恢复 Webman 项目骨架文件，同时保留当前核心插件、兼容层、测试和通用脚本；通过目录清单和逐路径 diff 确认没有回退当前核心实现
- [x] 1.2 将 Composer project 元数据与锁文件恢复到 `server/`，移除根级 Composer library 主入口及仅用于包复制安装的文件；通过 `composer validate --strict` 和依赖图确认不存在 `supdger/sandadmin` 自依赖
- [x] 1.3 补齐并校正前后端环境模板、默认端口和忽略规则；通过搜索确认前端默认代理与后端默认监听均为 `8787`，且依赖、运行和构建产物不会被 Git 跟踪
- [x] 1.4 更新 README、CHANGELOG、架构和治理文档，明确完整源码安装为主线、`6.1.5` Composer 包仅为历史兼容发行；通过文档链接检查和关键词扫描确认不存在相互冲突的安装指令

## 2. 验证 SandAdmin 完整源码

- [x] 2.1 在 `server/` 安装锁定依赖并运行 Composer/后端契约测试；通过 `php webman list` 确认 SandAdmin 与 SandPackage 命令可发现
- [x] 2.2 执行零业务插件检查和 Git 跟踪清单检查，确认源码不包含 SandAI、SandIAM、SandWorkflow 运行副本或 `vendor/runtime` 等产物
- [x] 2.3 在 `sandadmin-artd/` 使用锁文件安装依赖并执行类型检查或生产构建，确认完整前端可由当前仓库独立构建
- [x] 2.4 在临时干净克隆中重复后端安装和前端构建，确认成功不依赖当前工作区的未跟踪文件

## 3. 迁移统一演示宿主

- [x] 3.1 记录 `sand_demo` 当前源码、配置、运行目录和插件状态清单，并对拟同步文件执行 dry-run；确认 `.env`、数据库相关配置、`runtime`、候选、备份及业务插件目录均在保护范围
- [x] 3.2 将已验证的 SandAdmin revision 单向同步到 `sand_demo`，写入宿主 revision/版本锁和同步说明；通过源码清单或校验和确认核心源码与权威 revision 一致
- [x] 3.3 在不创建数据库、不执行迁移、不启停服务的前提下验证 `sand_demo` 后端依赖、命令加载和前端构建，并把结果标记为源码/构建层证据
- [x] 3.4 更新插件演示说明和同步入口，使独立插件统一指向 `sand_demo`；通过脚本 dry-run 确认同步方向只从权威仓库流向演示宿主

## 4. 安全退役 sand_plugins 旧演示宿主

- [x] 4.1 记录 `sand_plugins` 迁移前 Git 状态和旧宿主路径，确认大量现有未提交插件工作不在本变更目标内
- [x] 4.2 将确认的未跟踪旧演示宿主目录移动到工作区外的时间戳备份，并记录恢复路径；通过目录检查确认可恢复且未删除插件源码或验收材料
- [x] 4.3 仅修改旧宿主锁、README 和宿主同步脚本，使其指向 `sand_demo` 或明确退役；通过迁移前后 Git 状态对比确认没有触及无关脏文件

## 5. 清理远端分支

- [x] 5.1 重新 fetch 并证明 `codex/plugin-catalog-preview` 已合并、`codex/sandadmin-rename` 补丁已等价吸收；保存 `--merged`、`git cherry` 和唯一提交证据
- [x] 5.2 删除两个已吸收的远端分支并重新 fetch；通过远端分支列表确认除本次 feature branch 外只保留 `main`

## 6. 提交与交付

- [x] 6.1 运行 OpenSpec 严格校验、Git diff 检查及完整回归，记录已验证层级和数据库/登录/真实插件生命周期未执行项
- [x] 6.2 审查三工作区差异，确保 SandAdmin 提交不夹带依赖或运行数据、`sand_demo` 只含有界同步、`sand_plugins` 不夹带无关脏改动
- [x] 6.3 提交并推送 feature branch，创建 Pull Request，核对远端差异后合并到 `main`
- [x] 6.4 同步本地 `main` 并核对远端根目录、分支和历史发行仍可访问；输出已修改、已验证、已提交、已部署和未验证事项
