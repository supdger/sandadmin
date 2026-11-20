【任务】11d 运行规范文档（1-16，Fusion 同上，只写文档，不建库）━━━━
分支：从 main 开 docs/ops-standards。
要做：M-20 主从切换语义、M-21 故障恢复 runbook（写明 RPO 和 RTO 目标）、M-23 慢查询阈值、M-24 缓存规范（击穿、穿透、雪崩、一致性）、M-25 配置分层。每份都要按 #109 清单逐项回答（高标准、1.0 做什么、1.1、理由、以后补的代价、验收指标），并对标 Spring Boot 和 go-zero 的默认值。规模按几千万到上亿。
交付：未合并 PR 加 RESULT。
模型分工：主代理只做设计判断和结论；读代码、查资料、写文件、git 操作全派给 SWE-2 子代理（本机 dv-* 子代理，不算下级会话）。

通用条款（技术总监定）：不建库、不启库，不往任何豁免、白名单或 baseline 里新增条目，不强推，不 reset --hard，不 --no-verify，不 git add -A，不用云端，不用 Cursor，不开下级会话，不读秘密。不合并（合并前另排 Fusion 审计）。

【技术总监 11-19 补充条款（老板定案后）】
- 数据库版本口径统一写成 MySQL 8.4 LTS 起、PG 15 起。

任务目录是 ~/agent-work/assistant/11d-ops-standards-1119/，RESULT.md 写在这里。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
