# RESULT — 修 PR #103 安全文档（security.txt 占位邮箱）

## 仓库与分支

- 仓库：`supdger/sandadmin`，本地工作副本 `/Users/supdger/agent-work/assistant/2d-security-docs-1119/sandadmin`
- 分支：`docs/security-policy`，在 `b5c4aa5` 上新增提交 `ba4dca7`
- 提交：`docs(security): security.txt 去除占位邮箱，发布清单补联系方式核对与合规剩余项`
- 推送：普通 push，`b5c4aa5..ba4dca7 docs/security-policy -> docs/security-policy`

## 改法

发布物里不再携带占位邮箱，实现路径选「删除占位、保留真实渠道」，而不是「标注不可发布」：

- `server/public/.well-known/security.txt`
  - 删除 `Contact: mailto:security@example.invalid` 占位行；
  - 保留 `Contact: https://github.com/supdger/sandadmin/security/advisories/new`
    （GitHub Private Vulnerability Reporting 入口，本次已开启，渠道真实可用）。
  - 该文件不在 `.gitattributes` export-ignore 名单，`git archive` 会进发布包；
    现内容即最终可发布形态，未编造任何邮箱。
- `SECURITY.md`
  - 备用渠道不再写占位邮箱，改为引用 `/.well-known/security.txt` 的 Contact；
    专用安全邮箱开通后写入该文件随版本更新。
- `RELEASE-CHECKLIST.md`「安全与合规」新增三条：
  - 发版前核对 security.txt 每个 Contact 均为可用渠道，新增联系方式必须先填
    真实地址再发布，发布物不得携带占位联系方式（发版前必须填写的检查项）；
  - 工信部漏洞报送责任人（剩余项，未指定不得勾选）；
  - SECURITY.md 报送条款与披露政策法务复核（剩余项，未复核不得勾选）。

说明：security.txt 按 RFC 9116 无注释语法，未加非标准标记行；占位邮箱本身已删，
文件不需要「填写后方可发布」的中间态，因此未采用「不填不进发布产物」方案
（该方案还需改发布脚本，属门禁类改动，超出本任务授权）。

## 校验结果

- `bash tools/pre-merge.sh --fast`：全部通过
  - PHP 语法检查、契约一致性比对、危险函数白名单扫描、dev-only 引用门禁、
    TypePHP 禁用写法扫描（diff 模式 FAIL=0）、PHPUnit Unit 408 tests / 2613
    assertions 全绿、PHPStan No errors。
- 占位扫描：`example.invalid` / `example.com` 在 SECURITY.md、security.txt、
  RELEASE-CHECKLIST.md 中零命中。
- `git status`：推送后工作区干净，分支与 origin 同步。

## Private Vulnerability Reporting（gh api）

- `gh api -X PUT /repos/supdger/sandadmin/private-vulnerability-reporting` → HTTP 204（exit 0）
- 读回 `gh api /repos/supdger/sandadmin/private-vulnerability-reporting` → `{"enabled":true}`

## 剩余项（不阻塞合并）

- [ ] 工信部网络安全威胁和漏洞信息共享平台报送指定责任人（已记入 RELEASE-CHECKLIST，未指定前该项不得勾选）
- [ ] SECURITY.md 报送条款与披露政策的法务复核（已记入 RELEASE-CHECKLIST，未复核前该项不得勾选）
