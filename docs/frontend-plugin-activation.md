# 插件管理端载荷激活契约

当前 `saipackage-pg-v1` 实现沿用上游文件部署和依赖处理；下述自动激活动作仍是待实现契约，
不能据此报告前端已激活。源码复制后须完成实际构建和页面访问验证。
实现边界见[上游 PostgreSQL 适配](architecture/SAIPACKAGE_POSTGRESQL_ADAPTATION.md)。

本契约适用于所有通过 SandPackage 携带
`sandadmin-artd/src/views/plugin/<app>/` 管理端载荷的插件。该目录是宿主前端
工程的**源码输入**，不是生产静态产物。源码复制完成、插件生命周期完成和
生产管理端页面生效是三个不同结果。

## 状态模型

宿主对外状态必须依次区分：

`source_deployed → dependencies_ready → build_succeeded → activated → verified`

- `source_deployed`：ZIP 身份已校验，源码已进入宿主前端工程。
- `dependencies_ready`：宿主 lockfile 对应的依赖已就绪。
- `build_succeeded`：在私有暂存目录完成类型检查和 Vite 构建，产物清单可校验。
- `activated`：经宿主级互斥锁原子切换到生产静态目录，旧产物仍可恢复。
- `verified`：静态服务读到新产物，重新登录后插件菜单和至少一个页面入口通过。

`state=installed` 只表示插件生命周期已经完成，不能代替上述任一前端状态。
开发模式可由 Vite 直接加载源码，但必须报告 `delivery_mode=development-source`，
不得据此宣称生产静态产物已激活。

## 身份和最终结果

每次激活记录至少绑定：

- SandAdmin revision、SandPackage 版本和插件 `app/version`；
- 插件 ZIP SHA-256 与前端源码逐文件摘要；
- `pnpm-lock.yaml` SHA-256；
- Node、Corepack、pnpm 和 Vite 的实际版本；
- 固定命令、开始/结束时间、退出码和有界日志编号；
- 暂存产物逐文件摘要、激活后的产物摘要；
- 静态服务生效状态、缓存失效方式和验证 URL。

机器可读成功必须同时满足 `build_status=succeeded`、
`activation_status=activated`、暂存与激活产物摘要一致；真实页面通过后才可写
`verification_status=verified`。任一字段缺失均不得显示“管理端已发布”。

## 唯一执行入口

SandPackage 应提供独立的 `frontend activate` UI/API/CLI 动作。它不能复用
`web-install` 的生命周期完成回调，也不能把现有 `web-build` 配置直接加入
`TerminalRunner` 白名单后标记 installed。

执行器固定在 `sandadmin-artd/` 工作：

1. 校验宿主 revision、插件身份、源码摘要和宿主 `pnpm-lock.yaml`。
2. 使用宿主声明的 Node/Corepack/pnpm 版本；生产默认
   `corepack pnpm install --frozen-lockfile`，不得使用插件自带 lockfile。
3. 在宿主级全局前端构建锁下执行 `corepack pnpm exec vue-tsc --noEmit`。
4. 使用 `corepack pnpm exec vite build --outDir <private-stage> --emptyOutDir`
   构建到私有暂存目录，不直接清空或覆盖当前生产 `dist/`。
5. 校验暂存产物存在、只含普通文件且清单摘要稳定，再写入恢复 journal。
6. 备份旧产物、原子切换暂存产物、复核摘要，并按部署环境执行明确的缓存失效
   或静态服务发布动作。
7. 写入 `activated` 结果；真实 URL 验收另行推进到 `verified`。

依赖安装和构建是否允许联网由部署环境显式决定。缺少锁定工具链、网络权限或
缓存时必须失败关闭，不能自动改 registry、放宽 frozen lockfile 或猜测命令。

## 失败与恢复

构建执行器必须限制总运行时间、输出字节数和输出帧数，隔离进程组，并在超时、
客户端断开或异常时终止且确认回收全部子进程。稳定错误至少区分：

- `FRONTEND_DEPENDENCY_FAILED`
- `FRONTEND_BUILD_FAILED`
- `FRONTEND_BUILD_TIMEOUT`
- `FRONTEND_ARTIFACT_INVALID`
- `FRONTEND_ACTIVATION_FAILED`
- `FRONTEND_ACTIVATION_UNKNOWN`
- `FRONTEND_VERIFICATION_FAILED`

依赖或构建失败不得触碰当前生产产物。切换失败必须依据 journal 恢复旧产物；
若无法证明当前是旧产物还是新产物，状态必须为 `activation_unknown`，停止自动
重试并要求人工核验。重复请求只有在宿主 revision、插件/源码、lockfile、工具链
和产物身份全部相同时才可返回既有成功结果。

插件升级不能删除仍在服务的旧源码或旧产物恢复证据；卸载也必须通过同一全局锁
构建并激活“不含该插件”的新产物后，才能宣称管理端载荷已移除。

## 普通开发者流程

1. 校验插件 ZIP、签名和 manifest，在插件市场上传完整 ZIP。
2. 完成安装或升级，确认后端生命周期结果。
3. 查看前端状态；若仅为 `source_deployed`，调用 SandPackage 的独立
   `frontend activate` 入口。
4. 核对工具链、lockfile、构建及产物摘要和激活状态。
5. 重新登录，检查插件菜单、总览与一个页面 URL，再记录 `verified`。
6. 失败时按错误码使用恢复入口；不要手工复制目录、直接运行猜测的 shell 命令、
   编辑 SandPackage 状态或重跑生命周期 SQL。

## 当前实现边界

现有 `config/terminal.php` 中的 `web-build` 只是历史配置，不是已发布的安全激活
入口；`terminal.vue` 暴露的 `web-install` 只处理前端依赖。完成独立执行器、
恢复 journal、状态 API/UI 和隔离宿主故障注入验收前，生产前端激活能力仍为
`contract_defined_implementation_pending`。
