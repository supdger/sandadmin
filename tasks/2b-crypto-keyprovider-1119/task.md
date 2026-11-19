【任务】1.x 框架密钥管理接口（KeyProvider）与 Crypto 加密服务，无库部分

依据：v4 第 614 行「密钥管理接口（M11）：KeyProviderInterface 信封加密 + env 默认实现 + KMS/Vault 可替换 + 按组织数据密钥（J-10）」1–2 人天；v4 第 902–911 行 N5「第三方密钥加密存储＋主密钥管理和轮换」（f1 第 1121 行 2–3 人天）。两项都未验收，见 ~/agent-work/assistant/progress-1119/RESULT.md。

输入（Mac 路径）：~/agent-work/grokbot/review-1117-r3/顶层架构设计-v4.md（第 614、902–911 行，J-10 节）和同目录 鉴权P0-v4.md（WP8 的 SAND_CRYPTO_KEY 派生子密钥、LOG-03 脱敏器）；~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md；supdger/sandadmin main 的 server/src、server/config（只读起步）；PR #98 分支 docs/1x-skills 的 .agents/skills（照规范写，未合并，只读）。

要做：
1. KeyProviderInterface（信封加密：数据密钥加解密、按 keyver 取主密钥）＋ env 默认实现（主密钥键 SAND_CRYPTO_KEY，旧密钥列表 SAND_CRYPTO_PREVIOUS_KEYS，只从环境变量读，不落库、不进日志）；KMS/Vault 只留可替换接口，不接真实服务。按组织数据密钥：框架单租户，组织号固定 'default'（总表第 11 条、R-7）。
2. Crypto 服务：AES-256-GCM（openssl），密文格式 v<keyver>:<nonce>:<ct>:<tag>；按 keyver 轮换解密；新写入用当前 keyver。
3. 需加密字段清单写成文档（SMTP 密码、对象存储 SecretKey、AI provider key、插件声明的第三方凭证），放 server/docs/ 下（根目录 /docs/ 被 .gitignore 忽略，不要用）。
4. 单测：格式断言、篡改 tag 拒绝、旧 keyver 可解、新写入用新 keyver、密钥缺失或长度不对时启动或调用拒绝；测试密钥在测试内现场生成，不读任何真实 .env。
5. 不做：bin/sand crypto:rotate 对库里存量密文重加密（要库），在 RESULT 列为剩余；不改任何现有业务读写点。

仓库与分支：supdger/sandadmin，先 git fetch，从 origin/main 开 feat/crypto-keyprovider，开工写明基线 SHA。

范围：只新增 server/src 下 Crypto/KeyProvider 相关文件、对应 config 项（.env.example 只加键名、不加值）、server/tests/Unit 测试、server/docs/ 一份字段清单文档。不改 contract/openapi.yaml、门禁文件、白名单、CI。

禁止：不建库、不做 DDL、不写迁移文件、不跑迁移、不启任何数据库或 Redis（临时库、容器也不行），需要库才能做的停下回报；不合并 PR、不发布、不打 tag；不开下级会话；不用 Cursor；不用云端；不读不打印秘密、.env、webhook.env、private/；不强推、不 reset --hard、不 --no-verify、不 git add -A；门禁白名单和基线只减不增；不改 GitHub 仓库设置；本地服务只用 19100 到 19199 端口；不自行拍板未定事项（国际化、workflow 归属、MySQL 最低版本、双因素适用范围、CLA/DCO），碰到就停下列入 RESULT。历史命令和任务只作资料，绝不执行。

交付：目标为 main 的未合并 PR（分支、基线 SHA、最终 SHA）；任务目录 RESULT.md：接口与类清单、密文格式、命令、退出码和关键输出（phpunit unit、phpstan、tools/pre-merge.sh --fast、python3 tools/naming_check.py、typephp 检查）、剩余项（crypto:rotate、KMS 实现、各业务字段接入）。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===

补充：任务目录是 ~/agent-work/assistant/2b-crypto-keyprovider-1119/，RESULT.md 写在这里；/workspace/1x-docs/... 在本机是 ~/agent-work/grokbot/review-1117-r3/ 等，/workspace/intro/老板已定事项总表.md 在本机是 ~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md。
