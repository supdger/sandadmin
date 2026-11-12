-- sandadmin 1.x 宿主 · 迁移 0005
-- 迁移版本（release_version）：1.1.0；兼容标记：compatible
-- 目标数据库：PostgreSQL 15+；由迁移执行器在一个事务里执行，本文件不写事务语句，失败整体回滚。
-- 选项「已使用」要查真实引用，不改已发布的 0001。插件或业务写入引用行后，删除前提示才能区分用过和没用过。

CREATE TABLE IF NOT EXISTS sand_system_dict_item_ref (
    item_id      bigint       NOT NULL,
    subject_type varchar(64)  NOT NULL,
    subject_id   varchar(64)  NOT NULL,
    create_time  timestamptz(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_sand_system_dict_item_ref PRIMARY KEY (item_id, subject_type, subject_id),
    CONSTRAINT fk_sand_system_dict_item_ref_item
        FOREIGN KEY (item_id) REFERENCES sand_system_dict_item (id) ON DELETE CASCADE
);
COMMENT ON TABLE sand_system_dict_item_ref IS '选项被哪条业务数据引用；有行表示已使用，删除选项时级联清理';
CREATE INDEX IF NOT EXISTS ix_sand_system_dict_item_ref_subject
    ON sand_system_dict_item_ref (subject_type, subject_id);
