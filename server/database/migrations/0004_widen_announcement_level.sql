-- sandadmin 1.x 宿主 · 迁移 0004
-- 迁移版本（release_version）：1.1.0；兼容标记：compatible
-- 目标数据库：PostgreSQL 15+；由迁移执行器在一个事务里执行，本文件不写事务语句，失败整体回滚。
-- 基线把 sand_system_announcement.level 建成 varchar(8)，装不下枚举值 important（9 个字符）。
-- 只加宽列，不改已发布的 0001，CHECK 取值不变。

ALTER TABLE sand_system_announcement
    ALTER COLUMN level TYPE varchar(16);
