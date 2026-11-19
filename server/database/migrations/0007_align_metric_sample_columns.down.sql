-- sandadmin 1.x 宿主 · 迁移 0007 回退
-- 目标数据库：PostgreSQL 15+；由迁移执行器在一个事务里执行，本文件不写事务语句，失败整体回滚。
-- 列名改回接口对齐前命名，MB/GB 列换回字节列，删掉新增列、还原旧 CHECK 约束。
-- 丢数据项：MB/GB 换算已截断低位、乘回字节只是近似值；4 个新增列随 DROP 删除；
-- 精度与新增列数据不可逆，数据恢复靠升级前备份。

ALTER TABLE sand_system_metric_sample
    DROP CONSTRAINT ck_sand_system_metric_sample_cpu_usage,
    DROP CONSTRAINT ck_sand_system_metric_sample_cpu_cores,
    DROP CONSTRAINT ck_sand_system_metric_sample_memory_usage,
    DROP CONSTRAINT ck_sand_system_metric_sample_memory_used,
    DROP CONSTRAINT ck_sand_system_metric_sample_memory_total,
    DROP CONSTRAINT ck_sand_system_metric_sample_disk_free,
    DROP CONSTRAINT ck_sand_system_metric_sample_disk_total,
    DROP CONSTRAINT ck_sand_system_metric_sample_process_memory,
    DROP CONSTRAINT ck_sand_system_metric_sample_process_uptime;

ALTER TABLE sand_system_metric_sample DROP COLUMN cpu_cores;
ALTER TABLE sand_system_metric_sample DROP COLUMN memory_usage_percent;
ALTER TABLE sand_system_metric_sample DROP COLUMN process_memory_mb;
ALTER TABLE sand_system_metric_sample DROP COLUMN process_uptime_seconds;

ALTER TABLE sand_system_metric_sample
    ALTER COLUMN memory_used_mb TYPE bigint USING memory_used_mb::bigint * 1048576;
ALTER TABLE sand_system_metric_sample RENAME COLUMN memory_used_mb TO mem_used_bytes;

ALTER TABLE sand_system_metric_sample
    ALTER COLUMN memory_total_mb TYPE bigint USING memory_total_mb::bigint * 1048576;
ALTER TABLE sand_system_metric_sample RENAME COLUMN memory_total_mb TO mem_total_bytes;

ALTER TABLE sand_system_metric_sample
    ALTER COLUMN disk_free_gb TYPE bigint USING round(disk_free_gb * 1073741824)::bigint;
ALTER TABLE sand_system_metric_sample RENAME COLUMN disk_free_gb TO disk_free_bytes;

ALTER TABLE sand_system_metric_sample
    ALTER COLUMN disk_total_gb TYPE bigint USING round(disk_total_gb * 1073741824)::bigint;
ALTER TABLE sand_system_metric_sample RENAME COLUMN disk_total_gb TO disk_total_bytes;

ALTER TABLE sand_system_metric_sample RENAME COLUMN cpu_usage_percent TO cpu_percent;
ALTER TABLE sand_system_metric_sample RENAME COLUMN collected_time TO sample_time;

ALTER TABLE sand_system_metric_sample
    ADD CONSTRAINT ck_sand_system_metric_sample_cpu
        CHECK (cpu_percent IS NULL OR cpu_percent >= 0),
    ADD CONSTRAINT ck_sand_system_metric_sample_mem_used
        CHECK (mem_used_bytes IS NULL OR mem_used_bytes >= 0),
    ADD CONSTRAINT ck_sand_system_metric_sample_mem_total
        CHECK (mem_total_bytes IS NULL OR mem_total_bytes >= 0),
    ADD CONSTRAINT ck_sand_system_metric_sample_disk_free
        CHECK (disk_free_bytes IS NULL OR disk_free_bytes >= 0),
    ADD CONSTRAINT ck_sand_system_metric_sample_disk_total
        CHECK (disk_total_bytes IS NULL OR disk_total_bytes >= 0);
