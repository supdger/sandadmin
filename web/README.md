# SandAdmin Web

SandAdmin 1.x 的管理后台前端：React 19 + TypeScript + Vite + Semi Design + Tailwind CSS（仅布局）+ pnpm。

本目录是独立的前端应用，可脱离后端单独开发——内置 MSW mock，无后端时界面也能完整跑通。

## 环境要求

- Node.js 22.12 或更高（或 20.19+）
- pnpm 10+（开发使用 pnpm 12；`packageManager` 字段已锁定，Corepack 可自动切换）

## 快速开始

```bash
pnpm install
pnpm dev        # 开发服务器，默认启用 MSW mock
```

常用命令：

| 命令 | 作用 |
| --- | --- |
| `pnpm dev` | 启动开发服务器（HMR） |
| `pnpm build` | 产出生产构建到 `dist/` |
| `pnpm preview` | 本地预览生产构建 |
| `pnpm typecheck` | `tsc --noEmit` 类型检查 |
| `pnpm lint` | ESLint 检查 |
| `pnpm test` | Vitest 单元测试（jsdom + MSW） |

## 环境变量

复制 `.env.example` 为 `.env.local` 后按需修改。所有变量都有默认值，可以不配置。

| 变量 | 说明 | 默认值 |
| --- | --- | --- |
| `VITE_API_BASE_URL` | API 请求前缀。后端同域时用路径前缀（经反向代理转发），跨域时填完整地址 | `/api` |
| `VITE_APP_NAME` | 浏览器标签页与外壳标题 | `SandAdmin` |
| `VITE_APP_LOGO` | 外壳头部 Logo 图片地址，留空则只显示名称 | （空） |
| `VITE_APP_COPYRIGHT` | 页脚版权文案 | `© 2026 青岛沙盒创意网络科技有限公司` |
| `VITE_DEFAULT_LOCALE` | 界面语言，1.0 仅提供 `zh-CN` | `zh-CN` |
| `VITE_ENABLE_MOCKS` | 是否启动 MSW mock（`true`/`false`） | 开发 `true`，构建 `false` |

注意：`.env`、`.env.local` 等真实环境文件不会入库，仓库只保留 `.env.example`。

## Mock（MSW）

`src/mocks/` 提供各域最小可用的请求桩，`pnpm dev` 时自动启动浏览器 Service Worker，无需真实后端：

- 登录：`admin` / `Admin@123456`；`newbie` / `Admin@123456` 返回「必须修改密码」；同一账号连续失败 3 次后要求验证码（点选验证码为占位实现，校验宽松）。
- 安装：默认返回未安装；在 `/install/status` 请求上加 `?mocks=installed`，或设置 localStorage `mock.installed=1`，可切到「已安装」状态。
- 任务进度：`GET /api/jobs/{id}` 每次轮询推进 25%，直至成功。

`VITE_ENABLE_MOCKS=false` 或生产构建下不启动 mock，请求按 `VITE_API_BASE_URL` 直达真实后端。

单元测试统一使用 `src/mocks/server.ts`（Node 侧 `setupServer`），在测试里用 `server.use(...)` 追加临时 handler 即可。

## 目录约定

- `src/api/`：按域划分的接口函数，统一走 `src/lib/apiClient.ts`（统一 `{code,message,data}` 壳、`Authorization` 头、业务码非 200 抛 `ApiError`）。
- `src/routes/`：路由聚合。`src/routes/modules/<area>.tsx` 默认导出 `RouteObject[]`；`handle.public: true` 为免登录裸路由，其余自动挂到鉴权守卫 + 外壳布局下。
- `src/components/`：共享组件（PageHeader、Empty、ErrorState、Loading、ListPage）。
- `src/i18n/`：i18next；`locales/<locale>/<ns>.ts` 的文件名即命名空间，UI 文案一律走 `t()`。
- `src/mocks/`：MSW handler 与 worker/server 入口。

## 部署

`pnpm build` 产出静态文件到 `dist/`。这是 SPA：

1. 用任意静态服务器/Nginx 托管 `dist/`；
2. 配置 history fallback——所有未命中静态资源的请求回退到 `index.html`；
3. 将 `VITE_API_BASE_URL` 指向的 `/api`（或你配置的前缀）反向代理到后端服务；前后端分离部署时把 `VITE_API_BASE_URL` 设为后端完整地址并自行处理 CORS。
