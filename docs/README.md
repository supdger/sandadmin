# SandAdmin 文档

本目录是 SandAdmin 宿主的公共文档入口。它说明宿主的安装、运行、架构、插件契约和开源来源；每个可独立发布的插件仍须在自己的目录中维护完整 README。

## 使用 SandAdmin

- [本地运行与首次安装](getting-started.md)
- [架构与插件边界](architecture.md)
- [更名与插件兼容性通知](compatibility/sandadmin-rename-notice.md)

## 开发和发布

- [插件开发与发布约定](plugin-development.md)
- [上游来源、署名与许可证说明](upstream-and-licensing.md)
- [贡献指南](../CONTRIBUTING.md)
- [安全漏洞报告](../SECURITY.md)

## 文档责任边界

宿主文档只陈述宿主共有的能力和契约。插件的功能清单、依赖、安装包、权限、配置、升级与卸载步骤，必须随插件发布并由插件 README 负责。发布前应同时核对宿主版本、插件版本和实际 PostgreSQL 验收记录。
