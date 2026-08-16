# SandAI 管理应用前端

此目录承载 SandAdmin 内置 SandAI 的管理页面。字段与路径对齐 C-04/C-05 契约；应用、环境、工作负载客户端、凭证和服务授权由 SandIAM 管理，SandAI 仅消费其只读引用，不提供独立插件安装入口。

## 约定

- 契约文档：`docs/development/sand-ai-api-contract.md`
- API 前缀：`/app/sand-ai/admin/...`
- 权限标识：`sand_ai:<resource>:<action>`
- 开关：`api/config.ts` → `USE_SCHEMA_MOCK=false`
- 开发入口：`http://127.0.0.1:3007`（勿用 3006）
- 开发代理：`VITE_API_PROXY_URL=http://127.0.0.1:8788`
- 开发菜单：`VITE_ACCESS_MODE=frontend`

## 任务进度

- U-01 ~ U-07：已完成（文件/任务/总览聚合/知识库占位）
- A-01：端到端验收：待本地 SandIAM 接受数据和工作负载上下文签发后完成
- 身份边界：仅接受 SandIAM 签发的工作负载上下文；不回退到 SandAI 自建 API Key
- 验收约定：本地 Fake Provider；无需真实模型 Key，除非后续生产联调
