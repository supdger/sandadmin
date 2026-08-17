# AI 实现来源

本文件公开说明 SandAdmin 中由 AI 实现的代码范围。Git 提交中显示的作者和提交者仍是仓库维护者；`AI-Implemented` 仅说明实现工具，不替代维护者对审查、合并、发布和用户影响承担的责任。

## 已回填的历史范围

仓库维护者确认：以 SaiAdmin 上游 `c0f6ef327322cd85a800021d1a7c014df323dd2f` 为基线，其后合入 SandAdmin `main` 的 53 个提交由 OpenAI Codex 实现。每个对应提交的正文均包含：

```text
AI-Implemented: OpenAI Codex
```

回填前，该范围为 `c0f6ef3..84259b0`；回填会改变提交对象 ID，因此应以本文件、当前 `main` 历史和每个提交正文为准。上游基线、第三方依赖、许可证/版权文本及自动生成文件不因本声明被认定为 AI 原创实现。

## 后续提交规则

当变更实质上由 OpenAI Codex 实现时，提交正文必须带有以下 trailer：

```text
AI-Implemented: OpenAI Codex
```

Git 作者身份必须继续使用实际负责维护、审查和发布的人员身份。仅使用 AI 协助进行检索、讨论或格式化、而代码并非由 AI 实现时，不应添加该 trailer。涉及上游同步或混合来源时，应在提交正文说明边界。
