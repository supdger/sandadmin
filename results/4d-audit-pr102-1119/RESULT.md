可合并

# PR #102 合并前独立审计结论

- PR：supdger/sandadmin #102「feat(crypto): KeyProvider 密钥管理接口 + AES-256-GCM 字段加密（N5/M11 无库部分）」
- 分支：feat/crypto-keyprovider → main；head 2b5125d4d1c3c294495e7b42d7af2b4b7ecde107；1 个 commit，+638/-0
- 本地取证仓库：~/agent-work/pub-sandadmin
- 审计方式：只读。取证由 2 个 dv-scout（SWE-2）子代理完成，结论由主代理判断。没有修改、提交、推送、评论或合并。

## 1. 改动范围：通过
共 9 个文件，都在 crypto 模块、它的测试、文档和 env 模板之内，没有超范围文件：
- 源码：server/src/Domain/Crypto/{CryptoException,KeyProviderInterface}.php，server/src/Infra/Crypto/{Crypto,EnvKeyProvider,GcmCipher}.php
- 测试：server/tests/Unit/Infra/Crypto/{CryptoTest,EnvKeyProviderTest}.php
- 文档：server/docs/crypto-encrypted-fields.md
- 配置模板：server/.env.example（只在末尾加了注释掉的键名 SAND_CRYPTO_KEY / SAND_CRYPTO_PREVIOUS_KEYS 和生成命令，没有任何值）

## 2. 安全：通过（无阻断项）
- 秘密处理：没有硬编码的密钥、IV 或盐。测试用的密钥都在运行时用 random_bytes(32) 生成。密钥只从环境变量读取（EnvKeyProvider.php:34-42），解码后强制校验为 32 字节（:115-117）。异常信息都是固定文案，不带密钥、明文或密文。
- 加密实现：aes-256-gcm，属于 AEAD（GcmCipher.php:25）。nonce 每次用 random_bytes(12) 生成。tag 强制校验为 16 字节，由 OpenSSL 在解密时验证。格式错误、内容被篡改、密钥不对，都统一走 decryptFailed。按密文里的 keyver 选密钥，支持轮换；keyver 重复会被拒绝。
- 权限：没有新增接口、路由或命令，也不读写密钥文件。
- 注入：diff 里没有 SQL、shell 调用或路径拼接；解析用的正则首尾都已锚定（\A...\z）。
- 日志泄露：新代码里没有 log、print 或 var_dump。
- 测试覆盖：篡改 tag、密文、nonce，6 种格式错误输入，未知 keyver，缺少或非法密钥，keyver 重复，轮换，跨组织的包裹密钥拒绝解开，都有用例。

## 3. 门禁文件只减不增：通过
没有改动任何门禁文件（.github/workflows、tools/pre-merge.sh、tools/*check*、phpstan.neon.dist、baseline、豁免和白名单都不在 diff 里）。diff 中没有 phpcs、phpstan-ignore 这类忽略注释，也没有新增 baseline 或豁免条目。没有新依赖：composer.json、composer.lock、package.json 都没改，只用了 PHP 内置的 openssl_* 和 random_bytes。

## 4. 与《老板已定事项总表》：没有冲突
总表里没有专门针对密钥、Crypto 的拍板条目。与相关条目的对照：
- 第 11 条（框架是单租户）：一致。接口保留了 orgId，但单租户下固定为 DEFAULT_ORG，只是留了扩展口子。
- 第 10 条（框架和插件分开）：一致。代码放在宿主的 Domain/Infra 命名空间下。
- 第 2 条（PG/MySQL）：不涉及。本 PR 没有任何库、迁移或 SQL。
- 第 41 条（纯技术选型由技术总监定）：一致。AES-256-GCM 没有偏离惯例，也没加外部依赖。
- 第 24 条（必须有自动测试）：一致。新增 2 个测试文件，约 21 个用例。
- 表名前缀：没有建表；文档里提到的表名 sand_system_captcha 前缀正确。

## 5. gh pr checks 102：全绿
composer audit、frontend prod audit、release self-check 各有 2 次运行，6 条全部 pass。mergeable=MERGEABLE，mergeStateStatus=CLEAN，isDraft=false。

## 不阻断合并的建议（后续批次再考虑）
1. Crypto::encrypt/decrypt 字段加密传的是 aad=''（Crypto.php:26,33），字段密文没有绑定表、字段或记录。理论上可以把一个字段的密文挪到另一个字段。建议接入落库时把"表.字段"或记录 ID 作为 AAD 带进去。
2. GcmCipher::parse 对输入长度没有上限（GcmCipher.php:37-53），建议加一个合理上限。
3. server/composer.json 没有声明 ext-openssl，建议补上 require "ext-openssl"。
4. "nonce 唯一"只间接测了"两次加密结果不同"；同一 keyver 用错密钥解密，也没有单独的用例。可以考虑补测。

## 本次没核到的
- PR 描述里说的 phpunit 429 个用例全过、phpstan 0 错误、pre-merge.sh --fast 通过，本次没有重新跑（只读审计）。CI 全绿只作为旁证。
- Crypto 在依赖注入、装配层怎么注册不在本 PR 内，部署环境里缺密钥时的行为没有验证。
