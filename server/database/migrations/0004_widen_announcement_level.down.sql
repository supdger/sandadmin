-- sandadmin 1.x 宿主 · 迁移 0004 回退
-- 目标数据库：PostgreSQL 15+；由迁移执行器在一个事务里执行，本文件不写事务语句，失败整体回滚。
-- 把 sand_system_announcement.level 收回 varchar(8)。
-- 收窄是丢数据方向：已写入的超长 level 值会让本脚本报错；已丢长度不在这里恢复，靠升级前备份。

ALTER TABLE sand_system_announcement
    ALTER COLUMN level TYPE varchar(8);
