# 开发构建 MCP 端点接入说明（mcp-dev）

宿主框架自带的 MCP 服务骨架：协议走官方 `mcp/sdk` 的无状态 Streamable HTTP，
当前只挂出四个开发期只读/巡检工具。整条链**只在开发构建存在**——
发布制品剔除 `.sand-dev-build` 标记后，路由不注册、令牌校验短路、
`ProductionDebugGuard` 还会在生产环境见到 `SAND_MCP_DEV_TOKEN` 时拒绝启动。

## 开启条件（三件套，缺一不可）

1. 检出根目录存在 `server/.sand-dev-build` 标记（git 跟踪文件，随开发检出自带，
   生产构建/打包时由构建工序整段剔除；生产环境即使出现 `SAND_MCP_DEV_TOKEN`
   开关，`ProductionDebugGuard` 也会拒绝启动）；
2. `server/.env` 里配置 `SAND_MCP_DEV_TOKEN`（至少 16 位随机串，
   例如 `openssl rand -hex 24` 的输出，示例值请自行生成，勿用弱串）；
3. 客户端从本机回环地址发起，且不带 `X-Forwarded-For`/`X-Real-IP`/`Forwarded` 头；
   若带 `Origin`，必须是 `localhost`/`127.0.0.1`/`[::1]` 之一。

以上任一不满足，端点按 **404** 处理——不向外透露「这里有 MCP」。

## 启动

```bash
cd server
php start.php start -d        # 监听地址见 config/process.php
```

端口由 `SANDADMIN_SERVER_PORT` 控制，文档示例用 `9653`，可自行改成任意空闲端口。

端点地址：`POST http://127.0.0.1:9653/api/admin/mcp`

## 调用方式

本端点实现的是 MCP 无状态修订版（protocolVersion `2026-07-28`），
由 `mcp/sdk` 0.9.0 的 `StatelessHttpTransport` 提供。与有状态版本的区别：

- **没有 initialize 握手**，每个请求自带全部上下文；
- **必带 HTTP 头**（除凭据外）：
  - `MCP-Protocol-Version: 2026-07-28`
  - `Mcp-Method: <JSON-RPC 方法名>`（如 `tools/list`、`tools/call`）
  - `Mcp-Name: <工具名>`（仅 `tools/call` 需要）
  - `Authorization: Bearer <SAND_MCP_DEV_TOKEN>`
  - `Accept: application/json`（SDK 只接受 POST JSON-RPC）
- **每个请求 `params._meta` 必带两个键**：
  `io.modelcontextprotocol/protocolVersion` 与
  `io.modelcontextprotocol/clientCapabilities`。

工具列表示例：

```http
POST /api/admin/mcp
Authorization: Bearer <令牌>
Content-Type: application/json
Accept: application/json
MCP-Protocol-Version: 2026-07-28
Mcp-Method: tools/list

{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{"_meta":{
  "io.modelcontextprotocol/protocolVersion":"2026-07-28",
  "io.modelcontextprotocol/clientCapabilities":{}}}}
```

## 四个工具

| 工具 | 档位 | 说明 |
| --- | --- | --- |
| `dev_inspect_routes` | read | 读当前路由表（方法/路径/回调）与契约文件白名单内容 |
| `dev_recent_errors` | read | 摘读 `server/runtime/logs` 最新日志中的错误与慢请求行（已脱敏） |
| `dev_run_phpunit` | write | 跑白名单 PHPUnit 目标（超时 120s） |
| `dev_run_pre_merge` | write | 跑 `tools/pre-merge.sh --fast`（超时 180s） |

write 档工具按契约必须带 `idempotency_key`（≤255 字符），可另带
`dry_run: true` 只做参数校验不执行。

以下示例省略公共 HTTP 头；`params._meta` 只在首个示例写出，
其余调用同样要带（内容与 tools/list 示例相同）。`tools/call`
还要带 `Mcp-Name: <工具名>` 头。

### dev_inspect_routes

```json
{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientCapabilities":{}},
  "name":"dev_inspect_routes","arguments":{}}}
```

返回 `routes`（数组：methods/path/callback）与 `contract`
（白名单契约文件的存在性与内容，超限截断标记 `truncated`）。

### dev_recent_errors

```json
{"jsonrpc":"2.0","id":4,"method":"tools/call","params":{
  "name":"dev_recent_errors","arguments":{"kind":"error","lines":50}}}
```

`kind`：`error`/`slow`/`all`；`lines`：1–200。命中行经脱敏处理
（密钥键值、Bearer、长十六进制、手机号、邮箱一律打码）。

### dev_run_phpunit

```json
{"jsonrpc":"2.0","id":5,"method":"tools/call","params":{
  "name":"dev_run_phpunit","arguments":{
    "target":"tests/Unit/Infra/DevBuildTest.php",
    "idempotency_key":"run-2026-11-17-01"}}}
```

`target` 白名单：`tests/` 下真实存在的 `.php` 相对路径，或对应的
`Sandadmin\Tests\*` 全限定类名（内部映射回同路径校验）。返回
`passed`/`exit_code`/`output`（脱敏截尾）/`duration_ms`/`timed_out`。

### dev_run_pre_merge

```json
{"jsonrpc":"2.0","id":6,"method":"tools/call","params":{
  "name":"dev_run_pre_merge","arguments":{"idempotency_key":"gate-01"}}}
```

命令是固定 argv `bash tools/pre-merge.sh --fast`，不拼任何调用方参数。

## 安全边界

- **只进不出**：进程只绑回环来源校验，转发头出现即拒，不对公网开放；
- **Origin 白名单**：浏览器侧仅 localhost/127.0.0.1/[::1]，不带 Origin 的
  非浏览器客户端放行；
- **凭据能力封顶**：开发令牌权限 = 当前开发账号（['*'] 语义为「不额外加权限」），
  不引入特权后门；正式机器令牌（sand_system_api_token）本期未实现；
- **命令白名单**：执行类工具 argv 固定前缀 + 校验过的相对路径，
  `proc_open` 数组形式不过 shell，注入字符串一律 `MCP_TARGET_REJECTED`；
- **超时**：phpunit 120s、pre-merge 180s（`config/mcp.php` 可调）；
- **高危写不开放**：`high_risk` 档工具本期永不执行，只返回待确认请求形状
  （pending/approved/rejected/expired 状态机占位）；
- **审计**：每次调用记 `McpCall`（工具名、档位、幂等键、参数 sha256 摘要、
  结果、耗时、IP），参数原文与令牌不落审计；
- **限流**：每凭据 60 次/分 + 每工具 30 次/分（固定窗口，`config/mcp.php` 可调）；
- **生产剔除**：`.sand-dev-build` 不在发布制品中，端点不注册；
  生产环境配置 `SAND_MCP_DEV_TOKEN` 会被启动门禁直接拒绝。

## 骨架后续扩展点

- `Domain/Mcp/ToolSpec` + `ToolCatalog` 是显式登记表：工具名 → v1 路由 +
  权限码 + 三档。OpenAPI `x-mcp` 构建期生成注册表落地后，此处改挂生成的表；
- `CredentialVerifier`/`RateLimiter`/`AuditSink` 是端口接口，正式机器令牌、
  共享存储限流、库表审计各自换实现，内核 `McpKernel` 不动；
- 不做代码生成工具。
