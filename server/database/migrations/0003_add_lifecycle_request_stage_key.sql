-- sandadmin 1.x 宿主 · 迁移 0003
-- 迁移版本（release_version）：1.1.0；兼容标记：compatible
-- 目标数据库：PostgreSQL 15+；由迁移执行器在一个事务里执行，本文件不写事务语句，失败整体回滚。
-- 增量迁移：sand_system_lifecycle_request 增加 stage_key 列与取值约束，只新增不改存量结构。

ALTER TABLE sand_system_lifecycle_request
    ADD COLUMN stage_key varchar(16) NULL,                -- 固定阶段标识 check/backup/download/switch/verify，仅系统升级申请使用
    ADD CONSTRAINT ck_sand_system_lifecycle_request_stage_key
        CHECK (stage_key IS NULL OR stage_key IN ('check', 'backup', 'download', 'switch', 'verify'));

COMMENT ON COLUMN sand_system_lifecycle_request.stage_key IS '固定阶段标识 check/backup/download/switch/verify，仅系统升级申请使用；stage 列仍是人话描述';
