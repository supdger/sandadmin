> 草稿：待 maintainer 审阅后作为 0.2.4 Release 正文。

## 0.2.4（发布日期待定）

> **安全提示**：本版本对应安全公告 [GHSA-vq7f-m265-jf3w](https://github.com/supdger/sandadmin/security/advisories/GHSA-vq7f-m265-jf3w)（Critical，根因公告见上游 [GHSA-pr9j-xpm5-jprg](https://github.com/supdger/sand-core/security/advisories/GHSA-pr9j-xpm5-jprg)）。宿主 0.1.0–0.2.3 全部受影响：安装时 webman 插件机制会复制 tinywan/jwt 自带的配置文件，其中 access 与 refresh 签名密钥是写死的公开默认值（历史 HMAC 默认值短于 32 字节）；0.2.8 之前的 sand-core 不生成、也不检查这些密钥。未手动更换密钥的部署，任何人都可以用这些公开默认值伪造超级管理员令牌接管后台。`__JWT_ACCESS_SECRET_KEY__` / `__JWT_REFRESH_SECRET_KEY__` 占位符是上游后来引入的安装模板，不是本漏洞的根因。
>
> 本版本将 `supdger/sand-core` 下限提升至 `^0.2.10`、`supdger/sand-package` 下限提升至 `^0.2.26`（对应 [GHSA-g7rv-6hmp-476c](https://github.com/supdger/sand-package/security/advisories/GHSA-g7rv-6hmp-476c) 网页终端修复与安装路由收口）。sand-core 自 0.2.8 起在启动引导、登录与鉴权中间件执行 JWT 密钥自检（`JwtSecretGuard`），拒绝空密钥、`__JWT_*__` 占位符与短于 32 字节的密钥，历史 tinywan 短默认值在这一档已被拒；0.2.10 再把已知公开默认密钥（含上游测试/历史 RSA 密钥与误提交的较长密钥）按哈希列入黑名单。注意：0.2.9 及更早版本不覆盖全部已公开默认值——若部署所用密钥是长度超过 32 字节的已公开值，旧版检查不会拦截，仍需按本公告轮换密钥。
>
> **升级后必须更换 JWT 密钥**：编辑 `server/config/plugin/tinywan/jwt/app.php`，为 `jwt.access_secret_key` 与 `jwt.refresh_secret_key` 各设置独立的随机值（至少 32 字节，分别执行 `php -r "echo bin2hex(random_bytes(32));"` 生成两个不同值），确认文件对服务进程可读后重启服务。更换密钥后此前签发的令牌全部失效，所有用户需重新登录。
>
> **升级后必须排查**：检查后台访问日志（`server/runtime/logs/` 及前置代理访问日志）是否存在异常登录或异常接口调用；无法排除已被利用时，按主机已被入侵处理——更换该主机全部相关密钥与口令并排查服务器；同一台主机上其他仍在使用默认密钥的 webman 应用一并更换密钥。

### 变更摘要

- 安全加固
  - `supdger/sand-core` 下限 `^0.2.4` → `^0.2.10`，`supdger/sand-package` 下限 `^0.2.4` → `^0.2.26`。
  - 宿主 debug 默认关闭，生产环境拒绝开启；后台移除 HTTP 终端入口并加执行函数门控。
  - 新增本机开发一键登录：`SAND_DEV_LOGIN` 环境变量与回环对端 IP 双门控，未开启时返回与路由不存在逐字节一致的 404。
  - 新增依赖审计工作流：`composer audit` 锁定依赖、Workerman/Webman 必须使用发布 tag、禁止提交 vendor 目录。
  - 宿主配置移除默认数据库凭据；macOS 下未显式设置时默认 `PGGSSENCMODE=disable`，修复 libpq GSSAPI 协商与 worker fork 叠加导致的 SIGSEGV。
  - 修改密码后吊销该账号全部会话并换发新令牌。
- 1.x 宿主能力（`server/src` 新模块层，走 `/api/admin/v1`）
  - 公告、生命周期详情、选项组、组织域（用户/部门/角色/岗位）、通知、菜单、工作台、个人域、插件包等接口落地。
  - 请求级上下文、鉴权中间件与统一错误信封；DBAL 仓储基座统一分页、排序与过滤查询。
  - 插件扩展注册表：清单定义、库内覆盖与用户生命周期回调；本地 Flysystem 上传与 `file_usage` 登记；后台任务 worker 与轮询接口、`task_run` 基线。
  - 并发与幂等：组织创建幂等键落库；部门编辑与菜单改名按 `base_version` 乐观锁拦截并发冲突；选项组被插件或业务引用时禁止删除（新增 `dict_item_ref` 引用追踪）。
  - 卸载插件且选择保留数据时停用其定时任务与扩展覆盖（`suspend_time`），重新安装后恢复。
- 前端
  - `sandadmin-artd` 管理端交互收口：操作列与确认弹窗统一、空状态与只读文案、状态标签统一为「已停用」、附件删除提示带文件名、侧栏菜单跟随改名与显隐、系统更新页区分「环境不支持 / 检查详情隐藏」两态、升级进度未达终态时封顶 90%、开发页面以 `dev=1` 参数门控等；补 ESLint 9 flat 配置并修复 vue-tsc 报错。
  - 新增 `web/` 目录：SandAdmin 1.x 管理前端（React 19 + TypeScript + Vite + Semi Design + Tailwind，内置 MSW mock 可脱离后端独立开发）。该工程为开发中预览，本版本的安装与发布仍以 `sandadmin-artd` 为准。
- 插件市场目录
  - 跟进发布 SandIAM 至 0.9.5、SandAI 至 0.4.8、sandworkflow 1.0.8、SandLicense 0.1.2 等版本。
- 工程治理
  - 新增依赖审计 CI、AGENTS.md、发布验收清单（`RELEASE-CHECKLIST.md`）与 `tests/` 宿主验证脚本；require-dev 引入 PHPUnit 与 PHPStan；移除 openspec 过程目录；LICENSE/NOTICE 补齐双版权行。

### 升级说明

- 依赖锁定：`server/composer.json` 约束为 `supdger/sand-core ^0.2.10`、`supdger/sand-package ^0.2.26`。上游 0.2.10 / 0.2.26 发布后在 `server/` 执行 `composer update supdger/sand-core supdger/sand-package` 更新 `composer.lock`，并按下方前端步骤重建载荷；在此之前 lock 仍停留在旧版本，`composer install` 会提示 lock 与 composer.json 不同步。
  - 当前 `server/composer.lock` 实锁 sand-core 0.2.9、sand-package 0.2.24、tinywan/jwt 1.15.0，发版前须随上游发版更新到 0.2.10 / 0.2.26 或以上。
  - 已核实：`sandadmin-artd/.sand-package-source-manifest.json` 记录的受管前端载荷版本为 0.2.25（27 个受管文件 sha256 与仓内文件全部一致），高于当前 lock 锁定的 0.2.24——PHP 包与前端载荷版本不一致是真实状态，待 sand-package 0.2.26 锁定并重新发布载荷后消除。
- 数据库：本仓含 `server/database/migrations/` 下 0004–0007 四个宿主迁移文件（均标注 release_version 1.1.0；0004–0006 兼容标记 compatible，0007 为 breaking——列改名并把字节列换算进 MB/GB 新列）。基线迁移与迁移执行器均不在本仓（仓内仅集成测试手动执行 0004）；用到 1.x（`server/src`）模块的既有部署需要执行这些迁移，执行方式待 maintainer 确认，升级前请先备份数据库。
  - 沿用既有规则：从低于 Sand Core 0.2.7 升级的实例（含自 0.2.3 升级，其锁定 Core 0.2.4）仍须在目标库手动执行一次幂等的 `server/plugin/sandadmin/db/update-0.2.7.pgsql`（该文件随 sand-core 包发布，已核实存在），并立即修改仍在使用的旧默认口令（详见 README）。
- 前端：更新 `composer.lock` 后需重新构建并发布 `sandadmin-artd` 管理端（`pnpm install --frozen-lockfile` 与 `pnpm build`）；`pnpm install --frozen-lockfile` 已在 pnpm 11.19.0 下验证通过，`pnpm build` 验收结论待 maintainer 补齐。源码随包发布、以仓库内锁定载荷为准。

当前 `composer.lock` 锁定 Core 0.2.9@3dd46605576ab74d9890cf04ce2cba2659e0f2a0、Package 0.2.24@6fba46c2656440b1494ac0581e50f8244176b870，低于新约束下限，待上游发版后定向更新两包并回填本条哈希。受管前端文件与包内摘要一致性已核实（27/27 匹配）。

源码下载沿用 GitHub Source code ZIP/tar.gz；新实例按 README 正常 composer install。此发布不自动部署或升级已有宿主。
