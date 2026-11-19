# RESULT — 1.x 框架密钥管理接口（KeyProvider）与 Crypto 加密服务，无库部分（2026-11-19）

- **PR**：https://github.com/supdger/sandadmin/pull/102（`feat/crypto-keyprovider` → `main`，未合并）
- **基线 SHA**：`977427acc285da074a443e8206309289a9bd94fb`（origin/main，开工时 fetch）
- **最终 SHA**：`2b5125d`（`feat(crypto): 密钥管理接口与 AES-256-GCM 字段加密服务（N5/M11 无库部分）`，9 文件 +638 行）
- 依据：v4 第 614 行 M11、第 902–911 行 N5、J-10 节；鉴权P0-v4 WP8（SAND_CRYPTO_KEY 派生体系、LOG-03 脱敏口径）；总表第 11 条 / R-7（单租户组织号 `'default'`）。

## 1. 接口与类清单

| 文件 | 内容 |
| --- | --- |
| `server/src/Domain/Crypto/KeyProviderInterface.php` | 信封加密端口（J-10）：`currentKeyVersion()`、`masterKey(string $keyVersion)`（按 keyver 取 32 字节主密钥，版本不存在抛 CryptoException）、`wrapKey(string $secret, string $orgId='default')`、`unwrapKey(string $wrapped, string $orgId='default')`（数据密钥加解密，orgId 绑定进包裹密文）。KMS/Vault 替换点唯一，本批不接真实服务。`DEFAULT_ORG='default'`。 |
| `server/src/Domain/Crypto/CryptoException.php` | 内部错误类型（不映射契约错误码）：`missingMasterKey`、`invalidMasterKey`、`emptySecret`、`unknownKeyVersion`、`encryptFailed`、`decryptFailed`；文案不带密钥/密文内容。 |
| `server/src/Infra/Crypto/GcmCipher.php` | `@internal` AES-256-GCM 信封编解码：`parse`（拆 keyver/nonce/ct/tag 并校验）、`seal`、`open`；nonce 12B、tag 16B、key 32B。 |
| `server/src/Infra/Crypto/EnvKeyProvider.php` | env 默认实现：`fromEnv()`（读 `SAND_CRYPTO_KEY`/`SAND_CRYPTO_PREVIOUS_KEYS`）、`fromSpec()`（测试/装配用，不读 env）；条目 `v<序号>=<base64 32B>`；缺失/格式错/长度错/版本重复装配即拒绝；wrapKey 把 orgId 作 GCM AAD。 |
| `server/src/Infra/Crypto/Crypto.php` | 字段级加密服务：`encrypt`（当前 keyver）、`decrypt`（按密文 keyver 取主密钥，覆盖旧密钥轮换）。 |
| `server/tests/Unit/Infra/Crypto/{CryptoTest,EnvKeyProviderTest}.php` | 21 用例；测试密钥测试内 `random_bytes` 现场生成，不读任何真实 .env。 |
| `server/docs/crypto-encrypted-fields.md` | 需加密字段清单（SMTP 密码、对象存储 SecretKey、AI provider key、插件声明的第三方凭证、验证码派生场景、Webhook 端点密钥）＋密钥来源＋轮换口径＋未做事项。 |
| `server/.env.example` | 只加键名与生成命令，不加值（`SAND_CRYPTO_KEY`、`SAND_CRYPTO_PREVIOUS_KEYS`，注释掉）。 |

## 2. 密文格式

`v<keyver>:<nonce>:<ct>:<tag>` —— 首段密钥版本号（如 `v1`），后三段二进制各 base64；AES-256-GCM（openssl），nonce 随机 12B、tag 16B。env 条目格式 `v<序号>=<base64 的 32 字节>`，旧密钥列表逗号分隔。新写入用当前 keyver；解密按密文 keyver 查表（当前＋PREVIOUS_KEYS）。

## 3. 命令、退出码与关键输出

| 命令 | 退出码 | 关键输出 |
| --- | --- | --- |
| `cd server && vendor/bin/phpunit --testsuite unit` | 0 | `OK (429 tests, 2651 assertions)`（含新增 Crypto 21 用例 38 断言：格式断言、篡改 tag/ct/nonce 拒绝、旧 keyver 可解、新写入用新 keyver、密钥缺失/长度不对/畸形条目拒绝、org 绑定不符拒绝） |
| `cd server && vendor/bin/phpstan analyse --no-progress` | 0 | `[OK] No errors`（level max） |
| `bash tools/pre-merge.sh --fast` | 0 | `pre-merge --fast 全部通过`（php -l、contract-build --check、危险函数白名单、dev-only 引用、typephp_check diff、phpunit、phpstan 七步全过） |
| `python3 tools/naming_check.py code|notes|terms <新文件>` | 0 | FAIL 0、WARN 0（首轮 WARN 已修：`$dataKey`→`$secret`、`$m`→`$match`、`$badList`→`$malformedInputs`、`invalidDataKey`→`emptySecret`） |
| `php tools/typephp_check.php` | 0（随 pre-merge） | 新文件 0 命中 |
| `python3 ~/.codex/tools/scan_change_quality.py --repository-review` | 0 | 已按 prepare→record 记录与暂存内容绑定的审查结论 |

验证方式说明：测试/静态检查覆盖契约——`v<keyver>` 格式断言、篡改拒绝、旧 keyver 轮换解密、新写入新 keyver、密钥缺失或长度不对拒绝；未启任何数据库/Redis，未跑迁移。

## 4. 环境处置记录（非交付物）

- 本机 `server/vendor` 原先按 `--no-dev` 安装，缺 `symfony/yaml`、`mcp/sdk`，导致 phpunit 3 个 Contract 用例与 phpstan 20 条 class.notFound 误报；已 `composer install` 补齐后全绿。
- `composer install` 的 `sand:publish` 会重写 `server/webman`（仓库已注明该行为），已 `git checkout` 还原，未进交付差异。
- 本机 `main` 引用过期（7d80aea，1.x 之前）导致 typephp_check 的 `main...HEAD` diff 误扫存量代码报 25 条既有 FAIL；`git update-ref refs/heads/main origin/main` 快进到 origin/main 后 diff 归零命中。该 25 条为 origin/main 既有命中、与本次改动无关。

## 5. 剩余项（本批范围外）

- `bin/sand crypto:rotate`：遍历存量密文按新主密钥重加密（需要数据库，未做）。
- KMS/Vault 真实实现：`KeyProviderInterface` 已立为唯一替换点。
- 各业务字段读写点接入（SMTP、对象存储、AI key、插件凭证、Webhook 密钥、验证码派生子密钥 HKDF/AUTH-10）。
- 密钥保管/轮换/丢失处置的部署文档正文（本批在字段清单文档内登记口径）。

## 6. 未定事项

无触碰：未涉及国际化、workflow 归属、MySQL 最低版本、双因素适用范围、CLA/DCO 等需拍板事项。
