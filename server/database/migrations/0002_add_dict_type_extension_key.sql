-- sandadmin 1.x 宿主 · 迁移 0002
-- 迁移版本（release_version）：1.1.0；兼容标记：compatible
-- 目标数据库：PostgreSQL 15+；由迁移执行器在一个事务里执行，本文件不写事务语句，失败整体回滚。
-- 增量迁移：sand_system_dict_type 增加 extension_key 列与普通索引，只新增不改存量结构。

ALTER TABLE sand_system_dict_type
    ADD COLUMN extension_key varchar(128) NULL;           -- 插件来源标识，格式 <plugin_code>.<组key>；NULL=系统自带或管理员手工创建

CREATE INDEX ix_sand_system_dict_type_extension_key ON sand_system_dict_type (extension_key);

COMMENT ON COLUMN sand_system_dict_type.extension_key IS '插件来源标识，格式 <plugin_code>.<组key>；NULL=系统自带或管理员手工创建';
