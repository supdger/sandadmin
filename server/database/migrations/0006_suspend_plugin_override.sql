-- sandadmin 1.x 宿主 · 迁移 0006
-- 迁移版本（release_version）：1.1.0；兼容标记：compatible
-- 目标数据库：PostgreSQL 15+；由迁移执行器在一个事务里执行，本文件不写事务语句，失败整体回滚。
-- 不勾「删除数据」卸载时，定时任务和扩展位覆盖要停用，但不能删 schedule 行：
-- sand_system_schedule_run 的外键会把执行历史一起级联删掉。
-- suspend_time 非空表示因插件卸载而停用；重新安装时清空，enabled 与排序保留。

ALTER TABLE sand_system_schedule
    ADD COLUMN suspend_time timestamptz(0) NULL;

ALTER TABLE sand_system_plugin_extension
    ADD COLUMN suspend_time timestamptz(0) NULL;

COMMENT ON COLUMN sand_system_schedule.suspend_time IS '插件卸载且未删数据时写入；重新安装清空。非空时调度器不得认领';
COMMENT ON COLUMN sand_system_plugin_extension.suspend_time IS '插件卸载且未删数据时写入；重新安装清空。非空时覆盖不参与合并';

CREATE INDEX ix_sand_system_schedule_next_run_time
    ON sand_system_schedule (next_run_time)
    WHERE enabled AND suspend_time IS NULL;
