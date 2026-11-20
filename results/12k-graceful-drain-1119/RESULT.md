# 12k HR6-b 排空和发版序列 — RESULT

日期 2026-11-20。结论：**等 12j**，未开工。

## 前置检查（只查一次）

- 任务前置：「12j 已合并」。12j（HR5-b systemd 模板和 kill -9 脚本）的 RESULT（`~/agent-work/assistant/12j-systemd-selfheal-1119/RESULT.md`）结论为「等 12a」，未建分支、未开 PR。
- 12j 的前置是 12a（HR5-a 常驻内存安全基座），交付物为 supdger/sandadmin **PR #114**（分支 `feat/runtime-context`）。
- 实测：`gh pr view 114 --repo supdger/sandadmin --json state,mergedAt,title` →
  `{"mergedAt":null,"state":"OPEN","title":"feat(server): 常驻内存安全基座 3I-5a——上下文隔离、运行时回收、存活探针"}`
- `gh pr list --repo supdger/sandadmin --state all --search "12j OR systemd OR drain"` → `[]`，仓内不存在 12j 的任何 PR。
- 即：12j 未交付、未合并，本任务前置不满足。按开工规则「不满足就写'等 12j'结束，不等待」，不建分支、不改代码。

## 计划行对照（备查，开工后执行）

- 需求行：`results/10g-fix-plan-review2-1119/RESULT.md` M-12（HR5 崩溃自愈）HR6-b（1-14，依赖 1-4 / HR6-a / 12j）：web 组 `stop_timeout=30s`；`onWorkerStop` 里排空在途请求；发版序列脚本，顺序为 readiness 置 0 → 等负载均衡摘流 → `reload -g` → 恢复。
- 验收口径：本机压测时做一次 reload，在途请求零失败，并记录 5xx 个数；本地服务只用 19100–19199 端口。
- 目标仓：supdger/sandadmin，分支 `feat/graceful-drain`，从最新 main 开。
- 依赖链现状：12j ← 12a = PR #114（OPEN）；#81（merge-gates）也仍 OPEN，但按规则只卡合并不卡开工。

## 执行记录

- 已修改：无（仓库未动）
- 已验证：前置检查命令如上，退出码 0
- 已提交/已部署：否
- 未完成：全部实现工作。解锁条件：PR #114（12a/HR5-a）合并 → 12j 完成并合并 → 重派本任务
