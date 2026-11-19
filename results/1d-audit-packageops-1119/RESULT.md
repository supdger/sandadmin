# RESULT — 合并前独立审计：sandadmin PackageOps（PR #94 + PR #97）

- 审计时间：2026-11-19 06:10 PST；审计人：本地 Devin 会话（模型自报 Claude Opus 5.5，`model.txt` 记 `claude-opus-5-5-medium`），只读，未开下级会话。
- 审计对象（固定 SHA）：
  - PR #94 `feat/kernel-api-runtime-align` → `main`，head `8809904a1078adc87dd696e44d02392940f58e34`，相对 main `977427a` 领先 5 提交、落后 0；59 文件 +4565/−31；GitHub 报 MERGEABLE/CLEAN。
  - PR #97 `fix/packageops-lifecycle` → `feat/kernel-api-runtime-align`，head `be4e7701309359c3a992b229d9801c8236dea176`，merge-base 正好是 #94 head `8809904`，领先 3、落后 0；7 文件 +195/−17；MERGEABLE/CLEAN。
- 方法：`gh pr view/diff/checks`、`gh api` 只读拉取；#94 源码用已有复验检出（`~/agent-work/grokbot/pkgops-8809904-reverify-1118/sandadmin`，HEAD=8809904）里的 `git show 8809904:<path>` 读取，没 fetch、没 checkout、没改工作树；#97 用 PR diff。另读了上一轮复验留下的剔除后发布树 `dist/build-sandadmin-0.2.4-53-g8809904/`（只 `ls`）。
- **本审计没有重跑任何测试或命令行工具**。测试和门禁结果都引用历史 RESULT（pkgops-1118、pkgops-accept-1118、pkgops-reverify-1118、pkgops-probe-1118、pkgops-8809904-reverify-1118）和 PR 自述，本轮没有独立复跑。
- 资料路径对照：`/workspace/intro/老板已定事项总表.md` 在本机是 `~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md`；`/workspace/1x-docs/review-1117-r3/` 是 `~/agent-work/grokbot/review-1117-r3/`（v4 与 docs-ro 副本 cmp 一致）；docs/1x-skills 分支 `b7bc4c5` 的 `.agents/skills`。

---

## 结论：改后可合并

两个 PR 的主体方向正确，验签链路本身也没有放松。但发现 1 个违反已定事项的口子，外加 3 个小而确定的缺陷，必须先修。修完后按下面顺序合并：

1. 下面 M1–M4 修好（可以作为新提交落在 `fix/packageops-lifecycle`，也就是 #97 上），补对应单测，重跑无库门禁并独立复验；
2. 先把 **#97 合进 #94 分支** `feat/kernel-api-runtime-align`；
3. 再把 **#94 合进 `main`**。

### 必须修改（M1–M4）

**M1【违反已定事项 #5、v4 L1611/L383、packaging skill §1/§3】编译版和生产包里，只设一个环境变量就能绕过编译闸，装未签名包。**
- 事实：
  - `PackageGate::compiled()` 只看环境变量 `APP_ENV`、`SAND_COMPILED`（`server/src/Module/Package/PackageGate.php:29-40`），`SAND_COMPILED=0` 就判为未编译。
  - `tools/package-ops` 只 `require server/vendor/autoload.php`（第 26 行），不走 `support/bootstrap.php`，所以**不加载 `.env`**（composer 也没有 `autoload.files`）。生产 `.env` 里写了 `APP_ENV=production`，对命令行无效。
  - 发布剔除后的目录树里**保留了 `tools/package-ops` 和全部 `Module/Package/*`**，同时没有 `.sand-dev-build` 标记（实看 `pkgops-8809904-reverify-1118/sandadmin/dist/build-sandadmin-0.2.4-53-g8809904/sandadmin-0.2.4-53-g8809904/tools/package-ops`）。`.gitattributes` 和 `tools/dev-only-paths.txt` 都没剔除它。
  - 结论：在编译版或生产制品上执行 `SAND_COMPILED=0 php tools/package-ops install x.zip --allow-unsigned ...`，代码路径会放行。这正是 v4 L1611 明文禁止的「编译版用显式参数装未签名包」，也违反总表第 5 条「生产不安装」。v4 L383 和 skill 要求「未编译」按**构建产物标记**判定、不看可以伪造的东西，而环境变量正好可以伪造。
  - 前几轮复验的第 11–13 组只测了 `SAND_COMPILED=1`、变量缺省、`APP_ENV=production` 三种情况，**没测「发布制品 + SAND_COMPILED=0」**，所以没暴露。
  - 这是静态推断：本轮按规矩不执行命令行，没有实跑。
- 修法（二者至少取一，建议都做）：
  - (a) `PackageExecutor` 的 install/upgrade/uninstall 闸要求开发构建标记同时成立：`server/.sand-dev-build` 存在，与 `SAND_DEV_BUILD` 同一口径。标记不在，就算 `SAND_COMPILED=0` 也按编译版拒绝；
  - (b) 把 `tools/package-ops` 登记进 `tools/dev-only-paths.txt`，让发布包不带它。生产本来就只能查看、启用、停用（packaging §5），剔除不影响生产功能。这是加剔除项，属于收紧，不是放宽豁免。
- 补测：「无标记 + SAND_COMPILED=0 + 合法签名 / --allow-unsigned」两种都在 gate 阶段拒绝，零副作用；`build-release.sh --self-check` 证明剔除后树里没有 `package-ops`（如果选 b）。

**M2【秘密外泄】命令行报错把整条 `SAND_DB_URL`（含口令）打到 stderr。**
- `tools/package-ops:142`：`fwrite(STDERR, "SAND_DB_URL 协议不支持：{$dbUrl}（…）")`。协议写错时，连接串里的口令会进终端输出、CI 日志和交接文件。
- 修法：只输出协议名（`$scheme`）或固定文案，不回显 URL。

**M3【路径逃逸 / 破坏性删除】`PackageJournal::removeTree` 会跟随符号链接，删到插件目录外面。**
- `server/src/Module/Package/PackageJournal.php:158-179`：先用 `is_dir($path)` 判断，再递归 `scandir` 加 `unlink`。`is_dir` 对指向目录的符号链接返回 true，于是递归进链接目标、把目标里的文件逐个删掉；最后 `rmdir` 链接本身会失败，链接留着。
- 触发面：卸载时 `removeTree($backup)`（PackageExecutor.php:102），以及升级、回滚、`recover` 时删备份或暂存（同文件 :273、journal :94/:103/:116，`recoverOrphanBackups` :193）。插件目录里只要有一个指向目录的符号链接，执行就会删掉外部内容。这种链接可能是插件运行时自己建的，也可能来自开发者用 path 仓库软链进来的插件。从 zip 解出来的文件不会产生符号链接（`writeEntry` 是流拷贝），所以只有这两种来源会触发。
- 修法：遍历时先判断 `is_link($path)`，是链接就只 `unlink($path)`，不递归；对 `$dir` 本身如果是链接，也只删链接。补一条单测：插件目录内放一个指向目录外的符号链接，卸载后外部目录原样保留。

**M4【账本口径与网页通道不一致，真实库才会暴露】`DbalPluginRegistry` 漏了 `PluginResidue` 的 suspend/resume。**
- `DbalPluginRegistry.php:14` 注释写「SQL 口径与 PackageOps::apply 一致」，但网页通道 `PackageOps.php:509` 在不删数据的卸载时会调 `PluginResidue::suspend()`，在 `:555` 重新安装时调 `resume()`。CLI 账本的 `recordUninstall`（:81）和 `recordInstall`（:50）都没有做这两步。
- 后果：插件先经网页通道卸载（定时任务和扩展位覆盖被写上 `suspend_time`），再用 CLI 重装，`suspend_time` 不会被清空，这个插件的定时任务从此不再执行，管理员的扩展位覆盖也一直不生效（`ScheduleClaim.php:31-39` 按 `suspend_time IS NULL` 过滤）。这违反 v4 L383「与离线包/CLI 共用同一幂等账本」。无库单测测不出这个问题。
- 修法：`recordUninstall($app, false)` 后调 `suspend`，`recordInstall` 后调 `resume`，最好在同一事务里，口径与 `PackageOps::apply` 逐行对齐。等有真实库时，补一条与 `PluginResidueTest` 同口径的集成用例。

---

## 一、逐项审查

### 1. 范围：是否只在宿主 server/src PackageOps 及直接相关范围

| 范围 | 文件 | 判定 |
|---|---|---|
| PackageOps 承接（v4 L174/L370-374/L557-564 明列子项） | `server/src/Module/Package/*` 新增 12 个类、`PackageOps.php`（signature_verified 假 true 改为 false，新增 updateCheck）、`tools/package-ops`、对应单测与 `tests/Support/*` | 在范围内 |
| 系统更新探测承接（v4 L374、L564） | `UpdateProbe/UpdateChallenge/ProbeTransport/CurlProbeTransport`、`Http/UpdateProbeController.php`、`HostReadController::updateCheck`、`contract/openapi.yaml` 增加 1 条路由、契约生成物、`web/src/api/v1/systemUpdate.ts` 类型 | 直接相关，在范围内 |
| #94 原始题目：kernel_api 运行时与契约对齐 | `Contract/KernelApi/RuntimeCheck.php`、`FragmentCheck`、`ContractBuild`、`PhpEmit` 薄壳增加 `disableDefaultRoute`、sand-hello 夹具、`KernelApiTest` | 属于安装闸的 kernel_api 检查面（v4 L563 依赖闸），会被 `collectPlugins` 调用，算直接相关；但它和 PackageOps 承接混在一个 PR 里，PR 标题和正文只写了 kernel_api 部分，**PR 描述没有覆盖后 4 个提交**（建议补写，不阻断） |
| #97 | `ContractBuild`（重建时清孤儿）、`MemoryPluginRegistry`、`PackageExecutor`、`PackageJournal`、`tools/package-ops`、两份测试 | 在范围内 |

- 没碰 `server/plugin/`（Composer 产物）、sand-core、sand-package，也没碰鉴权链和中间件。
- 前端只加了类型（`contract.generated.ts` 是生成物，`systemUpdate.ts` 加了 `probe` 类型），没改页面。

### 2. 验签规则是否不放松

| 规则 | 代码事实 | 判定 |
|---|---|---|
| Ed25519 | `PackageSignature::verifyFile` 用 `sodium_crypto_sign_verify_detached`，签名和公钥长度严格校验（64/32 字节，原样字节或 base64），坏输入或 SodiumException 一律返回 false（PackageSignature.php:60-87） | 通过 |
| 先验签再解包 | `applyPackage` 顺序：sha 只读比对 → 编译闸 → `PackageSignature::checkPackage` → `PackageArchive::inspect` → 安装闸 → journal → extract（PackageExecutor.php:212-246）。验签失败时 work 目录和 journal 都还没建 | 通过 |
| 失败一律拒绝 | 缺签名默认拒；签名文件不可读、缺公钥、公钥不可读、验签不过都拒；`--allow-unsigned` 只豁免「没给签名」，签名给了但验不过照样拒（:31-53）。13 组场景在 c78aa47 和 8809904 两个 head 上都有零残留快照证据 | 通过 |
| signature_verified 不写假值 | CLI 通道只在真验签通过的成功路径上写 true，失败一律 false（`fail()` 强制 false，:412-415）；网页通道 `PackageOps.php:603` 从写死的 true 改成 false，并注明只做了 sha256；recover 恒 false | 通过 |
| 编译版禁未签名包 | 编译闸在验签之前，`SAND_COMPILED=1`、变量缺省、`APP_ENV=production` 都拒。**但编译判定只看可伪造的环境变量，且命令行工具随发布包分发，见 M1** | **不通过 → M1** |
| #97 是否动了验签 | #97 没改 `PackageSignature`，也没改 `applyPackage` 的验签顺序；只把 `journal->begin` 包进 try，失败时按 `stage=journal` 返回，签名字段仍走 `fail()` 的 false | 通过 |

非阻断的验签观察：
- **信任根是过渡口径**：公钥由调用方用 `--pubkey` 显式给出。v4 L405 写明「格式与信任根细则待定」，所以不算违规；但 docs/1x-skills 的 packaging §3 已经写成「公钥编进内核发行包、后台不可改；客户公钥只能经 CLI 加进白名单并记审计」。现在的验签只证明「包和操作员给的那把公钥匹配」，**证明不了来源**。在 M1 修好、通道只限开发环境的前提下可以接受；以后开网页申请通道时，绝不能沿用「调用方自带公钥」。
- **验签和解包之间有检查时与使用时的空档**：验签读取一次文件字节，`inspect` 和 `extract` 又各按路径重新打开一次 zip，两次读之间文件可能被换掉。本地命令行由操作员控制路径，风险低；建议以后先把包拷进 work 目录再验签和解包，或者整个流程只读同一份字节。

### 3. 门禁文件是否只减不增

- 两个 PR 的文件清单里都**没有**：PHPStan 基线和配置、`naming-allow.txt`、`typephp-allow.txt`、`tools/dev-only-paths.txt`、`scan-dangerous-functions` 白名单、`tools/pre-merge.sh`、`.github/` workflow、deptrac 配置。
- 测试只有新增：#94 的 `KernelApiTest` 有 2 行删除，是类注释和夹具 x-handler 改成新命名空间，不是删断言；生成契约测试的 1/1 改动只是源哈希头加 probe 路由行。#97 只新增测试。
- 历史 RESULT 记录：naming_check 0 FAIL；WARN 是 `DATA_POLICIES/$dataPolicy/$dataRemoved` 等对应已定字段 `data_policy/data_removed`，**没新增豁免**。
- 判定：**只减不增，通过**。注：M1(b) 要往 `dev-only-paths.txt` **加剔除项**，属于收紧，不违反这条。

### 4. 是否违反已定事项（总表 58 条 + v4 + 鉴权 P0）

| 条目 | 判定 |
|---|---|
| #5 生产不安装、编译版不能装 | **有口子 → M1** |
| #6 安全不低于 saiadmin 6.x | 网页上传执行、网页终端、在线拉包都没恢复（v4 L375-380 列的「不再支持」项一项未碰），比旧 sand-package 更收紧；M2、M3 修完后无回退 |
| #18 免登录生产剔除 | 未涉及；发布自检 8/8 PASS（历史证据），McpGate 依赖已去掉 |
| #19 TypePHP | 历史记录 typephp_check FAIL=0；#97 的 `MemoryPluginRegistry::$locks` 是静态可变数组，进程级共享状态，只在 CLI 演练账本使用，不进 Webman 常驻请求路径，可接受，记一笔 |
| #24 AI 改代码须自动测试 | 每处修改都带单测；**但 CI 只有 composer audit、frontend audit、release self-check，没有 PHPUnit 和 PHPStan job**（gh pr checks 实看），门禁全靠本地复跑。这是仓库现状（PR #81 merge-gates 还待批准），不是这两个 PR 造成的 |
| #46 不照抄代码 | 承接对象是自家旧 sand-package，不涉及外部代码 |
| v4 L383 未编译判定看构建产物标记 | **不满足 → M1** |
| v4 L383 / 已定 CLI 与网页共用同一幂等账本 | **CLI 漏 PluginResidue → M4** |
| v4 L405/L408/L484/L1466/L1611 | 除 M1 外都满足（见第 2 节） |
| 鉴权 P0：公开端点 | `/api/admin/v1/system/update/probe` 是 v1 契约里**第一条 `x-permission: public`**。它不挂会话鉴权，靠转发头拒绝、回环来源、150 秒有效的 32 字节一次性挑战加 `hash_equals` 做 fail-closed，任何一项不满足都返回 404；应答只回显调用方已经持有的 token 和 kernel_api 版本，没有信息泄露。v4 L374 明确要承接回环挑战探测，可接受。建议登记进鉴权 P0 的公开端点清单和路由矩阵（非阻断） |
| delivery-loop：插件安装改动 ≥2 人或安全岗审批 | 老板 11-19 定由本审计代替批准；本结论按此口径出具 |

### 5. DDL、迁移、未批准表结构

- 两个 PR **都没有** `.sql` 文件、`server/database/migrations/` 改动或任何 CREATE/ALTER。
- `DbalPluginRegistry` 只读写现有的 `sand_system_plugin`，列 `code/name/version/host_constraint/manifest_sha256/state/uninstall_time/update_time` 与现有 `PackageOps::apply`（PackageOps.php:535-552）使用的列完全相同，没引入新列或新表。
- `MemoryPluginRegistry` 的 `registry.state` 是 work 目录下的 JSON 演练文件，不是表。
- 判定：**通过**。

### 6. 安全（权限、路径穿越、命令注入）

| 项 | 结论 |
|---|---|
| 命令注入 | 新代码里没有 `exec/shell_exec/system/passthru/proc_open/popen/eval`（diff 全量 grep），也没有动态 include；cURL 不经 shell。通过 |
| zip slip、路径穿越（解包） | 不用 `extractTo`；逐条拒绝绝对路径、Windows 盘符和 `..` 段；只解 payload 前缀；`manifest.app` 正则限定 `^[a-z0-9]+(-[a-z0-9]+)*$`，落位路径不会逃逸；不会解出符号链接。通过 |
| 禁带内容 | 拒 vendor、node_modules、三件套 SQL、`.env*`、phar、so。非阻断：比较区分大小写，在 macOS/Windows 等大小写不敏感的文件系统上，`.ENV`、`Vendor/` 可以绕过；没有解压体积和条目数上限（zip bomb）。建议统一转小写比较，并加总量上限 |
| 路径穿越（删除） | **符号链接跟随 → M3** |
| uninstall 的 `<app>` 参数 | 没走 manifest 正则，只靠「账本里有记录」挡住。建议加同一条正则做纵深防御（非阻断） |
| SSRF | `CurlProbeTransport` 只放行字面回环（127/8、::1、localhost），不跟跳转，代理置空，超时 1s/3s；目标由监听配置推导，不接收请求参数。通过 |
| 秘密 | **连接串回显 → M2**；测试钥都是运行时临时生成，历史 evidence 已扫过、无泄漏 |
| 权限 | CLI 是开发环境操作员入口（M1 修好后才真正限定在开发环境）；`update/check` 维持原来的 `authenticated`，但现在每次调用会写挑战文件，并占用一个 worker 最多 3 秒去自请求。建议把「检查更新」收紧到平台超管，或加限流（非阻断） |

### 7. #97 专项

- 卸载/安装时 `journal->begin` 抛异常，现在收敛为 `stage=journal` 的失败报告；`begin` 加了 `file_exists` 短路。正确。
- `ContractBuild::build(write)` 清孤儿：只在固定目录 `server/tests/Contract/Generated/*.php` 下 glob，再用 `basename` 拼路径，没有穿越面；删不掉就报 failed，不假成功。正确。
  - 非阻断：孤儿删除发生在**产物已写入之后**。万一 `unlink` 失败，`collectPlugins` 返回 failed，执行器会回滚插件文件，但路由表等生成物已经按新插件写好，不会跟着回滚，留下「文件已回滚、生成物仍含该插件」的不一致，要等 `recover` 重跑收集才能纠正。卸载那条路径的收集失败也有同样问题（这个是 #94 就有的）。建议回滚后重跑一次 `collectPlugins`，或者先删孤儿再写产物。
- `MemoryPluginRegistry` 落盘：flock 串行化，按 realpath 去重防止同进程自锁，先写暂存文件再原子 rename，解析失败报错而不是当成空账本，文件名 `.state` 避开 journal 的 `*.json` 扫描。Devin Review 的 7 条意见都已修复。只用于 `--registry memory` 演练，不进生产路径。通过

---

## 二、风险说明（合并时须知）

1. **真实库没验证（Integration 没跑）**：`DbalPluginRegistry` 的 SQL 从未在 PostgreSQL 或 MySQL 上执行过；CLI 走真实账本的装、升、卸、回滚记账，双库矩阵（delivery-loop 第 1 步要求两库都跑），网页申请通道端到端，都没验证。可以缓解的事实是：SQL 形状和现有经过集成测试的 `PackageOps::apply` 同列同写法，`CURRENT_TIMESTAMP`、`fetchAllKeyValue` 两库都能用。**M4 正是只有真实库才会暴露的那类缺陷**，说明这个风险是真的。我判断这个风险在「CLI 只限开发环境（M1 修好后成立）、生产不安装」的前提下**可以接受**，不单独构成否决；但合并后、对外宣称「插件安装闭环完成」之前，必须用 `SAND_TEST_DB_URL` 跑一次 Integration，并补 M4 的集成用例。
2. **迁移执行器还没有**：包里的 `migrations/` 不执行，安装时也不提示。带表的插件 CLI 会报 PASS，但表不存在；`data_policy` 选 export-delete/delete 时如实报告「未动数据」。建议在执行器上线前，包里含 `migrations/` 就拒装或至少给出醒目提示（非阻断，放行稿已知缺口）。
3. **已知未做（放行稿没有明文，不自行发明）**：升级不拒绝降级，卸载不查反向依赖，安装闸没有 SBOM、来源证明和依赖漏洞扫描（packaging §6 第 5–7 项）。
4. **探测在单 worker 下必然超时**：`SANDADMIN_WEB_WORKERS=1` 时，worker 自己请求自己会等满 3 秒后报失败，不会挂死；并发检查会互相覆盖挑战文件。属于功能层面，非阻断。
5. **流程层面**：`main` 没开分支保护（`gh api branches/main` 返回 protected=false），CI 不跑测试和静态检查，合并门禁靠本地复验和本审计。

---

## 三、证据索引

- PR 元数据、检查、评论：`gh pr view 94/97`、`gh pr checks 94/97`（6/7 项全部 pass，只有审计和发布自检）、`gh api pulls/97/comments`（无行内评论；Devin Review 7 条已修复）、`gh api compare`（线性、无落后）。
- 源码行号均以 `8809904` 为准；#97 改动以 `gh pr diff 97` 为准。
- 发布剔除树：`~/agent-work/grokbot/pkgops-8809904-reverify-1118/sandadmin/dist/build-sandadmin-0.2.4-53-g8809904/sandadmin-0.2.4-53-g8809904/tools/package-ops`（存在）。
- 依据：v4 L174、L370-380、L383、L405、L408、L484、L557-564、L1466、L1611；总表第 5/6/18/19/24 条；docs/1x-skills `sandadmin1x-plugin/references/packaging.md` §1/§3/§5/§6，`sandadmin1x-ai-loop/references/delivery-loop.md` 第 1–4 步。
- 历史门禁（引用，本轮没复跑）：8809904 上 PHPUnit unit 501/2964 OK、PHPStan 0 错误、contract-build --check PASS、危险函数扫描 PASS、typephp FAIL=0、naming 0 FAIL、发布自检 8/8；#97 自述 unit 505 全绿、pre-merge 7/7。

## 四、本轮边界声明

- 已检查：上面所有条目（只读）。
- 已修改：只有本文件 `~/agent-work/assistant/1d-audit-packageops-1119/RESULT.md`。
- 没做：合并、评论 PR、推送、改仓库、建库或启库、运行 package-ops、重跑测试、读 .env、private 等秘密文件、开下级会话、用 Cursor 或云端。
