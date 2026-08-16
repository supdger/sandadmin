-- Built-in platform navigation for a SandAdmin installation.
-- SandIAM is registered here for this platform baseline. SandAI is deliberately
-- absent: it is installed as the independent `sand-ai` plugin package.

INSERT INTO "sand_system_menu" ("parent_id","name","code","slug","type","path","component","method","icon","sort","link_url","is_iframe","is_keep_alive","is_hidden","is_fixed_tab","is_full_page","generate_id","generate_key","status","remark","created_by","updated_by","create_time","update_time","delete_time")
SELECT 0,'SandIAM 身份与访问','SandIAM','',1,'/sand-iam','',NULL,'ri:shield-keyhole-line',98,'',2,2,2,2,2,0,'',1,'SandAdmin built-in platform',1,1,NOW(),NOW(),NULL
WHERE NOT EXISTS (SELECT 1 FROM "sand_system_menu" WHERE "code" = 'SandIAM' AND "delete_time" IS NULL);

INSERT INTO "sand_system_menu" ("parent_id","name","code","slug","type","path","component","method","icon","sort","link_url","is_iframe","is_keep_alive","is_hidden","is_fixed_tab","is_full_page","generate_id","generate_key","status","remark","created_by","updated_by","create_time","update_time","delete_time")
SELECT (SELECT "id" FROM "sand_system_menu" WHERE "code" = 'SandIAM' AND "delete_time" IS NULL),'概览','SandIAMOverview','',2,'overview','/plugin/sand-iam/index/index',NULL,'ri:dashboard-line',100,'',2,2,2,2,2,0,'',1,'',1,1,NOW(),NOW(),NULL
WHERE NOT EXISTS (SELECT 1 FROM "sand_system_menu" WHERE "code" = 'SandIAMOverview' AND "delete_time" IS NULL);
INSERT INTO "sand_system_menu" ("parent_id","name","code","slug","type","path","component","method","icon","sort","link_url","is_iframe","is_keep_alive","is_hidden","is_fixed_tab","is_full_page","generate_id","generate_key","status","remark","created_by","updated_by","create_time","update_time","delete_time")
SELECT (SELECT "id" FROM "sand_system_menu" WHERE "code" = 'SandIAM' AND "delete_time" IS NULL),'组织','SandIAMOrganization','',2,'organization','/plugin/sand-iam/organization/index',NULL,'ri:building-2-line',99,'',2,2,2,2,2,0,'',1,'',1,1,NOW(),NOW(),NULL
WHERE NOT EXISTS (SELECT 1 FROM "sand_system_menu" WHERE "code" = 'SandIAMOrganization' AND "delete_time" IS NULL);
INSERT INTO "sand_system_menu" ("parent_id","name","code","slug","type","path","component","method","icon","sort","link_url","is_iframe","is_keep_alive","is_hidden","is_fixed_tab","is_full_page","generate_id","generate_key","status","remark","created_by","updated_by","create_time","update_time","delete_time")
SELECT (SELECT "id" FROM "sand_system_menu" WHERE "code" = 'SandIAM' AND "delete_time" IS NULL),'应用与环境','SandIAMApplication','',2,'application','/plugin/sand-iam/application/index',NULL,'ri:apps-2-line',98,'',2,2,2,2,2,0,'',1,'',1,1,NOW(),NOW(),NULL
WHERE NOT EXISTS (SELECT 1 FROM "sand_system_menu" WHERE "code" = 'SandIAMApplication' AND "delete_time" IS NULL);
INSERT INTO "sand_system_menu" ("parent_id","name","code","slug","type","path","component","method","icon","sort","link_url","is_iframe","is_keep_alive","is_hidden","is_fixed_tab","is_full_page","generate_id","generate_key","status","remark","created_by","updated_by","create_time","update_time","delete_time")
SELECT (SELECT "id" FROM "sand_system_menu" WHERE "code" = 'SandIAM' AND "delete_time" IS NULL),'环境','SandIAMEnvironment','',2,'environment','/plugin/sand-iam/environment/index',NULL,'ri:server-line',97,'',2,2,2,2,2,0,'',1,'',1,1,NOW(),NOW(),NULL
WHERE NOT EXISTS (SELECT 1 FROM "sand_system_menu" WHERE "code" = 'SandIAMEnvironment' AND "delete_time" IS NULL);
INSERT INTO "sand_system_menu" ("parent_id","name","code","slug","type","path","component","method","icon","sort","link_url","is_iframe","is_keep_alive","is_hidden","is_fixed_tab","is_full_page","generate_id","generate_key","status","remark","created_by","updated_by","create_time","update_time","delete_time")
SELECT (SELECT "id" FROM "sand_system_menu" WHERE "code" = 'SandIAM' AND "delete_time" IS NULL),'工作负载与凭据','SandIAMWorkload','',2,'workload-client','/plugin/sand-iam/workload-client/index',NULL,'ri:robot-2-line',96,'',2,2,2,2,2,0,'',1,'',1,1,NOW(),NOW(),NULL
WHERE NOT EXISTS (SELECT 1 FROM "sand_system_menu" WHERE "code" = 'SandIAMWorkload' AND "delete_time" IS NULL);
INSERT INTO "sand_system_menu" ("parent_id","name","code","slug","type","path","component","method","icon","sort","link_url","is_iframe","is_keep_alive","is_hidden","is_fixed_tab","is_full_page","generate_id","generate_key","status","remark","created_by","updated_by","create_time","update_time","delete_time")
SELECT (SELECT "id" FROM "sand_system_menu" WHERE "code" = 'SandIAM' AND "delete_time" IS NULL),'服务与授权','SandIAMGrant','',2,'service-grant','/plugin/sand-iam/service-grant/index',NULL,'ri:service-line',95,'',2,2,2,2,2,0,'',1,'',1,1,NOW(),NOW(),NULL
WHERE NOT EXISTS (SELECT 1 FROM "sand_system_menu" WHERE "code" = 'SandIAMGrant' AND "delete_time" IS NULL);
INSERT INTO "sand_system_menu" ("parent_id","name","code","slug","type","path","component","method","icon","sort","link_url","is_iframe","is_keep_alive","is_hidden","is_fixed_tab","is_full_page","generate_id","generate_key","status","remark","created_by","updated_by","create_time","update_time","delete_time")
SELECT (SELECT "id" FROM "sand_system_menu" WHERE "code" = 'SandIAM' AND "delete_time" IS NULL),'审计','SandIAMAudit','',2,'audit','/plugin/sand-iam/audit/index',NULL,'ri:file-search-line',94,'',2,2,2,2,2,0,'',1,'',1,1,NOW(),NOW(),NULL
WHERE NOT EXISTS (SELECT 1 FROM "sand_system_menu" WHERE "code" = 'SandIAMAudit' AND "delete_time" IS NULL);
