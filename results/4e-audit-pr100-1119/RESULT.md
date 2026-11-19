可合并

# PR #100 合并前独立审计结论（只读）

- 仓库：supdger/sandadmin，PR #100 `feat(mcp): 业务工具集映射 v1 接口、工具级权限门与文件审计`
- 分支 feat/mcp-business-tools，head = 44aaff41fccca667b3c22a4702f0da13d8a7d75d（与任务给定 44aaff4 一致），base = main，状态 OPEN、MERGEABLE
- 取证副本：/Users/supdger/agent-work/grokbot/sandadmin-pr-work（只做了 `git fetch` 更新远端引用，工作区未动）
- 本审计未合并、未推送、未评论、未改仓库文件；只写了本文件。

## 1. 范围（通过）
- 24 个文件（新增 15 / 修改 9，+1244/-12），2 个提交（927b7f3、44aaff4）。
- 全部在 `server/src/{Application,Domain,Http,Infra}/Mcp/**`（源码 16 个）和 `server/tests/**/Mcp*`（测试 8 个）下面。
- 没有改到功能模块以外的文件：文档、CI、依赖锁文件、`contract/openapi.yaml` 都是 0 个改动。

## 2. 门禁文件只减不增（通过）
- 门禁、CI、豁免/白名单、baseline、phpstan/phpunit 配置、pre-commit、tools/、scripts/security 都没有改（0 个文件），所以没有新增任何放宽。

## 3. CI（通过）
`gh pr checks 100`（只读）一共 6 条，全部 pass：composer audit ×2、frontend prod audit ×2、release self-check ×2。
注意：PHPUnit / phpstan 不在 GitHub checks 里，只能看 PR 正文里写的本地结果；本次审计没有重跑测试。

## 4. 安全（无阻断、无必须修改项）
- 鉴权：入口要依次过 dev 构建标记 + `SAND_MCP_DEV_TOKEN`（≥16 位）+ 回环地址 + 拒绝转发头 + Origin 白名单 + `hash_equals` 比对，任何一项不过都拒绝（fail-closed）。actor 只从 DB 账号和角色表解析，请求参数没法伪造身份。
- 权限：两层闸门（外层看凭据 scope，内层用 `actor->can` 按 v1 接口同一权限码判断）。凭据 scope 只能缩小权限，缩小后强制 `is_super=false`。27 个工具都有登记（读 11 / 写 12 / 高危 4），写操作的权限码和 v1 接口一一对应，契约测试会逐条核对。4 个高危工具不进派发表，McpKernel 也会拦截，双重保护。没登记的操作返回失败，不会放行。
- 注入：分发用的是固定的 `match` 分支，没有动态调用、eval、变量变量或动态 include。路径 ID 强制转成 int，status 走枚举白名单，SQL 都是参数绑定；body 交给 v1 服务用同一套 OrgInput 校验，没有新增暴露面。
- 秘密：没有硬编码密钥。token 只通过 getenv 读取，不回显。
- 日志泄露：审计写在 `security` 日志通道（留存 183 天，满足总表 #16 的 6 个月下限）。参数只记 sha256 摘要，不记原文；凭据只记标识 `dev-token`。异常只在服务端记录，回给客户端的是统一的 INTERNAL_ERROR，不带堆栈。
- 常驻进程：新增的类都是 readonly 或无状态，请求状态只通过 CallContext 参数传递，没有共享可变状态。
- 命名检查：`naming_check.py code --diff -` 和 `notes --diff -` 都是 EXIT=0（只读，通过 stdin 运行）。`terms` 模式没有词表，没跑。

## 5. 与《老板已定事项总表》（通过，无冲突）
- #22 / #21 / #10（MCP 双向能力做在框架层，不依赖 SandAI）：一致。实现全在宿主 `server/src`，没有改插件目录。
- #6（安全不低于 saiadmin 6.x）、#18（开发环境免登录、生产剔除）：一致，而且更严格（dev 环境也要 token）。
- #16（安全日志留存 ≥6 个月）：一致（security 通道 183 天）。
- #24（AI 改代码必须自动测试）：PR 正文自称 phpunit / phpstan / pre-merge 全部通过。
- #41（合并由技术总监决定）、#58（命名规范）：一致。
- #50（涉及登录权限的改动要过 Claude 关口评审）：本次审计就是这道关口（按老板的口径，本地 Opus 审计通过视为批准）。
- 总表里没有关于档位口径（M-02）、openapi 契约或禁止开放工具的规定，所以不构成冲突。

## 6. 建议项（不影响合并，建议后续跟进）
1. `OrgActorSource` 不检查 `sand_system_user.status`，停用的账号仍然能通过 MCP 执行。现在只有 dev 端点，和 DevPrincipal 的口径一致，不算缺口；但机器令牌落地前必须补上。
2. 当前凭据固定为 `['*']`，账号又是超管，所以双层闸门在现在的配置下其实不收紧任何权限：拿到 dev token 就等于拿到 admin 全量权限。建议在文档里写明这个前提。
3. `menus_reset`（整棵菜单树恢复默认）定成了写档；建议评估是否改成高危档，和重置密码、分配角色保持一致。
4. `dry_run` 实际只回显参数、不做校验，和 schema 里"只校验不执行"的文案对不上。这段逻辑在 McpKernel 里本来就有，不是本 PR 新加的，可以另开 PR 处理。
5. 待确认（不阻断）：update 类工具会把路径参数也留在 body 里一起传给 v1 服务；`menu_key` 只检查非空。没发现拼接，但下游服务没逐行看完。

## 7. 未覆盖
- 没有在本地重跑 phpunit / phpstan（只读审计，不建 worktree）。
- v1 服务层内部（DeptService / MenuService 等）只抽查了，没有逐行复审。
- 没审 diff 以外既有的 Subprocess.php，也没审 `.sand-dev-build` 的分发控制。
