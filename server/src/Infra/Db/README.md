# Infra\Db — DBAL 仓储基础件

- 列表参数（HTTP query）：`page`(≥1) `page_size`(1–100，默认 20) `sort=<字段>` `order=asc|desc`（默认 asc）。
  不接受 `filters[...]` / `filter[...]` 动态筛选语法，出现即抛 `InvalidQueryException`。
- 具名筛选参数（`keyword`、`status`、`create_time_from/to` 等）由各模块的列表查询对象显式声明，
  如 `Module/<名>/<名>ListQuery`（继承 `ListQuery`，`fromArray` 里解析并做类型校验）。
- 用法：`XxxListQuery::fromArray($request->get())` → `XxxRepository::list($query)` → `Page<VO>`，
  JSON `{items,total,page,page_size}`。
- 子类 `final class XxxRepository extends Repository`（`@extends Repository<Vo>`），实现
  `table()/columns()/fieldMap()/hydrate()`：
  - `columns()` 显式列出 SELECT 列，禁止 `SELECT *`；
  - `fieldMap()` 是排序/筛选字段白名单（白名单键 => 列名），未声明字段抛 `InvalidQueryException`；
  - `likeFields()` 声明允许前缀模糊的字段（只允许有索引支撑的列）。
- 筛选用 `whereEq/whereNeq/whereGt/whereGte/whereLt/whereLte/whereLikePrefix/whereIn`：
  运算符由服务端固定、字段走白名单、值走参数绑定；值为 null（IN 为空）不加条件。
- `hydrate()` 用 `Row` 按列取类型化值（`int/string/float/bool/dateTime/isNull`）。
- 公有方法只返回 VO/Page/?VO/int/bool；VO 一律 `final readonly`。
- 无 ORM/EntityManager；无静态/进程级可变状态，Connection 构造注入（后续由连接入口按请求提供）。
- 示例：`Module/DemoNote/`（VO + DemoNoteListQuery + DemoNoteRepository；
  表 sand_demo_note 只在集成测试中自建，不进生产迁移）。
