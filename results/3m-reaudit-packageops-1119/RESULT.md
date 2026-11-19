可合并：先把 PR #97（head `cd60208`）合进 `feat/kernel-api-runtime-align`，再把 PR #94 合进 `main`。#94 合并时它的 head 会包含 #97 的改动，以合并后的新 head 为准。

# RESULT：PackageOps 合并前复审（PR #94 + PR #97，M1–M4 修复后）

- 时间：2026-11-19 PST。方式：只读。主代理负责判断和写结论（`model.txt` 记 `fusion-claude-opus-5-5-medium`）。读 diff、查文件、跑 `gh` 只读命令都派给本机 dv-scout 子代理（SWE-2），共 3 个。没开下级会话，没用云端。
- 审计对象（固定 SHA，head 变了就要重审）：
  - PR #97 `fix/packageops-lifecycle` → `feat/kernel-api-runtime-align`。head 是 **`cd60208f3784f01656ae2de07d0a6c5ed619c6d2`**，GitHub 显示 MERGEABLE/CLEAN。相对上一轮审计的 `be4e770`，多了 2 个提交：
    - `a506118`：M1–M4 修复。
    - `cd60208`：Devin Review 自动修复，只把私有谓词 `locked()` 改名为 `isLocked()`，改了 3 处，含 2 个调用点，不改语义。
  - PR #94 `feat/kernel-api-runtime-align` → `main`。head 仍是 `8809904`，相对 main 领先 5、落后 0，MERGEABLE/CLEAN。
- 依据：上一轮审计 `~/agent-work/assistant/1d-audit-packageops-1119/RESULT.md`；修复记录 `~/agent-work/assistant/2a-pkgops-m1m4-1119/RESULT.md`。
- **本轮没有重跑测试或门禁**（只读，并且跑 phpunit 会写缓存）。测试结果引用修复记录里的数据（在 a506118 上：unit 517/3024 OK、PHPStan 0 错误、pre-merge --fast 7/7、build-release --self-check 8/8）。测试覆盖面是本轮静态读代码确认的。

---

## 一、M1–M4 逐条复核

| 项 | 上一轮要求的修法 | 实际落实（文件:行，以 a506118 为准） | 判定 |
|---|---|---|---|
| M1(a) 编译闸要求开发构建标记 | install/upgrade/uninstall 必须有 `server/.sand-dev-build` 才放行 | `PackageExecutor` 构造器新增必传参数 `string $devBuildMarker`，没有默认值（:27-35）。闸的判定是 `PackageGate::compiled() \|\| !is_file($devBuildMarker)`（:391-394，head 上改名为 `isLocked`）。uninstall 的闸在 :69，排在所有账本和文件操作之前。install/upgrade 走 `applyPackage`，闸在 :221，**排在验签 :226 之前**。传空串时 `is_file('')` 为 false，按"锁定"拒绝。`tools/package-ops:173` 传的是 `$repoRoot/server/.sand-dev-build`，`support/bootstrap.php:59` 的 `SAND_DEV_BUILD` 判的也是这个文件，口径一致。全仓 `new PackageExecutor(` 一共 4 处（CLI 1 处、测试 3 处），都传了标记路径。`PackageGate.php` 没改 | 通过 |
| M1(b) 发布包剔除 `tools/package-ops` | 在 `tools/dev-only-paths.txt` 登记 | 新增 `rm tools/package-ops`，外加 1 行注释。`strip-dev.php:22-57` 会把 rm 项从发布包里物理删除。`check-dev-refs.php` 扫描时跳过 rm 项。属于**剔除（收紧）**，不是豁免。修复记录里的 self-check 显示"清单路径均不存在" PASS | 通过 |
| M2 不回显 `SAND_DB_URL` | 只输出协议名或固定文案 | `tools/package-ops:149-154` 只输出 `$scheme`，解析不出来时输出"无法解析"。全文件没有任何输出语句再引用 `$dbUrl` | 通过（残留见第三节 N2） |
| M3 `removeTree` 不跟随软链 | `$dir` 本身是链接时只 unlink；遍历时遇到链接也只 unlink | `PackageJournal.php:163-188` 按要求实现：`is_link($dir)` 时 unlink 后返回；遍历项满足 `is_link \|\| !is_dir` 时 unlink，否则才递归。Package 模块和 CLI 里没有别的递归删除 | 通过 |
| M4 DbalPluginRegistry 补 suspend/resume | keep 卸载在同一事务里 suspend，安装在同一事务里 resume，与 `PackageOps::apply` 对齐 | `recordUninstall(false)`：UPDATE 和 `PluginResidue::suspend` 在同一个 `transactional` 里（:86-101）。purge：只 DELETE，和 apply 一致。`recordInstall`：INSERT/UPDATE 和 `resume` 在同一事务里（:55-83）。两者用的是同一个 connection，类注释也同步更新了。和 `PackageOps.php:499-510/:555` 逐段对齐 | 通过（口径差异见 N3，不阻断） |

### 补测覆盖（静态核读，没有发现跳过）

- M1：`testMissingDevBuildMarkerRefusesUnsignedInstall` 在没有标记、`SAND_COMPILED=0`、`allowUnsigned` 的条件下断言 `failed/gate`，并断言零副作用（没有插件目录、账本没有记录、work 目录没有残留）。`...RefusesSignedInstall` 用运行时真实生成的 Ed25519 签名，断言 `gate` 并且 `signature_verified===false`，证明闸在验签之前。`...RefusesUninstall` 断言插件目录和账本原样保留。原有的 `testCompiledHostRefusesInstall` 保留。
- M2：`PackageOpsCliTest` 用子进程真跑 CLI，断言退出码为 2，stderr 里有"协议不支持：oracle"，没有口令、用户名、主机。
- M3：Journal 新增 2 个用例，覆盖目录链接、文件链接、悬空链接，以及 `$dir` 本身是链接。Executor 新增端到端用例：卸载后，外部链接目标里的 `keep.txt` 还在。
- M4：`DbalPluginRegistryTest` 有 5 个用例，在 sqlite 内存库上真实执行 SQL，覆盖 suspend 置位、resume 清零、purge 只删行不 suspend。另有一个用例删掉残留表后断言抛错，并且账本行回滚为 installed，证明是同一事务。
- 4 个测试文件里，`markTestSkipped/markTestIncomplete/extension_loaded` 一处都没有。测试 diff 只删了 2 行，都是构造器参数从 5 个改成 6 个，**没有删除或放宽任何既有断言**。

## 二、是否引入新问题

| 检查项 | 结论 |
|---|---|
| 验签规则 | `PackageSignature` 没改。`applyPackage` 的顺序仍是 sha → 闸 → 验签 → inspect，只是闸的条件变严了。`--allow-unsigned` 的语义没变。**没有放松** |
| 门禁文件只减不增 | be4e770→cd60208 期间，门禁相关文件（`.gitattributes`、`.github/workflows/*`、`naming-allow.txt`、`phpstan.neon.dist`、危险函数白名单和扫描脚本、`pre-merge.sh`、`typephp-allow.txt`、`dev-only-paths*.txt`）只有 `dev-only-paths.txt` 改了，是 +2 行剔除项。**没有增加豁免** |
| 范围 | 一共 9 个文件，都在 `server/src/Module/Package/*`、`tools/package-ops`、`tools/dev-only-paths.txt`、`server/tests/Unit/Module/Package/*` 里。**仍在宿主 PackageOps 范围内** |
| 命令注入、静态状态 | 生产代码没有新增 exec 类函数。只有测试 `PackageOpsCliTest.php:41` 用了 `exec`，参数全部经过 `escapeshellarg`，而且测试目录会被剔除出发布包。没有新增静态可变状态（`devBuildMarker` 是 readonly 实例属性） |
| DDL、迁移 | 没有 |

## 三、非阻断观察（建议后续处理，不影响本次合并）

- **N1 `recover()` 没有闸**（PackageExecutor.php:141-173）：recover 会清理暂存和孤儿备份，并重跑收集。上一轮的 M1 只要求 install/upgrade/uninstall 三个闸，所以这不算漏修。另外 `tools/package-ops` 已经剔除出发布包，生产制品里没有这个入口。建议以后给 recover 也加同一个闸，作为纵深防御。
- **N2 连接串的间接泄露面**：`tools/package-ops:155` 的 `DriverManager::getConnection` 不在 try 里，第一次查询时抛出的 DBAL 异常没被捕获，会打到 stderr。另外 :207 会把执行器的 `getMessage()` 转发出去。驱动的错误文本里可能有主机名或用户名。PDO 和 Doctrine 通常不把口令写进消息，但本轮没法静态定论。建议把连接异常统一收成固定文案。
- **N3 resume 的覆盖面比网页通道宽**：CLI 在 install 和 upgrade 时都会 resume，网页通道只在 `plugin_install` 时 resume。已核实，`suspend_time` 只会和 `state='uninstalled'` 在同一事务里写入。upgrade 要求 `state='installed'`，这时该插件的 `suspend_time` 一定是 NULL，所以 resume 什么也不做。resume 也不动 `enabled` 列，不会把管理员手工停用的项重新打开。**按当前数据不会出错**。只有以后出现"已安装但 suspend_time 非空"的写入路径时，两个通道才会分叉，建议到时候统一。另外，全新安装失败回滚时调的是 `recordUninstall(false)`，会留下一条 uninstalled 行，而不是删掉。这在语义上等价于"未安装"，代码注释也说明是有意这样做的。
- **N4 本地验证落后远端一个提交**：修复记录里的本地门禁跑在 a506118 上。cd60208 只是私有方法改名，通过 `gh api compare` 已确认调用点全部改到，GitHub CI 和 Devin Review 都是针对 cd60208 跑的。CI 里没有 PHPUnit/PHPStan job，所以 cd60208 上没有单测或静态检查的实跑证据。风险很低，有余力时可以在 cd60208 上补跑一次 `tools/pre-merge.sh --fast`。

## 四、CI（`gh pr checks`，只读）

- #94（8809904）：composer audit、frontend prod audit、release self-check 各跑了两次（push 和 pull_request 各触发一次），**全部 pass**。
- #97（cd60208）：上面 3 项各两次全部 pass，Devin Review pass（"Devin fixed the finding"）。a506118 之后没有新的行内评论或其他评论。
- 注意：CI 没有 PHPUnit/PHPStan job（仓库现状，PR #81 merge-gates 还待批准），单测和静态检查的门禁只能靠本地复跑。

## 五、风险说明

1. **真实库没有验证**：`DbalPluginRegistry` 的 SQL，包括新加的 UPDATE+suspend 和 UPDATE+resume 事务序列，从来没有在 PostgreSQL 或 MySQL 上执行过，只在 sqlite 内存库上用最小同名列验证了 SQL 形状和事务边界。Integration 套件没跑。CLI 走真实账本的装、升、卸、回滚，以及双库矩阵，都没验证。在"CLI 只限开发构建（M1 已落实）、生产不安装"的前提下，可以接受。但合并后、对外宣称"插件安装闭环完成"之前，必须用 `SAND_TEST_DB_URL` 跑一次 Integration，并补一个和 `PluginResidueTest` 同口径的集成用例。
2. 沿用上一轮的已知缺口：迁移执行器还没有；升级不拒绝降级；卸载不查反向依赖；没有 SBOM 和来源证明；信任根还是"调用方自带公钥"的过渡口径；`main` 没开分支保护。
3. 这次批准只对上面固定的 SHA 有效（#97 `cd60208`、#94 `8809904`）。合并前如果任一 head 变了，就要重新审。

## 六、边界声明

- 已修改：只有本文件。
- 没做：合并、推送、评论、改仓库文件、git fetch/checkout、建库或启库、运行 package-ops、跑测试、读 .env 或密钥、开下级会话、用云端。
