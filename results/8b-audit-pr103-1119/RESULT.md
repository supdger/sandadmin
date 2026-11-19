可合并

# PR #103 合并前独立审计结论

- 仓库：supdger/sandadmin（本地检出 `~/agent-work/assistant/2d-security-docs-1119/sandadmin`）
- PR：#103 `docs/security-policy` → `main`，OPEN，非草稿
- 审计时 head SHA：`ba4dca73277843e6470b1ba2708243bea00d8b11`
- 审计时 main SHA：`c506398e69dd55e5efc827a2499aac17fd80957f`（PR 记录 base_sha `977427a`，落后 3 个 merge commit；REST `mergeable: true, mergeable_state: clean`）
- 审计日期：2026-11-19
- 方式：只读。读 diff、跑 gh 只读命令由 SWE-2（dv-scout）子代理完成，主代理只判断、写结论。未合并、未推送、未评论、未改仓库文件。

## 1. 范围是否与描述一致 —— 一致

PR 描述写了 4 处纯文档，实际改了 4 个文件，共 +150/-0：

| 文件 | 类型 | +/- |
|---|---|---|
| `SECURITY.md` | 新增 | +69 |
| `server/docs/plugin-signing-keys.md` | 新增 | +70 |
| `server/public/.well-known/security.txt` | 新增 | +4 |
| `RELEASE-CHECKLIST.md` | 修改 | +7/-0 |

PR 描述有两处过时，不影响合并：
- 描述说 security.txt 里有占位邮箱 `security@example.invalid`，但第二个提交 `ba4dca7` 已把它删了，最终文件里没有邮箱字段。
- 描述里写的基线 `977427a` 已经落后于 main。

## 2. 门禁文件只减不增 —— 符合

- 没有改 `.github/`、`scripts/`、`tools/`，也没有改任何 hook、allowlist、baseline、ignore 或豁免类文件。
- `RELEASE-CHECKLIST.md` 是人工发布清单，不是脚本门禁。这次只加了更严的拦截项，比如"不能带占位联系方式"、"报送责任人未指定不得勾选"、"法务未复核不得勾选"。没有加豁免，也没有放宽规则。

## 3. 安全与正确性

- 泄露检查：diff 全文里没有内部邮箱、IP、密钥、口令或内网路径。
- security.txt（RFC 9116）：
  - Contact 字段存在，指向 GitHub 私密漏洞报告（PVR）。已用 API 确认 PVR 是开着的（`enabled: true`），这个渠道能用。
  - Expires 字段存在，格式合法，时间在未来。
  - 文件放在 `server/public/.well-known/`，Webman 的网站根目录是 `server/public`，所以对外路径就是 `/.well-known/security.txt`，正是 RFC 首选的位置。
  - Policy 链接指向 main 上的 SECURITY.md。这个文件合并后才会出现在 main 上，所以合并后链接才能打开，属正常。
- SECURITY.md 说支持的最新版本是 0.2.4，与 `gh release list` 显示的 Latest 版本一致。
- plugin-signing-keys.md 引用了 `PackageSignature.php` 和 `tools/package-ops`。这两个文件不在 PR head 上，但已在当前 main 上（由 #102 带入），合并后引用能打开。文档对照表里标"已实现"的两行，与 main 上的代码一致：Ed25519 分离签名验签、先验签再解包、没有签名默认拒绝。这个文档在 `tools/dev-only-paths.txt` 的剔除范围里，不会进发布包。
- 没有发现会导致错误行为或安全风险的问题。

## 4. 与《老板已定事项总表》是否冲突 —— 不冲突

- 总表没有提到 security.txt、漏洞披露、联系邮箱或披露周期，也没有直接提到 #103。
- 第 41 条：合并 PR、安全问题由技术总监自己定。本 PR 已由技术总监复验通过。
- 第 46 条：许可证统一 MIT、新建仓库须老板同意。本 PR 没有新建仓库，也没有改许可证。
- 第 24 条：自动测试不过不合并。CI 全部通过，见第 5 节。
- 第 50 条：合并前做独立审计。本次审计就是这一步。
- 语言要求在总表里仍是待确认项（待确认 4）。security.txt 写的是 `zh, en`，不和已定事项冲突。

## 5. `gh pr checks 103`（只读）—— 6/6 通过

composer audit ×2、frontend prod audit ×2、release self-check ×2，全部 pass。

## 6. 建议（不影响合并，可以合并后另开 PR 处理）

1. security.txt 的 `Expires: 2027-11-30` 离提交日约 376 天，超过了 RFC 9116 §2.5.5"应小于一年"的建议（这是 SHOULD 级，不是 MUST）。建议改到 2027-11-18 之前，并在发布清单里加一条"每年更新 Expires"。
2. SECURITY.md 把 security.txt 的 Contact 写成"备用渠道"，但两边其实是同一个 GitHub PVR，没有独立的备用联系方式。等真实安全邮箱定下来后补到 Contact（发布清单已经卡住这一项），或者把文档里的措辞改掉。
3. SECURITY.md 写了明确的时限承诺：3 个工作日确认收到、10 个工作日初步评估、Critical 7 天内修复、High 30 天内修复、2 日内报送工信部。这属于对外承诺，按发布清单要经法务复核和指定报送责任人后才能发布。合并本身不受影响。
4. 更新 PR 描述里过时的占位邮箱说法和基线 SHA。

## 未覆盖

- 没有在本地重跑 `tools/pre-merge.sh --fast`（只读审计，以 CI 结果为准）。
- 法务复核、报送责任人、真实安全邮箱都是仓库外的管理事项，无法核实。
