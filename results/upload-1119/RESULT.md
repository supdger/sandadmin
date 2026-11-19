# RESULT — 1.x 分段上传：逐项对照 + 方案稿（upload-1119）

## 交付

- PR：<https://github.com/supdger/sandadmin/pull/99>（OPEN，**未合并**）
- 分支：`docs/1x-chunked-upload`（从 origin/main `977427a` 开出）
- 最终 SHA：`4d4f5976132a338b4c1fe5f379ca3ea68bb89d8d`
- 改动：只新增 `docs/1x-分段上传方案.md` 一个文件（425 行）。`server/src`、`contract/openapi.yaml`、门禁文件、CI、`.gitignore`、`naming-allow.txt` 都没改；没建库、没执行 DDL、没跑迁移、没启动数据库。
- **要注意**：仓内 `.gitignore` 第 4 行是 `/docs/`，2026-09 的提交 986f670「remove repository process artifacts」、9a3da80「Move maintenance files out of source checkout」有意把 docs 移出了仓库。任务要求放 docs，所以这一个文件用 `git add -f` 加入（只加这一个文件，没用 `-A`）。PR 说明里已写明，留不留仓请评审决定。

## 输入实际取自哪里（Mac 上没有 `/workspace`）

| 任务写的输入 | 实际读的 |
| --- | --- |
| `/workspace/1x-docs/review-1117-r3/顶层架构设计-v4.md`、`鉴权P0-v4.md` | `~/agent-work/grokbot/review-1117-r3/` 下同名文件（T-7、J-12、E 节、f1、B-22；鉴权旧面路由表 `chunkUpload` 行、ORG-03） |
| `/workspace/intro/老板已定事项总表.md` | `~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md` 第 20 条 |
| 老版 saiadmin 上传代码 | composer 缓存里的 `saithink/saiadmin` 6.0.11（`ChunkUploadService.php`、`SystemAttachmentLogic`、`SystemController`）；用 `gh api` 只读核过，上游 `5981781` 同一文件逐字相同；前端看了本仓 main 的 `sandadmin-artd/src/components/sand/sa-chunk-upload/index.vue` 和 `api/auth.ts:168` |
| 1.x main 代码 | 在任务目录新克隆了 `supdger/sandadmin` main `977427a`：`server/src/{Application,Domain,Infra}/File`、`app/controller/FileController.php`、`config/file.php`、`config/server.php`、`contract/openapi.yaml`、`contract/api-conventions.md` |
| PR #98 skill | `origin/docs/1x-skills`（`b7bc4c5`）：`sandadmin1x-backend` 的 SKILL.md、`references/database.md`、`backend-structure.md`，以及 COVERAGE.md |
| 编号归类 | `~/agent-work/grokbot/audit-idlist-1117/编号两列表-修订.md`（草稿）：`sand_system_file`、`sand_system_upload_task`、`sand_system_upload_chunk` 都归雪花类 |

## 对照表（摘要；完整表见方案稿第二节）

| 能力项 | saiadmin 6.x | tus 1.0 | S3 Multipart | Laravel pion | 1.x 判定 |
| --- | --- | --- | --- | --- | --- |
| 分片 | 前端定 5MB | 字节偏移 PATCH | 1–10,000 段，每段 5MiB–5GiB | 跟随前端库 | 必须：服务端下发分片大小（默认 5MB），每片长度精确校验 |
| 分片寻址 | 客户端 hash 拼路径（可穿越） | 服务端发 URL | UploadId | 会话 id 拼名 | 必须：服务端雪花任务号，客户端值不进路径 |
| 断点续传 | 找第一个缺片 | HEAD 拿 offset | ListParts | 依前端库 | 必须：查询已收片 + 本人未完成任务列表 |
| 分片校验 | 无 | Checksum 扩展（sha1，460） | Content-MD5 / checksum | 无 | 必须：每片 sha256 |
| 整文件校验 | 无 | 无 | 复合校验 | 无 | 必须：服务端合并时重算 sha256 |
| 秒传 | 只比 hash，跨人命中 | 无 | 无 | 无 | 必须（收窄）：只在同上传人范围内命中 |
| 并发 | 顺序 | Concatenation 扩展 | 可并行 | 部分支持 | 必须：并行 + 同序号覆盖 + 条件状态更新 |
| 合并 | 末片触发、不查齐全 | 不用合并 | Complete | 末片触发 | 必须：显式 complete，转后台任务 |
| 取消 | 只停前端 | Termination | Abort | 无 | 必须 |
| 过期清理 | 无 | Expiration（404/410） | 桶生命周期规则 | 定时清 3 小时前分片 | 必须：默认 24 小时 + 定时清理 + 对账 + 桶规则兜底 |
| 大小 / 类型限制 | 只看客户端字段 / 扩展名 | Tus-Max-Size | 48.8TiB | 无 | 必须：1024MB 上限；扩展名早拒 + 合并后嗅探 + 双白名单 |
| 存储后端 | 写死本地 | 实现自定 | S3 | local 磁盘 | 必须：本机驱动（限 `instances=1`）；可选：S3 兼容驱动；不做：浏览器直传 |
| 权限 | `core:system:chunkUpload` | 不管 | IAM | 自定 | 必须：v1 会话 + 权限码，任务绑上传人 |
| 限流 / 配额 | 无 | 无 | 账号配额 | 无 | 必须：并发任务数、在途字节、传片频率、磁盘余量；可选：用户总存储配额 |
| tus 兼容端点 | — | — | — | — | 不做（与 v1 信封和契约生成链不兼容，只借语义） |

另外核对过 webman `tinywan/storage`（README 说本地支持分片 + md5，云端说法前后不一）、若依 RuoYi-Vue-Plus（S3TransferManager 是服务端到 OSS 的传输，浏览器分片在官方是否内置未核实）、ThinkPHP `think-filesystem` 3.0（Flysystem 3 封装，没有分片）、Flysystem 3（没有可续传的分段接口）。

读上游源码查出旧版 10 个问题（S1–S10），其中有安全影响的：S1 客户端 `hash` 原样拼路径，可路径穿越；S2 合并文件放在 web 根内；S3 秒传不看上传人，知道 MD5 就能拿到别人的附件；S4/S5 分片不绑任务、不校验摘要，可被投毒；S6 类型只比扩展名、MIME 用客户端传的值。

## 方案要点

1. 接口（拟增，只写在文档里）：`POST /uploads`（开始；命中秒传直接返回 file）、`GET /uploads`（本人未完成任务）、`GET|DELETE /uploads/{upload_id}`、`PUT /uploads/{upload_id}/chunks/{seq_num}`（原始字节 + `X-Chunk-Sha256`）、`POST /uploads/{upload_id}/complete`（受理后转后台合并）。新增 8 个错误码，复用 `FILE_TOO_LARGE`/`FILE_TYPE_NOT_ALLOWED`/`IDEMPOTENCY_KEY_REUSED`；不挂 MCP 工具。
2. 分层：`UploadController`（薄壳）→ `Application/File` 的 `ChunkUploadService`/`UploadHttp`/`UploadConfig` → `Domain/File` 的 `UploadTask`/`UploadState`/`UploadTaskRepository`/`FileStorage` → `Infra/File` 的 `PdoUploadTaskRepository`、`LocalFileStorage` 加分段动作，S3 驱动可选；`Task/Handler` 加合并任务和过期清理。合并后的嗅探、双白名单、写 `sand_system_file` 复用现有 `UploadService`，不复制。
3. 存储：新立 `FileStorage` 接口，带开始、传片、列已传片、完成、取消五个动作。本机驱动的分片放存储根下 `parts/<upload_id>/<seq_num>.part`，先写临时文件再原子改名；S3 驱动映射到原生分段上传。
4. 表（**待编号表审批，未建表**）：`sand_system_upload_task`、`sand_system_upload_chunk`，雪花 `bigint` 主键，PG/MySQL 两套写法，`naming_check.py sql` 已通过；建议不分区，任务结束就删分片行；秒传要给 `sand_system_file` 加索引 `(uploader_id, sha256, size_bytes)`。
5. 安全：任务按上传人隔离（别人的任务回 404 防枚举）；类型校验三道；分片和整文件都做 sha256；客户端值不进路径，存储键做格式校验，保留 `DISALLOW_LINKS`；秒传只在本人范围内命中；配额和限流计数放共享存储（现有进程内 `WindowRateLimiter` 不能复用）；关键动作写审计。
6. 清理：每小时跑一次。过期任务转 `expired` 并删分片或调 Abort；合并超时的转 `failed`；终态任务行保留 7 天；对账删掉无主的残片目录；结果写普通日志。对象存储另配桶生命周期规则兜底。
7. 验收：8 条可客观判定的标准（契约生成一致、1024MB 端到端、秒传跨人不命中、安全用例、清理、双库加迁移往返、门禁、合并时内存峰值 < 64MB）。
8. 工作量：必须项 5 个 PR，4.5–7.5 人天；加上可选的对象存储驱动是 6 个 PR，5.5–9.5 人天。比 v4 f1 的 3–5 人天多，是因为单列了异步合并、共享限流、对账清理、秒传索引和前端 Worker 哈希；这几项都去掉可以压回 3.5–5.5 人天。

## 未拍板点（单列；每条都有建议，详见方案稿第七节）

1. 上传权限码：新增 `system:file:upload` 并进权限树（`/files` 同步改），还是只要求登录，还是新增码但不进权限树。
2. 现在就立 `FileStorage` 接口（v4 J-12），还是按 `LocalFileStorage` 注释「不再自定义存储接口」先不立。
3. 对象存储驱动是否 1.0 交付；对象存储下整文件 sha256 靠回读对象来校验（有内网流量成本）。
4. 任务状态用 6 个值（多 `merging`、`expired`），还是收进命名规范规定的 5 个值。
5. `sand_system_upload_chunk` 是否按 v4 J-9 做按月分区。
6. `FILE_TOO_LARGE` 文案写死「100MB」，要去掉数值，还是另起 `UPLOAD_TOO_LARGE`。
7. 合并同步做还是异步做（建议异步）。
8. 分片大小由服务端下发，还是按 v4 T-7 原文由客户端传。
9. 配置键名带单位（`upload_chunk_bytes` 等），还是沿用 v4 的建议名。
10. 前端 sha256 怎么算：引入增量哈希库（如 `hash-wasm`，要先核许可证），还是不做秒传。
11. 默认建议值：并发 3、在途 2GB、每分钟 120 片、任务行保留 7 天、合并超时 2 小时。

## 缺的输入

1. 内核迁移和 `sand_system_file` 建表脚本的权威落点：本仓 AGENTS.md 说「本仓无 migrations，核心 SQL 在 sand-core」，skill 说用 `bin/sand migrate`，本仓没有建表脚本。
2. 编号表的正式审批结果（现在是草稿）。
3. 共享存储限流组件由谁做、何时做。
4. 首期部署是否多实例、用哪家对象存储；阿里云 OSS 的分段下限。
5. v4 B-22 说的「saiadmin 自 5.1.3 起有切片上传」：本次只核了 6.0.11 / `5981781`，没有追溯版本历史。

## 验证

- `naming_check.py sql`（取出方案稿里的 SQL 块，存到 /tmp 临时文件）：exit 0。第一次跑时 MySQL `KEY ix_...` 行被误判成列名，已改成单独的 `CREATE INDEX` 语句。
- `naming_check.py terms tools/naming-terms.tsv`：只有 3 处 FAIL，都是原样引用上游的标识符 `SystemAttachmentLogic`、`sa_system_attachment`（词表要求统一叫 file），不能改名。用任务目录里的豁免文件 `naming-allow-task.txt` 复跑，exit 0。
- `scan_change_quality.py --repository-review prepare/record/check`：结论绑定暂存树 `3bfd3a6`，check 通过；结论文件是 `review-1119.json`。
- pre-commit：克隆下来的仓库原本没有 hook，在本地 `.git/hooks/pre-commit` 接了 review check（只在本地，不提交）；提交时 hook 执行并通过。
- `tools/pre-merge.sh` 没跑：本次只加了 Markdown，它的检查项（PHP 语法、契约产物、PHPUnit、PHPStan）都不涉及，而且要先 `composer install`。
- PR #99 核对过：OPEN，文件列表只有这一个文档，head = `4d4f597`。
