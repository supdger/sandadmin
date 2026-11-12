-- sandadmin 1.x 宿主 · 迁移 0007
-- 迁移版本（release_version）：1.1.0；兼容标记：breaking
-- 目标数据库：PostgreSQL 15+；由迁移执行器在一个事务里执行，本文件不写事务语句，失败整体回滚。
-- ServerStatus 接口字段与采样表列名逐字对齐，嵌套对象按路径拍平成列名
-- （cpu.usage_percent → cpu_usage_percent）；字节列换算进 MB/GB 新列。
-- db_ok 不在接口字段里，保留：采样时数据库连通性仍要记。

ALTER TABLE sand_system_metric_sample RENAME COLUMN sample_time TO collected_time;
ALTER TABLE sand_system_metric_sample RENAME COLUMN cpu_percent TO cpu_usage_percent;

ALTER TABLE sand_system_metric_sample RENAME COLUMN mem_used_bytes TO memory_used_mb;
ALTER TABLE sand_system_metric_sample
    ALTER COLUMN memory_used_mb TYPE integer USING (memory_used_mb / 1048576)::integer;

ALTER TABLE sand_system_metric_sample RENAME COLUMN mem_total_bytes TO memory_total_mb;
ALTER TABLE sand_system_metric_sample
    ALTER COLUMN memory_total_mb TYPE integer USING (memory_total_mb / 1048576)::integer;

ALTER TABLE sand_system_metric_sample RENAME COLUMN disk_free_bytes TO disk_free_gb;
ALTER TABLE sand_system_metric_sample
    ALTER COLUMN disk_free_gb TYPE numeric(10,2) USING round(disk_free_gb / 1073741824.0, 2);

ALTER TABLE sand_system_metric_sample RENAME COLUMN disk_total_bytes TO disk_total_gb;
ALTER TABLE sand_system_metric_sample
    ALTER COLUMN disk_total_gb TYPE numeric(10,2) USING round(disk_total_gb / 1073741824.0, 2);

ALTER TABLE sand_system_metric_sample ADD COLUMN cpu_cores integer NULL;
ALTER TABLE sand_system_metric_sample ADD COLUMN memory_usage_percent numeric(5,2) NULL;
ALTER TABLE sand_system_metric_sample ADD COLUMN process_memory_mb integer NULL;
ALTER TABLE sand_system_metric_sample ADD COLUMN process_uptime_seconds bigint NULL;

ALTER TABLE sand_system_metric_sample DROP CONSTRAINT ck_sand_system_metric_sample_cpu;
ALTER TABLE sand_system_metric_sample DROP CONSTRAINT ck_sand_system_metric_sample_mem_used;
ALTER TABLE sand_system_metric_sample DROP CONSTRAINT ck_sand_system_metric_sample_mem_total;
ALTER TABLE sand_system_metric_sample DROP CONSTRAINT ck_sand_system_metric_sample_disk_free;
ALTER TABLE sand_system_metric_sample DROP CONSTRAINT ck_sand_system_metric_sample_disk_total;

ALTER TABLE sand_system_metric_sample
    ADD CONSTRAINT ck_sand_system_metric_sample_cpu_usage
        CHECK (cpu_usage_percent IS NULL OR cpu_usage_percent >= 0),
    ADD CONSTRAINT ck_sand_system_metric_sample_cpu_cores
        CHECK (cpu_cores IS NULL OR cpu_cores > 0),
    ADD CONSTRAINT ck_sand_system_metric_sample_memory_usage
        CHECK (memory_usage_percent IS NULL OR memory_usage_percent >= 0),
    ADD CONSTRAINT ck_sand_system_metric_sample_memory_used
        CHECK (memory_used_mb IS NULL OR memory_used_mb >= 0),
    ADD CONSTRAINT ck_sand_system_metric_sample_memory_total
        CHECK (memory_total_mb IS NULL OR memory_total_mb >= 0),
    ADD CONSTRAINT ck_sand_system_metric_sample_disk_free
        CHECK (disk_free_gb IS NULL OR disk_free_gb >= 0),
    ADD CONSTRAINT ck_sand_system_metric_sample_disk_total
        CHECK (disk_total_gb IS NULL OR disk_total_gb >= 0),
    ADD CONSTRAINT ck_sand_system_metric_sample_process_memory
        CHECK (process_memory_mb IS NULL OR process_memory_mb >= 0),
    ADD CONSTRAINT ck_sand_system_metric_sample_process_uptime
        CHECK (process_uptime_seconds IS NULL OR process_uptime_seconds >= 0);
