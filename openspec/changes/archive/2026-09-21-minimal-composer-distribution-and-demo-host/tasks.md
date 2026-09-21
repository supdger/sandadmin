## 1. Composer 后端包

- [x] 1.1 新增根 Composer library 元数据与 SandAdmin 安装器，并以隔离 Webman 夹具验证核心插件复制、重复安装和消费者状态保护。
- [x] 1.2 将 PostgreSQL 默认、ThinkORM 启动、禁用默认路由和 Request `more()` 契约移入安装载荷，并运行对应后端契约测试。
- [x] 1.3 验证 `plugins/catalog.json` 原公开路径和目录读取测试保持可用。

## 2. 源码仓库最小化

- [x] 2.1 将根 `tests/` 合并到 `server/tests/`，删除已被当前测试替代的退休夹具，并运行迁移后的测试。
- [x] 2.2 将根打包脚本与 clean-host 检查归入 `server/scripts/`，更新代码、文档和测试引用并验证脚本入口。
- [x] 2.3 在新消费者安装通过后移除 `server/` 中 Webman 运行骨架、项目锁文件和本机环境模板，检查公开根只剩必要发布入口。
- [x] 2.4 精简根治理文件和过时公共文档，将仍有效的许可证、来源、安装及安全边界收敛到 README、NOTICE 和 docs，并执行链接检查。

## 3. 统一 sand_demo

- [x] 3.1 在 `/Users/code/project/sand_demo/server` 创建标准 Webman 消费者并安装当前 SandAdmin Composer 包，验证命令清单和零插件加载。
- [x] 3.2 将锁定 revision 的 `sandadmin-artd` 消费副本放入 `/Users/code/project/sand_demo/sandadmin-artd`，安装依赖并验证生产构建。
- [x] 3.3 定向迁移 sand_plugins 的活动演示路径到 `sand_demo`，保护其现有未提交业务改动，并运行路径契约测试。
- [x] 3.4 在不创建或迁移数据库的前提下记录统一启动入口、所需环境和旧演示回滚边界；真实登录与插件生命周期若缺少现成授权环境则明确保留未验证。

## 4. 综合验收

- [x] 4.1 运行 Composer 校验、PHP 语法、后端测试、clean-host 检查、OpenSpec 严格校验和前端类型/构建检查。
- [x] 4.2 固定候选后由独立 Astra 审查安装边界、目录兼容和演示宿主所有权，修复阻断问题并记录未验证层。
- [x] 4.3 审查最终差异，确认没有 vendor、node_modules、环境密钥、数据库或业务插件运行副本进入 SandAdmin 源码仓库。
