# SandWorkflow PostgreSQL 1.0.7

## PostgreSQL adaptation

- Package SQL is PostgreSQL-native: `jsonb`, identity primary keys, PostgreSQL indexes, and quoted identifiers.
- SaiPackage installation no longer depends on MySQL session variables; menu parents are resolved through their stable menu codes.
- Runtime copy-recipient lookups use JSONB containment.
- This package is for new PostgreSQL installations and does not perform an in-place MySQL-to-PostgreSQL data migration.

# SandWorkflow 1.0.2 upstream history

## 内容

- 提供 BeFlow 风格的表单与流程设计器、流程分组、流程定义、发布、发起、待办、已办和流程数据查询。
- 支持条件分支、会签、或签、顺序审批、拒绝与撤销，并保存流程版本与运行快照。
- 使用 SaiAdmin 6.x 的用户、角色和部门接口为审批人与组织选择器提供数据。

## 安装

1. 在 SaiAdmin 应用市场上传 `sandworkflow-1.0.2-clean.zip`。
2. 安装后为所需角色授予 SandWorkflow 菜单与按钮权限。
3. 刷新前端菜单并重载后端服务。

## 验证

- PHP 源码已通过语法检查。
- Demo 前端的 `vue-tsc --noEmit` 与生产构建已通过。
- Demo 数据库已创建全部 `sand_workflow_*` 表。

## 已知限制

- 公式仅支持数字、括号与四则运算，不执行任意 JavaScript。
