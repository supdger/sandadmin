# 迁移映射

| 来源 | 目标 | 所有权 |
| --- | --- | --- |
| `server/plugin/sandadmin/` | `sand-core/server/plugin/sandadmin/` | `sand-core` |
| `sandadmin-artd/`（排除 `src/views/plugin/sandpackage/`） | `sand-core/sandadmin-artd/` | `sand-core` |
| `server/plugin/sandpackage/` | `sand-package/server/plugin/sandpackage/` | `sand-package` |
| `server/compat/Saithink/Saipackage/` | `sand-package/server/compat/Saithink/Saipackage/` | `sand-package` |
| `server/tests/SandPackage/` | `sand-package/server/tests/SandPackage/` | `sand-package` |
| `sandadmin-artd/src/views/plugin/sandpackage/` | `sand-package/sandadmin-artd/src/views/plugin/sandpackage/` | `sand-package` |

## 基线计数

- Sand Core 后端：152 个文件
- Sand Core 前端：406 个文件
- Sand Package 后端、兼容层及测试：62 个文件
- Sand Package 前端：16 个文件
- 迁移相关路径引用：835 条，切换时逐类消除或保留为包内合法引用

对应 SHA-256 清单位于同目录的四个 `*.sha256` 文件。`baseline.txt` 记录两个工作区的修订与工作树；`sand_plugins` 的现有修改全部属于用户资产，本变更只允许新增目前不存在的 `sand-core/`、`sand-package/` 及明确更新架构入口，禁止覆盖其他脏文件。
