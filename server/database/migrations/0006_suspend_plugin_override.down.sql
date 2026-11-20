-- sandadmin 1.x 宿主 · 迁移 0006 回退
-- 目标数据库：PostgreSQL 15+；由迁移执行器在一个事务里执行，本文件不写事务语句，失败整体回滚。
-- 移除两张表的 suspend_time 列与调度部分索引。
-- 丢数据项：suspend_time 里的停用记录随 DROP COLUMN 删除、不可逆，数据恢复靠升级前备份。

DROP INDEX IF EXISTS ix_sand_system_schedule_next_run_time;

ALTER TABLE sand_system_plugin_extension
    DROP COLUMN IF EXISTS suspend_time;

ALTER TABLE sand_system_schedule
    DROP COLUMN IF EXISTS suspend_time;
