# 从 GitHub 仓库安装插件

插件仓库不需要独立市场服务、市场账号或订单。后台从 SandAdmin 的公开 GitHub 仓库读取 `plugins/catalog.json`，再按每个插件声明的独立公开仓库下载 Release 附件，核验后交给现有 SandPackage 安装器。

## 使用者

1. 打开“插件仓库”，查看插件版本、文档、宿主兼容范围及本机状态。
2. 未安装时选择“安装”，有兼容新版本时选择“升级”；确认后自动下载、校验并调用既有安装流程，无需切页再次点击安装。
3. 已安装同版本不重复安装，不能降级；待处理候选、依赖或异常状态显示原因并跳转“插件管理”。
4. “插件管理”保留ZIP上传、适用的安装/卸载、依赖处理和恢复操作。有前端或Composer依赖时继续完成原流程，前端源码部署不等于生产前端已重建发布。
5. “查看文档”读取所选版本包内README原文，异常安装状态也可以查看；文档只读，不执行HTML或加载外部图片。

底层下载接口仍只准备候选，不执行SQL；界面在明确确认后串联现有安装/升级入口。刷新后确认真实状态已安装才报告成功。仓库不可达时可使用ZIP上传；清单不存在会显示错误，不回退SaiThink市场。

如果安装记录显示已安装，但运行目录缺失，系统会显示异常并拒绝重复安装。需在插件管理或原锁定宿主核查记录、文件和数据库，不删除记录来绕过保护。

服务端配置（`server/plugin/sandpackage/config/repository.php`）：

```dotenv
SANDADMIN_PLUGIN_REPOSITORY=supdger/sandadmin
SANDADMIN_PLUGIN_REF=main
```

只支持公开 GitHub 仓库。可固定 tag 或 commit；配置来源由管理员在服务端维护，浏览器不能提交任意仓库或下载地址。应用配置的修改需按宿主既有部署流程生效。

## 安装登记与本机插件目录

插件仓库从GitHub清单发现可下载插件；插件管理合并本机安装登记与 `server/plugin/<app>/info.ini` 可识别的应用插件。扫描只解析数据，不执行插件PHP。

- `server/plugin/<app>/` 是实际运行代码。
- 新实例的 `server/storage/sandpackage/` 保存安装登记、候选、备份、归档、锁及恢复日志。这些是持久数据，不能作为缓存清理，也不能包含在宿主源码同步中。
- 网络下载使用的临时ZIP由现有下载流程清理；已交安装器的候选可能用于继续安装或恢复，不能按下载缓存删除。
- 只有实际文件而没有登记时，显示未登记并阻止普通覆盖安装；只有登记而实际文件缺失时，显示安装文件缺失。目录存在不证明SQL、菜单或配置安装完成。
- 旧 `runtime/sandpackage/` 有数据时整根兼容，不在刷新页面时迁移。新旧根同时存在有效数据会报告冲突，不自动合并或选择其中一份。

迁移旧目录前应结束所有安装、依赖终端及恢复操作，并进入维护窗口。显式迁移命令先检查，再在同一文件系统内整体搬迁；拒绝活动锁、未完成恢复、旧路径绑定及不安全文件，不通过复制后删除掩盖跨盘问题。旧记录正在恢复时，先按原锁定宿主完成恢复，再迁移。迁移不执行插件SQL，不修复缺失的业务代码，也不改变安装状态。

维护命令在 `server/` 执行：

```sh
# 默认只读检查，不创建登记、不搬迁目录
php webman sandpackage:storage-migrate

# 确认所有Web、终端和恢复写者停止后才执行
php webman sandpackage:storage-migrate --apply --maintenance
```

`--maintenance` 是操作者对维护窗口的显式确认，不代替停止服务或检测所有外部进程。迁移后按宿主启动流程重新启动服务。命令报冲突时保留两处数据供核查，不删除任一目录来绕过检查。

消费者更新宿主前必须先保护新旧数据目录，见[宿主发布与消费同步](host-consumer-sync.md)。插件包目录结构及生命周期SQL契约保持不变。

## 维护者：单插件发布

插件源包与运行目录分离。源包位于插件自己的权威仓库；迁仓期间也可从已有权威插件工作区的已提交目录打包。打包命令接受显式目录，不复制或修改源目录。

```text
<plugin-repository>/
├── info.ini
├── config.json
├── install.sql
├── update.sql
├── uninstall.sql
├── README.md
├── LICENSE
├── plugin/<app>/config/app.php
└── sandadmin-artd/src/views/plugin/<app>/  # 可选
```

使用当前宿主提供的构建命令：

```sh
mkdir -p dist/plugins
php server/scripts/build-plugin-package.php /path/to/example-plugin dist/plugins example-plugin-v1.0.0 6.0.11
```

`example-plugin` 只是格式示例，不代表已有可安装插件。命令输出独立 ZIP 和 `<app>-<version>.catalog.json` 条目，ZIP 根目录直接包含 `info.ini`，不包裹额外目录。安装器使用原有插件标识，本次不将 `sand-iam` 重命名为 `iam`。

维护者完成包验证后：

1. 在插件自己的公开仓库创建对应 tag 的 Release，将 ZIP 作为附件上传。
2. 把生成条目合入 SandAdmin `plugins/catalog.json` 的 `plugins` 数组；插件条目必须声明 `repository`。同一个插件的新版本追加到该插件的 `versions` 中，并补充 `notes`。不要重复 app 或 version。
3. 将清单发布到消费者配置的 ref。先上传附件，再公开引用它的清单；不得用源码快照 ZIP 代替插件包。
4. 保留旧版本附件及其摘要，升级路径由插件自己的 `update.sql` 负责。兼容字段表示宿主版本范围，不证明任意旧插件版本均可直接跨版本升级。

清单结构：

```json
{
  "schema": 1,
  "plugins": [
    {
      "app": "example-plugin",
      "repository": "maintainer/example-plugin",
      "title": "示例插件",
      "about": "用途说明",
      "author": "维护者",
      "versions": [
        {
          "version": "1.0.0",
          "tag": "example-plugin-v1.0.0",
          "asset": "example-plugin-1.0.0.zip",
          "sha256": "由打包命令产生的64位小写摘要",
          "host_min": "6.0.11",
          "notes": "更新说明"
        }
      ]
    }
  ]
}
```

上面摘要是说明占位，不能原样发布。`host_max` 可选，包含上下边界；版本采用三段数字，可附预发布标记。`info.ini` 的 `app/version` 必须与清单及后端 `config/app.php` 一致。

## 边界与失败处理

- 服务端仅超级管理员可读清单和准备候选；下载接口仅 POST。实际包地址从配置和已验证清单构造。
- SHA-256 绑定所选清单版本及附件内容，但不替代仓库维护者权限管理；SandAdmin 目录及其声明的插件仓库共同构成信任来源。浏览器不能覆盖 `repository` 或提交下载 URL。
- 清单上限 1MiB，下载 ZIP 上限 5MiB；现有解压上限 64MiB、2048 条目。超过限制应精简发布载荷，不能将未声明的依赖目录、测试或环境文件直接打入包。
- 仅 HTTPS GitHub 及固定附件域名，验证 TLS，连接超时 5 秒，单次请求含重定向总时限 60 秒，最多 3 次重定向。下载操作会先刷新清单，再下载附件。
- 传输使用 cURL multi 与 Worker 定时驱动，不在 HTTP Worker 等待网络；每进程最多 2 个活动请求，活动与排队请求总数最多 8。临时 ZIP 在成功及失败后清理。
- 当前普通安装线拒绝非空 `sand_platform` 扩展；已有插件不得仅删除元数据来绕过真实依赖或迁移要求。迁仓不等于兼容验收。
- 当前清单登记 SandIAM、SandWorkflow 和 SandAI；每个条目都指向各自独立仓库的 Release ZIP。

本功能不自动提交、上传 Release、移动旧插件源码、执行数据库迁移或重启服务。实际公开仓库访问、真实插件数据库安装、管理前端构建发布及业务链需要在发布候选上独立验证。

## 插件本地运行依赖

有些插件的 `plugin/<app>/vendor` 是正式运行载荷。构建工具允许根 `release-build-contract.json` 明确声明该目录，声明形式复用已有插件发布契约：

```json
{
  "schema": "example-plugin.release-build-contract/v1",
  "kind": "reviewed-runtime-payload-inputs",
  "generated_payloads": {
    "plugin/example-plugin/vendor": {
      "file_count": 58,
      "tree_sha256": "由已审核运行依赖产生的64位小写树摘要"
    }
  }
}
```

示例数量和摘要不能原样使用。树摘要算法：枚举vendor内部相对文件路径与每个文件的SHA-256，按路径 `SORT_STRING` 排序，用 `JSON_UNESCAPED_SLASHES` 编码映射后计算SHA-256。构建时同时校验源码和最终ZIP，缺少声明、数量不符、内容漂移、符号链接或危险路径均拒绝；总包大小及文件数限制不变。契约随ZIP保留，其他generated_payloads条目不会自动进入通用包。

此项支持不替代插件自身的正式构建和签名流程。SandIAM还要求SDK、门户、public assets和独立签名溯源，应继续使用其正式构建产物；通用打包通过不能证明SandIAM正式发布或宿主安装通过。

## 安装文件缺失时清理并重新安装

在「插件管理」中，正常安装的插件使用「卸载」。显示「安装文件缺失」时使用「清理残留」：先检查并显示旧安装包声明的现存业务表、插件菜单和文件目录，输入插件标识后确认清理。业务表及所列菜单会被删除；旧包和残留文件会移入持久存储根的 `archives/`，文件归档不包含数据库备份。

清理完成后刷新仓库，即可重新安装。若清理中断，管理页保留「继续清理」入口；系统会重新核对现场，数据库已完成时只继续归档。不要手工删除安装登记来绕过检查，这可能遗留重装冲突。

清理不会执行旧卸载脚本中的任意SQL，只支持可验证的表声明和限定菜单删除。存在其他插件依赖、未知SQL、包身份或现场变化时，会指出原因并停止当前删除；不能把宿主核心表或其他插件的数据纳入清理。当前入口用于运行文件缺失及该清理操作的续作，不替代失败升级恢复。

若旧安装登记对应的包未涵盖数据库中的较新表，检查会列出依赖表。可在清理对话框选择仓库同一插件的发布版本，点击「使用此版本检查清理范围」。系统验证发布包后，只用其声明补全范围；安装记录版本不会变成所选版本，也不会安装该包。重新检查成功后再确认删除。清理一旦开始，补充包即被冻结，须通过「继续清理」完成原操作。

权限按钮可使用 `slug` 而不填写 `code`。异常清理接受严格绑定当前插件标识的 `slug LIKE '<app>:%'`、`component LIKE '/plugin/<app>/%'`、`path = '/<app>'` 和 `path LIKE '/<app>/%'`，以及受归属检查约束的既有 code 等式/LIKE 条件。不能使用 `code = ''`、名称匹配或缺少路径分隔边界的前缀。角色菜单和菜单须采用相同条件；选中节点还必须能通过自身或选中祖先的路径/组件证明归属，并完整覆盖子节点。固定表名与字段名支持 PostgreSQL 的小写双引号形式。
