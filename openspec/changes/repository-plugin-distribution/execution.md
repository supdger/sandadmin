# 执行记录

日期：2026-09-18。工作区 `/Users/code/project/sandadmin`，保留现有 `codex/sandadmin-rename` 分支；开始时工作树干净。本次未提交、推送、发布或同步既有宿主。初始阶段未执行真实数据库/服务；用户后续明确授权独立验收，进展见末节。

## 已实现

- 替换 SaiThink 商店代理及前端账号购买流程，以公开 GitHub 仓库清单/Release ZIP 分发。
- 服务器仓库配置、严格清单解析、版本和宿主兼容判断、SHA-256及ZIP身份校验、既有候选/安装升级/恢复锁衔接。
- cURL multi + Timer有界异步下载，HTTPS/固定域名/重定向/大小/超时及8个总请求/2个活动请求限制；不携带token。
- 控制器保留超级管理员约束、下载仅POST，使用chunked JSON与Webman中间件衔接；HTTP1.0在操作前普通505拒绝，1.1 close在末块后关闭。
- 单插件构建命令、初始空清单、使用及发布说明；仓库规则允许独立迁移完成后的同仓源包，当前三个业务插件权威来源未切换。

## 验证

- `php server/tests/SandPackage/RepositoryDistributionTest.php`：通过。模拟HTTP传输、真实ZIP/临时文件、真实安装器部署，SQL用记录型连接；涵盖安装、升级、错误包/摘要/身份/兼容、重复候选、降级和恢复状态。没有真实数据库连接或重启。
- `php server/tests/SandPackage/RepositoryHttpResponseTest.php`：12项通过。真实Response/Chunk编码、模拟连接和Timer，覆盖管理员限制、响应顺序、JSON成功失败、1.0拒绝/1.1关闭、断连及非法选择；不是监听端口上的真实HTTP验收。
- `php server/tests/SandPackage/GithubRepositoryClientContractTest.php`：通过。含本地拒绝行为及明确标记static-rule的源码安全约束，不以此替代真实网络重定向验证。
- `php server/tests/SandPackage/RepositoryPackageBuildTest.php`：通过。真实ZIP/摘要、静态版本读取且不执行插件PHP、错误版本、旧扩展、符号链接、输出保护。
- `php server/tests/SandPackage/UpstreamPostgresLifecycleTest.php`：既有无DB生命周期回归通过。
- 改动PHP语法、前端ESLint/Stylelint/Prettier、git diff --check：通过。
- `pnpm exec vue-tsc --noEmit -p src/views/plugin/sandpackage/install/failed-upgrade-recovery.tsconfig.json`：通过，包含实际index.vue与api/index.ts。
- `pnpm exec vite build`：通过，3065 modules。完整`pnpm run build`受既有`failed-upgrade-recovery.vite.config.mts(2,17)`在moduleResolution=node下解析`@vitejs/plugin-vue`失败影响；临时bundler验证另暴露两处既有wang-editor声明问题，未修改全仓配置。
- 真实GitHub只读：新Client的CLI驱动读取`supdger/sandadmin/main/README.md`成功（3139字节）；读取`plugins/catalog.json`为HTTP404。说明连接可用，但清单尚未公开发布；未验证真实Release ZIP链路。
- 独立Astra/medium审查发现清单前端15秒超时不足，已改65000ms并复核无must-fix；下载135000ms。新增连接关闭测试独立复跑通过。

## 尚未验证/发布

浏览器连接成功，但无既有标签；3006进程实际属于Brain6，其他PHP为sand_plugins演示宿主或mdmall。没有当前SandAdmin候选前端，未进行真实UI验收，未擅自启服务。真实GitHub插件附件下载、真实PostgreSQL安装升级、前端生产发布和业务链均未验收。

初始plugins/catalog.json为空。尚未发布到GitHub，也未发布真实插件ZIP。不能以临时中立包通过描述三个业务插件已经可在线安装。

## sand_plugins配合

已向现有任务`01a099f1-08ad-7c73-ad70-da84c7481331`发送契约与有界检查要求，保留其原目标与长稳计时、不迁仓/发布/同步/改数据库。本任务补充只读现状及临时打包结果：

- SandIAM 0.7.3：`plugin/sand-iam/vendor`被发布工具拒绝；需插件方判定运行依赖和正式发布排除策略，不能机械删除。
- SandWorkflow 1.0.7：缺根LICENSE；需保留真实版权来源。
- SandAI 0.1.0：非空sand_platform扩展，与当前普通安装入口不兼容；不可删元数据掩盖依赖。

未写入上述插件源码。已回传具体差异，尚未收到可读的配合检查结果。

## 模型记录

主控脚本证实gpt-6-astra/low/openai。子任务astramedium__repository_ui_design与astramedium__repository_review请求并实际为gpt-6-astra/medium/openai；solmedium__github_transport、solmedium__repository_ui、solmedium__plugin_packager请求并实际为gpt-5.6-sol/medium/openai。/usr/bin/python3受Xcode许可阻断，使用已提供的bundled Python成功读取同一模型记录脚本。

## 插件侧回传后的兼容修正

插件侧已回传三个插件结论：SandIAM本地vendor属于正式SAML运行载荷，不能删除；SandWorkflow缺LICENSE由插件侧修复；SandAI旧sand_platform是真实依赖，不清空或放宽宿主拒绝。先前将所有vendor视为不应发布目录的判断已纠正。

现场只读计算SandIAM vendor：58个文件、604800字节，树摘要`e35c67c2bff009a454b03d96bd182ec7fdec01948740b2d5208c44af5ffa934a`，与release-build-contract.json一致。通用builder按此通用声明形式增加受控支持，不硬编码SandIAM名称。

插件侧提供v38正式候选：`/Users/code/project/sand_plugins/.artifacts/sand-iam-0.7.3-v38-20260918T045858Z/sand-iam-0.7.3-v38-20260918T045858Z-release-unsigned.zip`，SHA-256=`3f8d5e2cabc05eb3cfd42f1207e4a1e0cbf9e7522a07027ff63acbf8297f24cf`。本任务现场重新核对摘要，并将base/runtime指向新建临时目录，调用真实InstallLogic::uploadFromPath：返回sand-iam/0.7.3/state=2/saipackage-pg-v1，vendor/autoload.php已暂存，runtime_deployed=false。没有调用install、SQL或服务操作，临时目录已清理。

此证据证明已有正式ZIP可通过当前普通安装候选预检，不需要先由通用builder重打包。插件侧报告已通过外部Ed25519 attestation，但本任务没有重验签；文件名unsigned不等于缺少外部签名。真实宿主部署、portal/SDK用途、PostgreSQL生命周期和业务链仍未验证，GitHub正式发布状态未确认。

受控runtime vendor修复已完成：源码及closed ZIP分别校验精确目录数量/树摘要，ZIP内契约声明与读取时一致；未声明、内容篡改、增删文件、数量/摘要不符、nested vendor、.env/.git/node_modules和符号链接均拒绝。主控及独立Astra重跑RepositoryPackageBuildTest通过，独立审查无must-fix。addFile到close之间的并发漂移仅有最终ZIP检查的源码证据，未注入竞态，不宣称竞态复现通过。本轮未修改安装器，不重跑无关前端或数据库检查。

## 授权后的独立落地验收

用户明确回复“授权”，覆盖专用数据库初始化、SandIAM安装及本次服务启动/重载。2026-09-18创建`sandadmin_repository_acceptance_20260918`成功，导入SandAdmin基础SQL成功，86条菜单。隔离宿主在当前任务`work/repository-acceptance/host`，后端127.0.0.1:18918、Channel22118、前端31918已启动，未修改既有数据库和其他实例。

完整`pnpm run build`已通过（vue-tsc + Vite，3065 modules）：仅从app tsconfig排除测试专用failed-upgrade-recovery.vite.config.mts，未改变全局moduleResolution。上文完整构建阻塞已解除。真实页面及SandIAM安装正在独立验收；未宣称GitHub在线链路通过。

### 真实安装与接口验收结果

- 标准初始化补齐vendor/topthink/think-orm/src/db/connector/pgsql12.sql后登录成功。此次缺函数是独立验收初始化脚本遗漏，正式InstallController已有该步骤；脚本已补齐。
- 修复Captcha.php五处旧saithink配置路径为实际sandadmin.captcha路径；原仓和隔离候选一致，PHP语法及独立审查通过。配置cache模式在真实请求中生效。
- 正式v38 ZIP摘要复核后，通过CLI调用真实InstallLogic::uploadFromPath及install(false)，state由2到1，86张sand_iam_业务表落库，SandIAM菜单存在，已重载本实例。不是浏览器点击安装证据。
- 经真实验证码和admin密码登录业务200；用户、菜单、安装列表、SandIAM应用及组织列表接口均HTTP200/业务200；前端/api代理菜单业务200。
- 真实未登录仓库请求业务401，GET下载404；已登录目录请求返回chunked JSON，业务400/远端HTTP404。未发布清单导致在线下载不可用，不能计下载成功。
- 安装SandIAM后的前端Vite构建通过，14.93s；不等同完整业务验收。
- Astra/medium独立读取安装/登录/接口/构建记录并检查Captcha修复，无must-fix。独立浏览器操作被request-header policy加载故障阻塞，未绕过工具策略。
- 独立实例保留供用户访问：127.0.0.1:31918。本次未执行真实升级、GitHub发布、源码提交/推送或既有宿主部署。待发布清单及保持原名的ZIP已在任务outputs/plugin-release-preview整理，未写入已发布目录。

## 已授权预发布与用户实例404修复

用户再次明确授权在线预发布。源码提交3a80b25已推送supdger/sandadmin的codex/sandadmin-rename；Release `sand-iam-v0.7.3-v38`已发布为prerelease，附件保持正式ZIP原名和摘要。清单提交416230a已推送codex/plugin-catalog-preview；main未修改。origin为SaiAdmin上游，首次推送被403拒绝未产生变更，随后向已确认的github远端成功推送。

用户截图localhost:3006的当前进程经lsof确认为当前sandadmin前端，代理8788；后端cwd也已确认。本实例及独立验收实例的SANDADMIN_PLUGIN_REF改为codex/plugin-catalog-preview并重载。真实经8788和3006/api访问仓库接口均HTTP200/业务200，返回SandIAM及预发布版本；原404已解除。

正式GithubRepositoryClient与RepositoryLogic从真实远端清单和Release附件下载、验证SHA256/ZIP身份并在新临时目录调用InstallLogic暂存成功，state=2，runtime_deployed=false，无数据库操作。独立宿主先前同摘要包真实安装state=1证据保持。浏览器点击及真实升级仍未验收，不将预发布宣称稳定版本。

## 状态感知与文档增量（用户后续要求）

用户要求仓库直接安装/升级、自动判断已安装、本地安装改插件管理、文档查看。原3006/8788实例IAM记录0.6.0/state1但运行目录缺失；旧index仅展示记录导致误报，本次保持记录与数据库不变，修正真实状态读取为7。

后端新增ordinaryStatus只读预检、catalog本机local及版本action、下载前动作拒绝和只读README接口。独立审查发现健康state2候选被误blocked，已修为普通管理操作允许、仓库仍manage避免重复下载；新增真实Controller::index临时目录测试。RepositoryDistribution及HttpResponse测试独立复跑通过。

真实HTTP复验：8788 local.state7/version0.6.0/blocked=true/action=manage、index.state7；18918 local.state1/installed_version0.7.3/blocked=false/action=installed。两边文档均返回6591字节正确README；重复下载分别缺文件拒绝/已安装同版本拒绝。安装info.ini前后SHA256相同，未执行插件生命周期SQL、安装或卸载；普通HTTP认证和系统操作日志按宿主正常机制运行。四个宿主后端文件同步至本任务隔离实例，两个后端已重载。

本轮浏览器工具最小复查仍报request-header policy加载失败；未改用脚本绕过，因此真实UI交互证据仍待完成。前端候选及独立验收进行中。

### 前端增量完成与复核

- 本地安装改插件管理，仓库展示本机状态及允许动作；一次确认串联刷新/候选准备/安装，核对身份和升级源版本，刷新确认state1才成功。文档抽屉以文本插值显示README，独立请求序号防串页。
- 独立审查P2发现本地HTTP未占用全页互斥，已将五类本地写请求、上传、恢复活动阶段及既有终端活动任务纳入共享忙碌状态，刷新后释放；上传成功等待父页刷新。再次独立审查无must-fix。
- 局部类型、ESLint、Stylelint、Prettier通过；最终主控重新执行完整pnpm run build通过（vue-tsc + Vite）。静态互斥断言不是浏览器并发证据。
- 冻结前端三文件同步至本任务隔离实例。真实3006 Vite返回的新模块包含插件管理、查看文档、直接安装及共享互斥；这仅证明代码已提供，不等同浏览器点击验收。
- 本轮未清理旧记录、执行插件安装/升级/卸载或发布新插件包。用户现有IAM 0.6.0缺运行文件状态未自动修复。真实一键点击和版本升级仍待独立浏览器/业务验收。
- 额外状态机脚本行为段通过，但末尾原有静态版本断言写死6.1.4、当前配置6.1.5而失败，本轮未改其版本断言。

## 用户截图纠偏

用户指出真实页面不可理解。截图及独立Browser真实DOM均复现：两个state7文件缺失插件被显示为数据库升级未完成，页首恢复大卡占用约440px，出现空来源版本箭头。根因为isFailedUpgradeRecovery将普通ordinary_actions_blocked=true直接等价为失败升级；此前源码复核未覆盖此分类与最终默认布局，不能以构建/HTTP通过表述UI完成。

本轮Browser连接已恢复，已取得修复前真实页面证据。修复将区分普通禁写与真实失败升级，并将恢复流程移入主动打开的单插件详情；待独立真实页面复验。

### 纠偏完成与真实页面验收

- 分类仅接受精确失败升级形状或显式恢复模式；主控执行真实导出函数11条断言通过，覆盖普通state5/6/7不误分类、四种恢复模式和全局保护保留。
- 仓库默认展示插件；管理页显示安装文件缺失和简短解释。单插件详情默认关闭，真正恢复按需打开；环境/仓库来源收起，删除默认页工程流程堆叠。
- 前端专项格式、lint、类型及完整构建通过；恢复行为测试入口构建通过。
- 独立Astra实际操作3006：仓库首屏、管理详情、README、版本弹窗、390×844窄屏通过，无新增must-fix；主控亲自查看实际截图。最终两处文案“安装文件缺失”“仓库版本”也已reload确认。
- 截图保存在当前任务outputs/plugin-repository-after-desktop.png、plugin-repository-after-narrow.png、plugin-management-detail-after.png。
- 未执行安装、升级、卸载或修复旧记录。真正失败升级恢复仅回归/源码证据，未页面演练；31918停在验证码登录，健康已装UI未覆盖。

## 持久存储修复（2026-09-21）

用户明确要求修复runtime扫描职责并通知sand_plugins。主控核实现存实现以runtime/sandpackage登记枚举；实际源码实例仅有SandAI0.1.0与SandIAM0.6.0旧记录，server/plugin仅宿主核心，未改旧记录或数据。

本轮新增5.1-5.4，采用统一持久根、旧根整根兼容、显式维护迁移与登记/实际目录并集。保留文件登记格式，不引入数据库双写。候选含恢复所需数据，不能视为可删缓存。

已通知sand_plugins任务“SandIAM 0.7.4 兼容迭代”。消费者发现rsync --delete会威胁新目录，已在其权威脚本server相对根增加/storage/sandpackage/***双向排除；消费者报告真实zsh+rsync临时source/target的dry-run/apply验证通过：新旧根同名文件不覆盖、源独有不复制、目标独有不删除、源整个保护目录不存在时目标仍保留；普通storage文件仍复制删除。消费者未同步真实demo、未迁移、未发布或提交。已有其他工作树改动保留。该项是消费者回报证据，不冒充主控独立执行。

实现与独立复验结果随后记录；尚未迁移当前实例旧目录。

### 实现与独立验收结果

- PluginStorage统一普通安装、兼容恢复及CLI的整根路径：新实例server/storage/sandpackage，旧runtime有数据时整根兼容，双根拒绝。InstallController合并登记与实际info.ini应用目录，未登记state6、缺文件state7、坏元数据state99，GET不补登记或搬迁。
- sandpackage:storage-migrate默认只读，--apply还需--maintenance。同盘原子rename且持有已有锁到结束；拒绝在途状态、未完成标记、未完成恢复、不安全路径、旧路径绑定及已有目标。终态且身份有效的fresh审计原样保留。
- 主控执行通过：PluginStorageTest最终34条、RepositoryHttpResponseTest18条、RepositoryDistributionTest69条、UpstreamPostgresLifecycleTest44条、FreshInstallRecoveryTest51条、FailedUpgradeRecoveryV2Test18条。后五套在迁移准入delta前通过，delta只改显式迁移准入；独立审查亦复跑受影响套件。测试用真实临时文件/控制器及SQL recording，不是业务数据库验收。PHP语法7/7及OpenSpec strict、git diff --check通过。
- 独立Astra发现并复现两项迁移准入问题：宽松state转换放行坏登记、拒绝正常成功新装审计。主控修复并增加拒绝原文保留和成功终态审计迁移测试；独立Storage34及真实InstallLogic正常安装完整journal模拟旧根再迁移通过，journal逐字节一致。无剩余本切片must-fix。
- 当前源码实例实际CLI默认inspect通过：apply=false/migrated=false；缺少--maintenance的--apply被明确拒绝。当前8788无监听且无该runtime/webman.pid，未启动服务、未执行实际目录迁移、未修改插件业务数据库。
- sand_plugins消费方已收到目录、解析接口和迁移命令；同步保护临时rsync验证已通过，实际demo同步和迁移仍未执行。发布清晰revision后消费方主动拉取，不能把当前代码/临时测试视为消费方已部署。

## 异常插件清理后重新安装（2026-09-21）

用户明确要求异常安装不能只阻断，必须提供清理后重新安装的出口。本轮实现独立于普通卸载的清理预览和确认入口，基于原包及可选受验仓库同插件包的声明识别表和菜单；数据库事务、精确菜单ID、DROP RESTRICT、文件原子归档和持久日志续作。原登记已归档但流程未结束时，列表仍从清理日志提供入口，普通安装保持阻断；最终完成才回到未安装。

独立审查先发现“无菜单DELETE声明时漏检查本插件菜单”缺陷；已修复为无条件检查归属菜单，并独立重放确认预览拒绝、候选保留、数据库零变更。后端增量独立审查无must-fix。清理故障行为45项通过，另通过PluginStorage、RepositoryDistribution（含补充包校验/不安装边界）、RepositoryHttpResponse、FreshInstallRecovery、UpstreamPostgresLifecycle、PostgresLifecycleSqlExecutor回归。

真实生命周期在原已授权独立库`sandadmin_repository_acceptance_20260918`内进行；实际连接Unix本机，未新建数据库。使用中立`cleanup-probe`，真实InstallLogic安装→移走后端模拟state7→清理1表/2菜单→重新上传和安装state1→正常卸载state0，宿主菜单数量恢复；归档内原包及用户改动文件内容保留。额外真实复现旧包漏新版依赖表：创建第二张本次测试表及FK，旧预览具体拒绝→补充声明→2表预览→清理→重装→正常卸载通过。测试对象均移除，归档及审计保留。证据在任务工作区`work/cleanup-acceptance-20260921/lifecycle-final.log`、`supplemented-lifecycle.log`。

当前用户宿主仅做范围检查和受验补充包准备：原IAM登记0.6.0保持，实际GithubRepositoryClient校验仓库0.7.3 ZIP后只保存声明，完整预览86表/176菜单/1目录；额外只读检查外部FK、事件触发器、共享菜单自定义触发器均为空。没有删除本实例表、菜单或安装记录，没有安装IAM。用户应在管理对话框查看范围后自行明确确认清理。当前8788新POST检查路由未登录返回业务401，GET清理404；热重载已加载路由。

前端与真实页面独立验收尚在本轮收尾，见后续补充记录，不以以上后端证据替代。

### 最终页面验收

独立Astra通过真实Chrome操作实际组件HTTP夹具：86表/176菜单默认折叠，390×844窄屏检查，展开区域限制220px并独立滚动；首次归档失败撤掉旧确认，重新检查进入files_pending，重新输入标识继续成功；重载后进入仓库，同插件显示未安装且直接安装按钮可用。补包、错误标识禁用、待清理禁止换包及Escape关闭亦通过。后端缓存刷新警告不会因重载成功而隐藏。截图在任务工作区`work/cleanup-acceptance-20260921/ui-86-176-narrow.png`和`ui-reinstall-ready.png`。这是实际组件浏览器验收，非生产认证接口调用或当前IAM真实删除；真实数据库生命周期证据见上文。

前端最终Prettier、ESLint、生产与夹具vue-tsc、生产构建3065模块、夹具构建1865模块通过；真实Chrome组件行为18/18通过，包含大范围默认折叠、确认、防重、失败续作及重装入口。构建仅既有导入/包体积警告。OpenSpec strict及diff检查通过。按本任务已有授权提交到`codex/sandadmin-rename`并推送github同名分支，通知sand_plugins消费该修订；不会自动替消费者同步宿主或删除当前IAM数据。

本轮实际模型记录：主控gpt-6-astra/low/openai；cleanup_design_review与cleanup_acceptance请求及实际均gpt-6-astra/medium/openai；cleanup_ui请求及实际均gpt-5.6-sol/medium/openai。

## Permission-menu compatibility (2026-09-21)

Added app-bound slug/component/path predicates and exact lowercase PostgreSQL quoted identifiers. Existing ownership-chain and child coverage checks remain; empty code and name are not ownership evidence.

Source declarations were read directly from sand_plugins revision 0fc83bb43a5ec1e106c382e9228dc05779ae25da on codex/plugin-cleanup-contracts. The actual parser accepts SandAI 14-table and Workflow 7-table declarations. This proves declaration compatibility, not actual uninstallation of either plugin. Consumer reports Workflow 51/51 menu coverage and adjacent-path rejection. Existing released ZIPs were not replaced.

Real PostgreSQL acceptance used the existing authorized isolated database and neutral cleanup-probe: install root/page/empty-code slug button, preserve an unrelated empty-code menu, simulate missing runtime, preview three owned menus, cleanup, reinstall, normal uninstall. Both quoted and unquoted variants passed; test data removed. Logs: work/cleanup-acceptance-20260921/slug-menu-lifecycle.log and quoted-slug-menu-lifecycle.log in the task workspace. Current IAM was inspected only: 86 tables, 176 menus; no deletion.

Actual plugin SQL exposed quoted-identifier incompatibility, now fixed. Independent review exposed slugLIKE/codeLIKE being normalized despite invalid token boundaries; fixed and independently replayed. Final 96 behavior assertions pass, both committed plugin declarations pass, RepositoryDistribution passes, diff check passes. No UI changes; prior independent browser evidence reused. Independent reviewer reports no remaining must-fix.

Commit/push uses the existing authorized host feature branch; consumers must pull the revision and separately verify real plugin lifecycle before publishing replacement candidates. Models: root gpt-6-astra/low/openai; cleanup_design_review and cleanup_acceptance requested/actual gpt-6-astra/medium/openai.

## Card layout and legacy menus (2026-09-21)

Repository cards now separate the title, description, author/status, and primary/secondary actions. Long titles have a bounded two-line area and tooltip; narrow screens place the primary action on its own full-width row. Existing operation handlers and disabled/loading semantics are unchanged. Production/fixture typechecks, lint, formatting, both builds, and 18 browser behavior checks passed. Independent Astra operated the signed-in localhost3006 page: document and version dialogs opened, IAM manage action selected the management tab, and the legacy Document item was absent. The three-card long-text fixture passed desktop and 390px overflow/action checks. Screenshots are in task work/cleanup-acceptance-20260921/cards-live-desktop.png, cards-fixture-desktop.png, cards-fixture-narrow.png. The file named cards-live-narrow.png is actually desktop-sized and is not narrow-screen evidence.

Both PostgreSQL bootstrap scripts no longer insert the default Document link. The current sandadmin database had the old id19 Document/type4/root/name/link_url=https://saithink.top record, despite newer source seed URLs. Per the user's explicit removal option, that exact record and its role links were backed up, checked for absent children, deleted transactionally, and menu/auth caches cleared. Remaining Document records=0; authenticated browser confirmed absence. FirstLoginContractTest 18 assertions passed.

Current IAM was inspected only: 215 active sand_iam permissions, 100 missing parents, zero parent_id=0 buttons, 215 distinct slugs, and 20543 role-menu links. The source plugin task traced 95 old base permissions plus 5 migration006 permissions whose empty code was skipped by later code-based refresh. Plugin-side naming/generation and bounded repair are in progress; no IAM permissions or role links have been modified in this host. Full read-only inventory is in task work/cleanup-acceptance-20260921/iam-permission-inventory-before.json. Task8.3 remains pending and is not hidden by the completed UI/Document fixes.
