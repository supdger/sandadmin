-- sandadmin 1.x 宿主 · 迁移 0005 回退
-- 目标数据库：PostgreSQL 15+；由迁移执行器在一个事务里执行，本文件不写事务语句，失败整体回滚。
-- 删除引用登记表 sand_system_dict_item_ref。
-- 丢数据项：表内引用记录随 DROP 一并删除、不可逆，数据恢复靠升级前备份。

DROP TABLE IF EXISTS sand_system_dict_item_ref;
