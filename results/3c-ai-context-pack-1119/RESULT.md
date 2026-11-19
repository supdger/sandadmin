# RESULT — 1.x AI 上下文打包脚本（独立审计新增表 #23，无库）

## 交付物

- 仓库：supdger/sandadmin
- 分支：`feat/ai-context-pack` → 目标 `main`，PR 未合并
- PR：https://github.com/supdger/sandadmin/pull/108
- 基线 SHA（origin/main 开工点）：`977427acc285da074a443e8206309289a9bd94fb`
- 最终 SHA：`32249869d4f6ad061be2eea6fb7f9b89394d57cd`

## 改动范围（只两件）

| 文件 | 改动 |
| --- | --- |
| `tools/ai-context-pack.php` | 新增。打包脚本 + `--self-check` 自检 |
| `.gitattributes` | 追加一行 `/tools/ai-context-pack.php export-ignore`（沿用 tools/ 逐文件列口径） |

未动：`tools/pre-merge.sh`、`server/src`、`contract/`、`.agents/skills`、CI、`.gitignore`（输出走已忽略的 `/dist/`）。

## 脚本用法

```
php tools/ai-context-pack.php                  # 生成 dist/ai-context-pack.md（/dist/ 已被 .gitignore 覆盖）
php tools/ai-context-pack.php --out <path>     # 指定输出文件，可放仓库外
php tools/ai-context-pack.php --max-bytes <n>  # 体积上限，默认 65536 字节，超限退出 1
php tools/ai-context-pack.php --self-check     # 不落地，跑一致性自检
php tools/ai-context-pack.php --help           # 用法
```

退出码：0 成功/自检通过；1 自检发现不一致或写文件失败；2 参数错误。

## 输出内容与大小

输出单文件 Markdown，分节：统计 → 接口清单（方法/路径/operationId/说明/权限/业务错误码）→ 路由表（`route.generated.php` 全量，handler 已按 use 表还原 FQCN）→ 权限码（权限树 / 树外码 / 管理员高危码）→ 错误码总表（码/HTTP/说明/通用标记）→ MCP 工具表（名/档/权限码/绑定路由/dev_only）。

实测：28,712 字节 / 326 行（接口 100、路由 100、错误码 61、MCP 工具 4），约为上限 64 KiB 的 44%。文件头记录与生成产物同源的 `SourceHash` 值（`sha256:a8cf8b7c…`），AI 可对照产物头判断是否过期。

输出样例（节选）：

```
# SandAdmin 1.x AI 上下文包

> 由 tools/ai-context-pack.php 生成。源哈希（与生成产物头部同源）：
> sha256:a8cf8b7cebf97fe5e9c8c4776d63005e670511b7bf4f09d8eef32e619405f18f。…

## 接口清单
| POST | `/api/admin/v1/files` | filesStore | 上传文件 | authenticated | FILE_TOO_LARGE FILE_TYPE_NOT_ALLOWED IDEMPOTENCY_KEY_REUSED |
| GET  | `/api/admin/v1/files` | filesIndex | 文件列表 | `system:file:index` | — |

## 路由表（server/config/route.generated.php）
POST /api/admin/v1/files → app\controller\FileController::store

## MCP 工具表
| dev_run_pre_merge | write | `mcp:dev:gate` | — | 是 |
```

## --self-check 核对项

- 契约 paths ↔ `route.generated.php` 路由集合逐一对应（缺/多都报）
- 接口 `x-permission` 与 `x-permissions` 码都在 `PermissionRegistry` 常量里
- `x-error-catalog` 键集 == `ErrorCodeRegistry::HTTP_STATUS` 键集
- `x-mcp-tools` + 接口 `x-mcp` 工具名集 == `McpToolRegistry::specs()` 名集
- 六个产物文件（路由表、plugin 路由表、三个 Registry、`web/src/api/v1/contract.generated.ts`）头记录的源哈希 == `SourceHash::compute` 重算值
- 摘要字节数 ≤ `--max-bytes`

## 验证命令与关键输出

| 命令 | 退出码 | 关键输出 |
| --- | --- | --- |
| `php tools/ai-context-pack.php --self-check` | 0 | `ai-context-pack 自检通过：接口 100、路由 100、错误码 61、MCP 工具 4、摘要 28712 字节（上限 65536）` |
| `php tools/ai-context-pack.php` | 0 | `已生成 …/dist/ai-context-pack.md（28712 字节，上限 65536）` |
| `php tools/ai-context-pack.php --max-bytes 1000 --self-check` | 1 | `FAIL 摘要包 28712 字节超过上限 1000` |
| `php tools/ai-context-pack.php --bogus` | 2 | `未知参数：--bogus` |
| `tools/pre-merge.sh --fast` | 0 | 7 项全过：语法、契约一致、危险函数、dev-only、typephp diff、PHPUnit 408 tests、PHPStan no errors |
| `naming_check.py code tools/ai-context-pack.php` | 0 | 无 FAIL/WARN（曾报 `$real` WARN，已改 `$resolved` 复测通过） |
| `naming_check.py notes tools/ai-context-pack.php` | 0 | 注释占比 0.9% |
| `naming_check.py terms tools/naming-terms.tsv <两文件>` | 0 | 无命中 |

另验证：生成文件 `git check-ignore dist/ai-context-pack.md` 命中（输出不进仓库）；脚本写到仓库内未忽略路径时会打警告。

## 待 PR #98 合并后补的 skill 行

PR #98（`docs/1x-skills`）的 `.agents/skills/sandadmin1x-ai-loop/SKILL.md` 当前未提本脚本。合并后在 SKILL.md「接到任务先做的事」第 2 步（让 AI 先读规矩）下补一行：

```
- 需要接口/路由/权限/错误码/MCP 工具清单时，先跑 `php tools/ai-context-pack.php`，
  给 AI 喂生成的 `dist/ai-context-pack.md`，不直接塞 `contract/openapi.yaml` 全文。
```

（位置也可放 `references/mcp.md` 开头「查」类能力段；以 #98 合并后的实际结构为准。）

## 约束遵守确认

- 无建库、无 DDL、无迁移文件、未启动任何数据库或 Redis；PHPUnit 只跑了 Unit 套件（门禁自带）。
- 未改 `.agents/skills`、未合并任何 PR、未打 tag、未改 GitHub 仓库设置。
- 未读未打印 `.env`/`webhook.env`/`private/`/密钥；未 force push、未 `reset --hard`、未 `--no-verify`、`git add` 只加具体两个文件。
- 门禁白名单/基线未动；`.gitattributes` 只追加一行。
- 未定事项未触碰（本任务不涉国际化、workflow 归属、MySQL 版本、双因素、CLA/DCO）。

## 遗留说明

- 仓库现状接口上还没有 `x-mcp` 字段（业务工具映射在 PR #100），脚本已支持：接口带 `x-mcp` 时自动并入 MCP 工具表并纳入自检。
- `--self-check` 未接进 `pre-merge.sh`（任务禁止改该文件）；如需进门禁另行授权。
- 输出默认进 `dist/`；若后续想让 AI 工具默认读到固定路径，可在 skill 行里写死。
