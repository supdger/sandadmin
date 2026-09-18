## Context

见 proposal.md。当前锁定 SaiPackage 6.0.2，SandPackage 已有 PostgreSQL 安装/升级确认/失败恢复。清单来源固定在服务端，公开仓库为 supdger/sandadmin；现有业务插件仍在独立工作区，本次不迁移或改变其权威来源。

## Goals / Non-Goals

**Goals:** 仓库清单与本机状态 → 版本 ZIP → 校验 → 一次确认后既有安装生命周期；管理页处理卸载、依赖和恢复。提供维护者单插件打包命令和清单维护说明。

**Non-Goals:** 账号订单平台、任意第三方URL安装、私有仓库token、新迁移引擎、自动依赖插件安装、实际业务插件搬迁、对外发布。

## Decisions

- plugins/catalog.json schema=1，plugins数组包含app/title/about/author/versions；版本包含version/tag/asset/sha256/host_min/可选host_max/notes。服务器只由这些标识构造仓库和Release地址，不接受客户端URL。
- 默认仓库supdger/sandadmin、ref=main，可由服务端环境配置修改；无密钥。清单初始为空，404显式报错。
- 下载提交app/version/sha256，服务器重新读取清单，拒绝变更和不兼容版本。ZIP info.ini在交现有InstallLogic前校验；下载只准备候选，不执行SQL。
- 网络传输封装GithubRepositoryClient，使用curl_multi和Workerman Timer非阻塞轮询；连接5秒，总60秒，限制重定向、域名和字节数，每Worker并发2。清单最大1MiB，ZIP最大5MiB，解压继续使用现有64MiB/2048条目限制。失败关闭并清理资源。
- 界面替换在线商店区域，移除账号购买；保留本地安装及恢复区域。复用hideGlobalPluginWrites、加载/空/失败状态，仓库操作一次确认后串联下载与现有安装请求，不要求用户切页再点安装；失败或待处理时引导插件管理。Astra/medium独立设计已核对。
- 单插件打包命令从plugins/<app>或显式源码目录读取，排除无关文件的方式采用明确载荷白名单，生成ZIP和目录条目；不依赖构建市场平台。

## Risks / Trade-offs

- GitHub网络不可达 → 明确错误，保留本地ZIP上传入口。
- 清单与附件发布不同步 → 校验失败关闭，不使用latest模糊地址。
- 旧sand_platform包不兼容 → 保留现有拒绝，文档说明先完成插件兼容适配。
- 同仓不代表自动安装 → plugins仅发布源码，实际运行目录由安装器部署。
- ZIP校验解压仍是既有同步文件操作 → 网络非阻塞且包5MiB限额；本轮不声称生产容量或真实数据库验收。

## Migration Plan

更新代码和清单后由维护者发布ZIP及清单，消费者选择配置的ref。回滚代码恢复旧入口不会改变已安装记录。本轮不推送、不重启、不写数据库；使用临时目录和记录型数据库替身验证安装衔接。

## Runtime dependency contract correction

插件方确认SandIAM的plugin/sand-iam/vendor属于正式SAML运行载荷。通用builder支持根release-build-contract.json内kind=reviewed-runtime-payload-inputs及generated_payloads精确plugin/<app>/vendor条目，复用file_count/tree_sha256字段；树摘要为依赖目录内部相对文件路径到SHA-256的映射，以SORT_STRING排序、JSON_UNESCAPED_SLASHES编码后计算SHA-256。无声明仍拒绝vendor，其他声明不自动扩大载荷白名单。对源码与最终ZIP分别核对，保留5MiB/64MiB/2048和符号链接/敏感路径拒绝。

这只证明所声明运行依赖的完整性，不证明契约自身经过授权审查，也不替代插件已有工具链/源码快照/SDK/门户/签名发布契约。SandIAM正式发行继续使用插件侧正式builder，宿主消费符合安装结构的既有ZIP。

## 状态感知、直接安装和文档

- 用户新要求在既有变更内实现。catalog插件local包含state/version/installed_version/blocked/reason，版本action区分install、upgrade、installed、downgrade、manage、incompatible。读取真实运行目录与安装记录，复用普通生命周期只读预检；记录为已安装但文件缺失时不得显示可升级。
- index展示真实状态并保留旧恢复字段。现有待安装候选、依赖或异常状态引导插件管理，不覆盖候选。用户当前0.6.0记录对应后端目录缺失，保留其记录和数据库，不自动恢复或重装。
- 前端刷新状态后确认，串联download与现有install接口；安装仍在普通HTTP请求上下文中执行，不在异步下载Timer里调用SQL。升级确认源/目标与候选一致，链路全程防重；刷新看到真实state1才称成功。
- 文档GET仅接受app/version/sha256，从同一受信Release下载并核验ZIP，只读根README.md，256KiB文本上限。不解压到插件目录，不暂存候选，不执行HTML或加载远程图片；前端插值显示Markdown原文，独立loading/error/empty及竞态保护。
