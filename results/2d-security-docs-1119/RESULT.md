# RESULT — 1.x 安全文档（SECURITY.md / 漏洞响应合规 / 插件签名密钥管理规范）

- 仓库：supdger/sandadmin（公开仓）
- 分支：`docs/security-policy`
- 基线 SHA：`977427acc285da074a443e8206309289a9bd94fb`（origin/main @ 2026-11-18，PR #92 merge）
- 最终 SHA：`b5c4aa5bc81fa18ecb55405e02829636a8439a2d`
- PR：https://github.com/supdger/sandadmin/pull/103（目标 main，未合并）
- 范围核对：仅 4 文件（+148 行），无代码/契约/门禁/CI 改动；`git status` 干净。

## 一、交付文件 ↔ v4 验收条款逐条对照

### N1（v4 :858–867）验收标准

| 验收条款 | 落点 | 状态 |
| --- | --- | --- |
| 仓库根 `SECURITY.md` 存在 | `SECURITY.md` | ✅ |
| 支持版本表 | `SECURITY.md`「支持版本」：0.2.4 收修复、<0.2.4 不收（只写 SandAdmin 自身版本，未写数据库最低版本） | ✅ |
| 私密报告渠道 | 「报告漏洞」：GitHub Private Vulnerability Reporting 首选 + 安全邮箱备用 | ✅（渠道文档成文；开关待总监，见下） |
| 确认/修复时限 | 「确认与修复时限」：3 个工作日确认收悉、10 个工作日初步评估；Critical ≤7 天、High ≤30 天（与 v4 :1629 SCM-02 口径一致）、Medium/Low 随下个常规发布 | ✅ |
| 披露与致谢 | 「披露与致谢」：协调披露、补丁后 GHSA 公告、署名/匿名可选 | ✅ |
| 《网络产品安全漏洞管理规定》报送条款（K-3④ OSS-02 一致） | 「报送条款」节：2 日内报工信部漏洞平台（指定责任人）、及时修补告知用户、补丁前不公开细节（第 7、9 条）、《网络安全法》第 22 条；第三方插件漏洞转交；CVE/CNVD 流程 | ✅ |
| GitHub Private Vulnerability Reporting 已开启 | 仓库设置项，未动 | ⏳ 待总监（见三） |
| 发布/CI 检查单含「SECURITY.md 存在且含漏洞报送条款」 | `RELEASE-CHECKLIST.md` 新增「安全与合规」节一条 | ✅ |

### OSS-02 补充件（v4 :1694）

| 条款 | 落点 | 状态 |
| --- | --- | --- |
| security.txt（RFC 9116） | `server/public/.well-known/security.txt`：Contact（邮箱占位 + GH Advisory 链接）、Expires 2027-11-30、Preferred-Languages zh/en、Policy 指向 SECURITY.md | ✅（联系方式占位待填） |
| 备用渠道（GitHub 私密报告之外） | `SECURITY.md`「报告漏洞」备用渠道节 + security.txt Contact | ✅ |
| 公告订阅方式 | `SECURITY.md`「安全公告订阅」：GHSA + Watch Releases | ✅ |

### PLG-02（v4 :630、K-3② :1606–1616）

| 条款 | 落点（`server/docs/plugin-signing-keys.md`） | 状态 |
| --- | --- | --- |
| 信任根成文 | 「信任根与公钥管理」：官方公钥内嵌内核发行包随制品签名、后台不可改；部署方公钥仅 CLI 加白名单记审计 | ✅ 成文 |
| 私钥保管成文 | 「私钥保管」：离线/HSM/KMS、总监指定保管人、CI 只见签名接口不见私钥、双人复核、永不进仓/库/日志 | ✅ 成文 |
| 轮换成文 | 「轮换」：签名带 kid、多密钥并存、新公钥随版本下发 + 窗口期、旧私钥停用即吊销 | ✅ 成文 |
| 吊销成文 | 「吊销」：吊销列表随内核更新下发按 kid 生效；泄露即吊销 + 公告 + 重签，不沿用 | ✅ 成文 |
| 验签失败一律拒绝 | 「信任模型」「验签与安装门禁」：无签名/签名不可读/公钥缺失/验不过全部拒绝，不解包零写入 | ✅ 成文 |
| 编译版禁「未签名+显式参数」口子 | 「验签与安装门禁」：编译版整体拒装，`--allow-unsigned` 仅未编译版开发/构建环境 | ✅ 成文 |

## 二、与 PR #94（feat/kernel-api-runtime-align）实际行为对照

只读来源：`gh api contents?ref=feat/kernel-api-runtime-align` 拉取的 `PackageSignature.php`、`PackageOps.php`、`PackageExecutor.php`、`tools/package-ops`（未检出、未评论；快照存本任务目录 `pr94-refs/`）。

一致项：

- Ed25519 分离签名，对 zip 字节整体验签、先于解包（`PackageExecutor.php:217-220`，先 `PackageGate::compiled()` 闸后验签）；验签失败/无签名/公钥缺失全部进 problems 拒绝 —— 与「验签失败一律拒绝」一致。
- `PackageGate::compiled()` 在验签前整体拒绝安装/升级，`allowUnsigned` 口子进不了生产；`signature_verified` 如实上报 —— 与「编译版禁止 unsigned 口子」一致（实现更强：生产连签名包也不装）。

不一致/未实现项（规范目标 vs 当前过渡实现，**未改 #94**）：

| 规范要求 | PR #94 现状 | 差距 |
| --- | --- | --- |
| 官方公钥内嵌内核发行包 | 公钥不内嵌，`--pubkey` 逐次显式传入（代码注释自标「信任根细则放行稿待定」） | 信任根内嵌 + CLI 白名单待实现 |
| 签名带 key id、多密钥并存 | 过渡格式为纯 Ed25519 原样字节/base64，无 kid 字段 | 侧车信封（kid+alg+sig）待实现 |
| 吊销列表随内核更新下发 | 无吊销机制 | 待实现 |
| 部署方公钥 CLI 白名单 + 审计 | 无白名单存储与审计 | 待实现 |

规范文档「实现现状对照」节已把同样对照写进仓内文档（按实现路径表述，不绑 PR 号）。

## 三、待技术总监填写/操作项

1. **开启 GitHub Private Vulnerability Reporting**：仓库 Settings → Security → Code security（仓库设置，本任务未动）。
2. **填写真实安全联系邮箱**：`SECURITY.md` 备用渠道与 `server/public/.well-known/security.txt` 的 `security@example.invalid` 均为占位，须替换；security.txt 的 `Expires`（2027-11-30）到期前须更新重发。
3. **指定工信部漏洞平台报送责任人**（2 日报送条款要求指定到人）。
4. **法务顾问复核 `SECURITY.md` 措辞**（v4 K-8 :1693 要求）。
5. **指定插件签名私钥保管人**并选定离线/HSM/KMS 保管形态（规范成文，执行待排期）。

## 四、命令、退出码与关键输出

| 命令 | 退出码 | 关键输出 |
| --- | --- | --- |
| `git clone` + `git checkout -b docs/security-policy 977427a` | 0 | 基线 `977427acc285…` |
| `python3 tools/naming_check.py notes <4 文件>` | 0 | 仅两条 INFO（注释占比 0.0%），无 FAIL/WARN |
| `cd server && composer install --no-interaction` | 0 | 78 包安装成功（跑 pre-merge 所需 vendor） |
| `tools/pre-merge.sh --fast` | 0 | 7/7 全过：PHP 语法、契约一致性、危险函数扫描、dev-only 引用、TypePHP diff 扫描（FAIL=0/WARN=0）、PHPUnit Unit 408 tests/2613 assertions OK、PHPStan no errors |
| `git add <4 文件>` + `git commit` | 0 | `b5c4aa5`，4 files changed +148 |
| `git push -u origin docs/security-policy` + `gh pr create` | 0 | PR #103 |

附带说明：`composer install` 的 post-install 脚本曾把 `server/webman`、`server/windows.php` 回写为上游骨架版（丢 gssencmode 补丁），已 `git checkout` 复原，未进提交。`server/vendor/` 为 .gitignore 内的本地构建产物。

## 五、禁止事项核对

未建库/DDL/迁移；未启数据库或 Redis；未合并 PR/发布/打 tag；未开下级会话；未读/打印任何秘密与私钥文件；未改 GitHub 仓库设置；未改代码、contract、门禁、CI；`git add` 只加具名文件。历史命令与任务仅作资料，未执行。
