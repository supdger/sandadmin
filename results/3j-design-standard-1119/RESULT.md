# RESULT — 3j 设计高标准清单 skill（sandadmin1x-design-standard）

## 交付标识

- 基线 SHA：`977427acc285da074a443e8206309289a9bd94fb`（origin/main）
- 分支：`docs/1x-design-standard`（已推送，未强推、未合并）
- 最终 SHA：`469caddb13ea88f8d4ac15a0c133005fb2eeaab2`（第 2 个提交；首个 `4a55e2e`）
- PR：https://github.com/supdger/sandadmin/pull/109
- 修正说明评论：https://github.com/supdger/sandadmin/pull/109#issuecomment-7018545170

## 产物路径

- skill：`sandadmin/.agents/skills/sandadmin1x-design-standard/SKILL.md`（仓内，已入 PR）
- 数字台账：`~/agent-work/assistant/3j-design-standard-1119/sources.md`（任务目录，不进仓）
- 差异表：`~/agent-work/assistant/3j-design-standard-1119/gap-table.md`（任务目录，不进仓；另一工作包产出，本任务按 skill 对齐其归属列）
- AGENTS.md：必读 skill 小节加一句（本仓自有 skill、不在 skill-sync 四目录内、设计缺项审计打回）

## 清单要点

- 54 条定稿编号：D6 / H5 / A8 / C3 / T3 / S2 / X5 / O4 / M5 / E3 / Q3 / P6 / G1；每条必填：高标准要求｜1.0 做什么｜1.1 做或不做｜理由｜以后补的代价｜验收指标。
- 用法与审计口径：逐项回答，缺项/减法缺理由或代价/数字无来源一律打回；先按高标准写再做减法；Laravel/ThinkPHP FPM 只作底线；目标规模档与首期部署容量两套数字不混用。
- 规模口径引《老板已定事项总表》第 8 条原文（几百万是保守数，平滑扩到几千万、上亿，基础层留接口、第一版不必做满）。
- P 组关键数字（2026-11-19 实抓复核值）：
  - 公开参照（Benchmarker 滚动数据集，抓取日期 2026-11-19）：webman 110,952 req/s@c64（P50 0.68ms、P99 1.50ms、c512 100,555/P99 6.47ms、155/403、php 9/55）、workerman 173,597（P99 0.84ms、73/403）、gin 111,896、vertx 199,842、quarkus 151,114、spring 58,750、laravel 1,741。比值：webman≈Gin（0.99 倍）、≈Spring 1.9 倍、≈Quarkus 73%、≈Vert.x 56%、laravel≈webman 1.6%；workerman→webman 框架层约损 36%。
  - 我方门禁：4 核 4G/16 进程本机 keep-alive——hello ≥8 万 QPS、真实鉴权链+一次主库查询 ≥5,000 QPS（官方 1–2 万打 5 折推导，须 1.0 压测复核）；业务门禁沿用 v4：设计 300/压测 750/峰值 900 req/s、AI 300 路、浸泡 24h。
  - 延迟分位沿用 v4 I-4（登录 500ms/1s、列表 300/800ms、写入 500ms/1s、增量同步 1s/3s、AI 首字 2s/5s）；P4 reload 零失败；P5 kill 恢复 ≤5s 须实测；P6 RSS 24h 增 ≤10% 须实测。
- workerman 五项优势落点：常驻内存→P1/P2/C1（风险→P6）；多进程事件循环→A7/P1/H1（风险→T1 共享计数、D4 节点位、A5 共享锁）；内建 WebSocket/SSE→P3/A7（风险→P4 分组 reloadable=false）；自定义进程→A1/A5/P3/O1-O2（风险→A7/D2 预算）；平滑重载→P4/H5/M3（风险→只发整包制品+reload）。
- 六维度对标「几成」：吞吐延迟＝裸 HTTP 与 Gin 持平、Quarkus 七成、Vert.x 五六成；连接池＝六七成（预算制非池化）；服务治理＝六七成（限流超时齐、通用熔断与自适应卸载缺）；可观测＝五六成（日志审计强、metrics/trace 缺口）；优雅停机发版＝八九成（缺 LB 摘除钩子）；配置管理＝五六成（分环境齐、中心化缺）。

## 差异表对齐（gap-table.md）

- 判定未动：已覆盖 36 / 部分 18 / 缺失 0（分组：D 5已1部、H 2已3部、A 8已、C 3部、T 3已、S 1已1部、X 5已、O 2已2部、M 2已3部、E 3已、Q 2已1部、P 3已3部、G 1部）。
- 18 个部分覆盖项归属已逐条与 skill 对齐；5 项冲突改动：
  | 编号 | 原建议 → 改后 | 依据 |
  |---|---|---|
  | H3 | 1.1 → 1.0 | skill H3 定主库切换演练+双 AZ 文档为首期底线 |
  | O4 | 1.1 → 1.0（规则）/1.1（栈通道） | skill O4 定规则文本 1.0 成文 |
  | M1 | 1.1 → 1.0（演练runbook+PITR；跨地域备份仍暂缓） | skill M1 验收含恢复演练跑通一次（R-5 测试环境模拟） |
  | M4 | 1.0 → 1.1 | skill M4：按用户/比例灰度依赖 D3 租户路由 |
  | P1 | 1.1 → 1.0 | 老板 11-19 高性能单列；skill P1 定裸吞吐首期门禁 |
- 差异表新增「与 v4 归属冲突（需老板/架构确认）」一节（编号｜v4 原归属与行号｜标准归属｜理由）；人天重算：1.0 共 13 项约 **9–17.5 人天**，1.1 共 6 项约 **5.5–11 人天**，合计约 **14.5–28.5 人天**（M1 跨地域备份与 O4 告警栈暂缓无当期人天）。
- 部分覆盖项一览（编号/缺什么/归属/人天）：D6 慢查询阈值+采集分析/1.0/0.5–1；H3 主库切换设计与演练/1.0/1–2；H4 就绪依赖口径/1.0/0.5–1；H5 停机排空成文/1.0/0.5–1；C1 L1+L2 分级纪律/1.0/0.5–1；C2 通用失效策略/1.0/0.5–1；C3 击穿雪崩防护规范/1.0/0.5–1；S2 CDN 部署/1.1/0.5–1；O3 OTel 追踪栈/1.1/2–4；O4 告警规则/1.0/0.5–1（通道 1.1/0.5–1）；M1 演练runbook+PITR/1.0/1–2；M2 跨地域手段/1.1/1–2；M4 灰度策略/1.1/0.5–1；Q2 混沌工程化/1.1/1–2；P1 裸吞吐门禁实测/1.0/2–3；P4 排空流程+不停服数字/1.0/0.5–1；P5 崩溃拉起时限数字/1.0/0.5–1；G1 分环境配置体系/1.0/0.5–1.5。

## 未拍板与冲突清单

- 未拍板（原表保留）：E3 国际化范围（1.0 只中文 vs 双语）；D1/D3 MySQL 最低版本口径（8.0+ vs 8.0.16+/8.4 LTS）。
- v4 归属冲突待确认：H3、O4、M1、M4、P1 五项（见上表）。

## 仍待取证/须压测

- TechEmpower R23 中 webman/workerman 行值（官网 JS 渲染、tfb-status 本机不可达）。
- 我方裸基线门禁实测值（≥8 万 hello、≥5,000 带库鉴权链——推导目标，须 1.0 压测复核）。
- P5 kill 恢复时限 ≤5s、P6 RSS 斜率 ≤10%、每进程挂路数上限 50–100 路——均须 1.0 压测定基线。
- The Benchmarker 为滚动数据集：引用数字以 2026-11-19 抓取为准，审计时应重抓复核。

## 自查（命令、退出码、关键输出）

| 命令 | 退出码 | 关键输出 |
|---|---|---|
| `python3 ~/agent-work/grokbot/devin-queue/naming_check.py notes SKILL.md gap-table.md` | 0 | 0 FAIL 0 WARN，仅 INFO 注释占比行 |
| `bash tools/skill-sync.sh` | 0 | 四个同步目录全部 `OK 与上游一致`，新目录不影响 |
| `bash tools/pre-merge.sh --fast` | 0 | 7 步全过：PHP 语法/契约一致 PASS/危险函数 PASS/dev-only PASS（1423 文件）/TypePHP diff 0 FAIL/PHPUnit 408 tests 2613 assertions OK/PHPStan No errors |

附注：`composer install`（pre-merge 依赖 vendor）按骨架覆盖过 `server/webman`、`server/windows.php`，已 `git checkout` 恢复，git status 干净；只 `git add` 了两个提交各自的指定文件（首提 SKILL.md+AGENTS.md，返工仅 SKILL.md）。

## 范围声明

未改 server/src、契约、门禁、CI、tools、~/.shared-skills；未建库未启数据库；未合并 PR；未强推、未 reset --hard、未 --no-verify；全部 CLI 操作；未开下级会话；未读取或打印任何秘密文件。
