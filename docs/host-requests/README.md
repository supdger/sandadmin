# HOST/COMPAT 请求流程

本目录定义消费工作区向 SandAdmin 反馈宿主问题的入口。它替代跨工作区直接改代码，不替代外部 Issue 系统；使用 GitHub 等平台时沿用同一字段和状态。

## 分类

- `HOST-*`：零插件基线或中立插件也能复现的通用宿主缺陷。
- `COMPAT-*`：宿主和插件分别可用、组合后失败，或扩展契约不明确。
- 插件自身领域、SQL、菜单、权限、服务或 UI 问题留在插件权威仓库，不进入 SandAdmin。

## 必要证据

报告必须冻结：SandAdmin revision、插件 revision、候选包 SHA、数据库/生命周期状态、精确复现步骤、预期与实际、去敏日志，以及能够区分宿主和插件的下一项实验。字段见 [模板](TEMPLATE.md)。

每轮只能改变宿主或插件之一。验证宿主修复时固定插件候选；验证插件修复时固定宿主 revision。demo 只接收同步结果，不作为修改源。

## 状态

`reported → accepted → fixed → committed → synced → retested → closed`

若同一现象连续三轮有效诊断仍不能缩小范围，状态改为 `BLOCKED_ROOT_CAUSE_UNKNOWN`，回到最后一个可复现组合并停止自动修改。只有提出新的可区分实验或补齐缺失外部条件后才能恢复。

SandAdmin 修复必须在 feature branch 形成独立 commit，并通过零业务插件检查。消费方拉取该 commit/RC 后，保持原插件候选不变复测；通过后再更新兼容记录。
