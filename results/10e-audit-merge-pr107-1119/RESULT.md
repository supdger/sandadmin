可合并（审计时 head：b878b0df985f3dfd066580e9084f2a8a8f459b0a）——已合并，main 新 SHA：05efc923593e067683f03bc7c3375f08ea1646a8

# PR #107 合并前独立审计（10e）

- PR：supdger/sandadmin#107 `feat/http-security-mw` → main，标题「feat(server): HTTP 安全中间件（N3 响应头 + v1 Origin/CSRF + CORS 白名单）」
- 审计时 head：b878b0d（7 个提交：d4e8bb0、77ed4e4、977c396、c8fc46a、14bd9a5（合并 main a18fef9）、81ed8f1、b878b0d）
- 合并前 main：02cdbe8f6ffa4d685ccb43262577612887df3316（自 merge-base a18fef9 起新进 #104 test-gate、#111 migration-runner，共 6 个提交）
- 合并：`gh pr merge 107 --repo supdger/sandadmin --merge`，2026-11-19T23:44:23Z，merge commit 05efc92，源分支保留

## 一、审计结论分项

### 1. 范围与描述一致 —— 符合
- 47 个文件，与 PR 描述的变更清单逐项对得上：3 个中间件、3 个配置类、SiteOriginGuard 及注册、security/middleware/route/static/process 配置、openapi.yaml 登记 CSRF_REJECTED、契约产物重生成（只改源哈希行；ErrorCodeRegistry、contract.generated.ts 加 CSRF_REJECTED）、前端 env.ts/adminClient.ts 带标记头、5 个新单测类、断言脚本、两份 .env.example。
- 没有夹带无关改动；描述已更新到最终 SHA b878b0d，并补上了合并后的门禁结果。

### 2. 安全与正确性 —— 符合，无阻断
- CSRF：只对 `/api/admin/v1` 和 `/api/admin/v1/` 前缀下的非 GET/HEAD/OPTIONS 请求生效。
  - Origin 有值：先按严格形态解析（含 userinfo、路径、query、fragment、通配符或非 http(s) 一律判非法），再与 site_origin 精确比较。`null` 和伪装形态都会被拒。
  - 缺 Origin：回落到 Referer 同源判断；两者都没有就拒绝。
  - 标记头必查；如果配置了值，还要求值相等。
  - 拒绝时返回 403 `CSRF_REJECTED`，用标准错误信封，带 request_id。
- site_origin 只读部署配置，不从 Host 或转发头推导。生产环境缺少或形态非法时，SiteOriginGuard 拒绝启动，并调用 haltMaster 防止 worker 反复重启。
- CORS：精确白名单。ACAO 回写的是规范化后的白名单值，不是请求原值。永不签发 Allow-Credentials。预检直接应答，不进下游；白名单外的预检返回 204 且不带 ACAO。有 Origin 就加 `Vary: Origin`。白名单不等于 CSRF 放行，写操作仍要满足 Origin===site_origin。
- 安全头：放在全局最外层，短路响应也会带头。fallback 和静态链另外挂了中间件，补上了 webman 中 fallback 不经过全局中间件的缺口。同名头先按大小写不敏感删除再写入，不会出现重复头。HSTS 只看连接 transport=ssl，不信任转发头。
- 技术总监已定的取值都已落实：Referrer-Policy=strict-origin-when-cross-origin，X-Requested-With 前后端默认 XMLHttpRequest，CSP 起步值为 api `default-src 'none'` / page `default-src 'self'`，默认 Report-Only 灰度。
- 前端：`request` / `requestDownload` / `adminUpload` 三处都带上了标记头，有 msw 单测覆盖。上一轮发现的阻断问题（后台写操作会全部 403）已解决。

### 3. 门禁文件只减不增 —— 符合
- `.github/`、`tools/pre-merge.sh`、`tools/contract-build`、`typephp-allow.txt`、`dev-only-paths*`、`dangerous-functions-allowlist.txt`、`naming-terms.tsv`、phpstan 配置：都没有改动。
- 新增的 `tools/assert-security-headers.sh` 是新的检查工具，不是豁免。openapi.yaml 新增的是错误码登记，与已有 4 个通用码同类，不算放宽门禁。
- 没有新增任何豁免或白名单条目。

### 4. 与《老板已定事项总表》不冲突 —— 符合
- 总表没有直接涉及安全头、CSRF、CORS 的条目。
- 与相关条目一致：
  - 第 6 条「安全不低于 saiadmin 6.x」：本 PR 是加强。
  - 第 41 条「安全和纯技术选型由技术总监自己定」：取值是技术总监定的，并已复验。
  - 第 24 条「自动测试不过不合并」：门禁全过。
  - 第 18 条「开发环境保留免登录」：未受影响。

### 5. 上一轮审计（8c，head c8fc46a，结论「改后可合并」）的必改点 —— 全部已落实
1. 前端不带 CSRF 标记头：已修，提交 81ed8f1，见 diff 中 env.ts / adminClient.ts / 测试。
2. 与 main 冲突、契约产物要重新生成：已修，提交 14bd9a5 合并 main，产物源哈希是 0e05c742…；`contract-build --check` 通过。
3. 描述与范围不一致：已更新。

8c 的建议项 a–d 不阻断合并，留作后续：
- a：旧插件路由 `/core/*`、`/tool/*`、`/app/sandpackage/*` 不经过全局 `''` 键，没有安全头。
- b：TLS 反代下不输出 HSTS，要等 N2 trusted_proxies。
- c：CSP 没有 report-uri。
- d：dev-login 在 CSRF 作用域内。

8a 目录（8a-audit-pr105-1119）审计的是 PR #105，与本 PR 无关。

### 6. 与新 main（含 #104、#111）的合并性 —— 符合
- `git merge-tree --write-tree origin/main b878b0d` 退出码 0，没有冲突，tree 为 0bef2603。
- main 那边没有改 openapi.yaml 和任何生成产物，也没有改本 PR 涉及的中间件、配置和前端文件，合并后源哈希不会过期。
- 在合并结果树（临时 worktree，已清理）上实测：

| 命令 | 退出码 | 结果 |
|---|---|---|
| `tools/pre-merge.sh --fast` | 0 | 8/8 全过（见下方说明） |
| `php tools/contract-build --check` | 0 | PASS |
| `phpunit --testsuite unit` | 0 | 705 tests / 3666 assertions OK |
| `phpstan analyse` | 0 | No errors |
| web `pnpm typecheck` | 0 | 通过 |
| web `vitest adminClient.test.ts` | 0 | 18/18 |

- `pre-merge.sh --fast` 第一次跑在 TypePHP diff 扫描那步失败：报 `server/config/database.php` 的 `$resolve()`。原因是 clone 里本地 `main` 指针落后于 origin/main，main 自己近期的改动被当成了新增行；PR #107 根本没有改 database.php。把本地 main 临时指到 origin/main 后重跑全部通过，跑完已恢复。
- 未跑：`--full` 集成套件和改动行覆盖率门禁（需要数据库或 pcov，本任务禁止启动数据库）。

### 7. 合并前置条件（合并前即时复核）
- head 为 b878b0df…（以 b878b0d 开头），state=OPEN，不是草稿，MERGEABLE / CLEAN。
- `gh pr checks 107`：6/6 pass（composer audit、frontend prod audit、release self-check 各 2 次）。
- 合并前 main 为 02cdbe8，与审计时一致。

## 二、后续建议（不阻断）
- 架构复审 v1 挂载机制：现在是全局挂载，再在中间件里按前缀限定作用域。
- 8c 建议项 a–d 另立工作包。
- CSP 从 Report-Only 切到强制之前，先补上报地址和灰度观察。

## 模型记录
- 主 Agent：Fusion（Claude Opus 5.5 Medium + SWE-2 Medium，系统提示声明；sessions.db 未核）
- 子 Agent：dv-scout ×2（证据收集、合并性核对）、dv-implementer ×2（合并结果树门禁、合并执行）= 请求 SWE-2 档案 → 实际模型未记录

## 任务记录
- 已修改：本文件；GitHub 上 PR #107 已合并
- 已验证：见第一节第 6、7 项
- 已提交/已部署：已合并到 main（05efc92）；未部署
- 未完成：无
