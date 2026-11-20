# 12j HR5-b systemd 模板和 kill -9 脚本 — RESULT

日期 2026-11-20。结论：**等 12a**，未开工。

## 前置检查（只查一次）

- 任务前置：「12a 已合并」。12a（3I-5a 常驻内存安全基座）交付物为 supdger/sandadmin 的 **PR #114**（分支 `feat/runtime-context`，对应 HR5-a `/health/live` 等）。
- 实测：`gh pr view 114 --repo supdger/sandadmin --json state,mergedAt,title` →
  `{"mergedAt":null,"state":"OPEN","title":"feat(server): 常驻内存安全基座 3I-5a——上下文隔离、运行时回收、存活探针"}`
- PR #114 仍 OPEN、未合并；按开工规则「不满足就写'等 12a'结束，不等待」，本任务不建分支、不改代码。

## 计划行对照（备查，开工后执行）

- 需求行：`results/10g-fix-plan-review2-1119/RESULT.md` M-12（HR5 崩溃自愈）HR5-b（1-13，0.5–1 人天，依赖 1-4 / HR5-a）：仓内 systemd 单元模板（Restart=always，StartLimit 崩溃循环熔断）+ kill -9 验收脚本，3s 拉起目标；只交付仓内模板和用例，不部署。
- 验收口径：本机 19100–19199 端口起 webman，kill -9 一个 worker 后 3 秒内拉起并记录耗时；跑完停进程、确认端口释放。
- 目标仓：supdger/sandadmin（与 12a 同仓），分支 `feat/systemd-selfheal`，从最新 main 开。

## 执行记录

- 已修改：无（仓库未动）
- 已验证：前置检查命令如上，退出码 0
- 已提交/已部署：否
- 未完成：全部实现工作，等 PR #114（12a）合并后重派
