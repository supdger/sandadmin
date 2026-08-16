# 上游来源、署名与许可证说明

## SandAdmin 与 SaiAdmin 的关系

SandAdmin 是从 [SaiAdmin 6.x](https://github.com/saithink/saiadmin6.x) 派生并由独立维护者修改的 PostgreSQL fork。它不是 SaiAdmin 的官方版本，不代表获得 SaiAdmin、其作者或贡献者的认可、支持或背书。

该派生关系必须在仓库首页、发行说明和任何重新分发的发布页中保持清楚可见。不得将 SandAdmin 描述为 SaiAdmin 官方产品，也不得删除继承源文件中适用的版权、许可证或第三方声明。

当前 GitHub 远程仍使用历史仓库名 `supdger/saiadmin-pg`。在其完成公开更名或迁移前，它是 SandAdmin 的实际代码与问题跟踪入口；这不改变对外产品名称，也不改变本节的上游署名要求。

## 保留的兼容标识

品牌更名不等于破坏兼容性。历史 `sa_*` 标识、`saithink/saipackage` Composer 包和 `Saithink\\Saipackage` PHP 命名空间仍可能存在：它们分别用于兼容和第三方依赖解析。它们不表示 SandAdmin 是 SaiAdmin 官方发行版。

## 许可证与再分发

仓库根目录的 [LICENSE](../LICENSE) 适用于相应的 MIT 授权内容；[NOTICE](../NOTICE) 记录本仓上游与第三方说明。`sandadmin-artd/` 还保留其自身适用的许可证和源文件声明。

发布者必须：

1. 保留所有适用的版权和许可证文本。
2. 将与发布内容相关的 NOTICE 和第三方声明一并分发。
3. 对自己新增或修改的内容使用清楚、准确的署名；不要覆盖上游的原始归属。
4. 仅对已经实际验证的兼容性、安装结果和功能作出发布声明。
