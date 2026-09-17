# HOST-003 / HOST-004 执行记录

日期：2026-09-17。实际 clean 起点 `07d83d591b85deb83875473687a0d033a418c778`，
分支 `codex/sandadmin-rename`。两项仅修改 SandAdmin；未修改 `sand_plugins`，
未检查或启动 PostgreSQL 服务，未执行其冻结现场恢复，未部署。
首次交付没有推送或连接数据库；后续用户要求立即解决，授权执行已明确范围的专项回归并推送开发分支。

## HOST-004

根因和最小修复见[宿主请求记录](../../../docs/host-requests/HOST-202609-004-file-cache-concurrency.md)。
详细复现及最终候选证据见[缓存回归记录](../../../docs/host-requests/evidence/HOST-202609-004-local-cache-regression.md)。

- 实现提交：`b4b37db`。
- 原驱动 40 进程、20 冷 + 20 热复现 783 个异常或标签断言失败事件，包含原始异常。
- 最终候选 40 进程、1,000 冷 + 1,000 热，80,000 批次，零失败，104.655 秒。
- 并发清理 100 轮、并发过期 100 轮、1,005 标签成员、普通/压缩损坏恢复通过。
- 合约测试 37 断言通过；独立审查发现压缩损坏误判缺失的边界，已修复并复验通过。
- 此证据来自真实 PHP 多进程、中立 ReflectionCache 和 CheckAuth 权限谓词。
  消费方随后基于 `2425803` 回传真实 HTTP/JWT 证据：
  40 worker，冷/热各 1,000 轮，80,000 批次、240,000 次请求，零失败。
  已核对 `.artifacts/sand-iam-0.7.2-v25-20260917T015450Z/host-recovery/HOST-004-http-jwt-regression-final.json`；
  它覆盖三个首屏接口的并发回归，不扩大为所有业务会话验收。

## HOST-003

根因和恢复边界见[宿主请求记录](../../../docs/host-requests/HOST-202609-003-fresh-install-recovery.md)。
具体测试、精确写库计划及脚本摘要见
[恢复回归记录](../../../docs/host-requests/evidence/HOST-202609-003-local-recovery-regression.md)。

- 新装恢复及官方 CLI 51 项、原有上游生命周期 44 项、SQL 执行器 27 项通过，共 122 项。
- 实现提交：`52b2bcd`。
- 官方 `recover inspect` 对旧新装失败记录返回提交未知，并支持绑定计划的正式清理；
  全部试验使用记录连接与临时文件，不连接数据库。
- 独立审查已通过；COMMIT 确认丢失保持未知，两个登记写入中断窗口均已修复。
- 新增真实 PostgreSQL opt-in 脚本在首次交付时仅 lint。脚本摘要
  `1b3445b625b581e2c488029f2e5da2662ebc6ba1fa54a5299faa841ffc74a12f`。
- 随后用户明确要求解决已说明的专项授权与验证问题。原脚本在本地 `sandadmin` 库实跑，
  7 项通过，退出码 0；只读复核固定测试 schema 残留为 0。未扩大至业务表或新数据库。
- 旧冻结现场的官方清理、修复候选再安装、账本与文件/服务健康仍待消费方验证，
  本轮不关闭 HOST-003。

## 通用检查与授权

- `bash tools/check-clean-host.sh` 通过：源码树无业务插件副本。
- OpenSpec strict 校验通过，仅表示规格格式有效。
- 首次授权不包含 HOST-003 隔离 DDL；提供具体对象、操作及清理方案并取得后续用户授权后完成专项。
- 系统 Git/Python shim 受 Xcode 许可提示阻断，未修改许可。使用已安装的 Command Line Tools Git
  及现有独立 Python 运行原命令。

## 消费版本

两项实现分别提交为 `b4b37db`（HOST-004）和 `52b2bcd`（HOST-003）。
消费方应拉取本执行记录所在的最终文档提交或其后经确认的 clean revision；
最终完整 SHA 由交接消息给出，不能将任一 dirty 工作树作为正式同步来源。
专项真实 PostgreSQL 与首屏真实 HTTP 均已补齐；消费方冻结现场的完整恢复与业务健康不在本任务内代报。
不归档整个变更，不以专项通过自动关闭尚待消费方确认的 HOST-003。

## 模型记录

- 主 Agent：实测 `gpt-6-astra / low / openai`。
- `astrahigh__cache_concurrency`：请求及实测 `gpt-6-astra / high / openai`。
- `astramedium__cache_review`：请求及实测 `gpt-6-astra / medium / openai`。
- `astrahigh__fresh_recovery`、`astrahigh__recovery_review`：请求及实测 `gpt-6-astra / high / openai`。
