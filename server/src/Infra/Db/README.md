# Infra\Db — B1 DBAL 仓储基础件

- 列表参数：`page`(≥1) `page_size`(1–200，默认 20) `sort`(`-created_at,id`) `filters[字段][op]=值`
  - op：eq neq gt gte lt lte like in(逗号分隔) null notnull
- `ListQuery::fromArray($request->get())` → `XxxRepository::list($q)` → `Page<VO>`，JSON `{items,total,page,page_size}`
- 子类 `final class XxxRepository extends Repository`（`@extends Repository<Vo>`），实现 `table()/fieldMap()/hydrate()`；
  `fieldMap()` 是排序/筛选白名单，未列字段抛 `InvalidQueryException`（含 `fields`，供 ErrorMiddleware 映射 422）。
- 公有方法只返回 VO/Page/?VO/bool/int；VO 一律 `final readonly`。
- 无 ORM/EntityManager；无静态/进程级可变状态，Connection 构造注入（后续由 Pool 按请求提供）。
- 示例：`Infra\Repository\Demo\DemoNoteRepository` + `Domain\Demo\DemoNote`。
