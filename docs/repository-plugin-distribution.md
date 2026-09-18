# 从 GitHub 仓库安装插件

插件仓库不需要独立市场服务、市场账号或订单。后台从维护者指定的公开 GitHub 仓库读取 `plugins/catalog.json`，按版本下载同一仓库的 Release 附件，核验后交给现有 SandPackage 安装器。

## 使用者

1. 打开插件管理中的“插件仓库”，读取清单或刷新。
2. 查看插件版本、更新说明和宿主兼容范围，下载指定版本。
3. 下载完成切回“本地安装”，继续安装；已有版本则按现有升级确认操作。
4. 有前端或 Composer 依赖时，继续完成原安装流程要求的依赖安装。前端源码部署不等于生产前端已经重建发布。

下载成功只表示插件包已准备；下载阶段不执行 SQL。安装、升级和卸载仍使用已有生命周期与失败恢复逻辑。仓库不可达时可使用原有 ZIP 上传入口；清单不存在会显示错误，不回退到 SaiThink 市场。

服务端配置（`server/plugin/sandpackage/config/repository.php`）：

```dotenv
SANDADMIN_PLUGIN_REPOSITORY=supdger/sandadmin
SANDADMIN_PLUGIN_REF=main
```

只支持公开 GitHub 仓库。可固定 tag 或 commit；配置来源由管理员在服务端维护，浏览器不能提交任意仓库或下载地址。应用配置的修改需按宿主既有部署流程生效。

## 维护者：单插件发布

插件源包与运行目录分离。源包可位于 `plugins/<app>/`，也可在迁仓前位于已有权威插件工作区；打包命令接受显式目录，不复制或修改源目录。

```text
plugins/<app>/
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
php scripts/build-plugin-package.php plugins/example-plugin dist/plugins example-plugin-v1.0.0 6.0.11
```

`example-plugin` 只是格式示例，不代表已有可安装插件。命令输出独立 ZIP 和 `<app>-<version>.catalog.json` 条目，ZIP 根目录直接包含 `info.ini`，不包裹额外目录。安装器使用原有插件标识，本次不将 `sand-iam` 重命名为 `iam`。

维护者完成包验证后：

1. 在 `supdger/sandadmin` 创建对应 tag 的 Release，将 ZIP 作为附件上传。
2. 把生成条目合入 `plugins/catalog.json` 的 `plugins` 数组；同一个插件的新版本追加到该插件的 `versions` 中，并补充 `notes`。不要重复 app 或 version。
3. 将清单发布到消费者配置的 ref。先上传附件，再公开引用它的清单；不得用源码快照 ZIP 代替插件包。
4. 保留旧版本附件及其摘要，升级路径由插件自己的 `update.sql` 负责。兼容字段表示宿主版本范围，不证明任意旧插件版本均可直接跨版本升级。

清单结构：

```json
{
  "schema": 1,
  "plugins": [
    {
      "app": "example-plugin",
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
- SHA-256 绑定所选清单版本及附件内容，但不替代仓库维护者权限管理；仓库自身是信任来源。
- 清单上限 1MiB，下载 ZIP 上限 5MiB；现有解压上限 64MiB、2048 条目。超过限制应精简发布载荷，不能将未声明的依赖目录、测试或环境文件直接打入包。
- 仅 HTTPS GitHub 及固定附件域名，验证 TLS，连接超时 5 秒，单次请求含重定向总时限 60 秒，最多 3 次重定向。下载操作会先刷新清单，再下载附件。
- 传输使用 cURL multi 与 Worker 定时驱动，不在 HTTP Worker 等待网络；每进程最多 2 个活动请求，活动与排队请求总数最多 8。临时 ZIP 在成功及失败后清理。
- 当前普通安装线拒绝非空 `sand_platform` 扩展；已有插件不得仅删除元数据来绕过真实依赖或迁移要求。迁仓不等于兼容验收。
- 初始清单为空。这表示尚无按此契约发布的插件，不表示原插件不存在。

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
