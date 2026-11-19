# 字段加密清单与密钥管理（N5 / M11）

第三方凭证一旦随数据库备份或从库泄露会全部失守，因此下列字段**入库前必须经
`Sandadmin\Infra\Crypto\Crypto` 加密**，密文格式 `v<keyver>:<nonce>:<ct>:<tag>`
（AES-256-GCM，后三段 base64）。

## 需加密字段清单

| 字段 | 所在位置 | 说明 |
| --- | --- | --- |
| SMTP 密码 | 邮件/通知渠道配置 | 发信账号口令 |
| 对象存储 SecretKey | 文件存储配置 | 对象存储访问密钥的 Secret 部分 |
| AI provider key | AI 服务商配置 | 各 AI 服务商 API Key |
| 插件声明的第三方凭证 | 插件扩展点登记的凭证字段 | 插件经契约声明、由宿主代管的第三方密钥 |
| 验证码答案派生场景 | `sand_system_captcha.answer_ciphertext` | 走主密钥 HKDF 派生子密钥体系（AUTH-10），同受本清单约束 |
| Webhook 端点密钥 | 出站 Webhook 端点配置（WH-02） | 端点签名/认证密钥，接入时登记 |

新增第三方凭证字段一律先补进本清单再落库；明文字段不允许入表。

## 密钥来源

- `SAND_CRYPTO_KEY`：当前主密钥，格式 `v<序号>=<base64 编码的 32 字节>`。
- `SAND_CRYPTO_PREVIOUS_KEYS`：旧主密钥列表，逗号分隔同格式条目，只用于解密旧 keyver 密文。
- 密钥只从环境变量读，**不落库、不进日志、不进仓库**；缺失或不是 32 字节时
  `EnvKeyProvider` 装配即拒绝。生成：`php -r "echo 'v1=' . base64_encode(random_bytes(32)) . PHP_EOL;"`。
- KMS / Vault：`KeyProviderInterface`（`Domain/Crypto`）是唯一替换点，
  实现信封加密语义即可换掉 env 实现，业务侧不感知。

## 轮换口径

1. 生成新条目（如 `v2=<base64>`）写入 `SAND_CRYPTO_KEY`；
2. 旧条目移入 `SAND_CRYPTO_PREVIOUS_KEYS`（与既有旧条目逗号相连）；
3. 旧密文按 keyver 继续可解，新写入自动用新 keyver；
4. 存量密文重加密由 `bin/sand crypto:rotate` 承担（尚未实现，见「未做事项」）。

密钥丢失意味着对应 keyver 的密文不可恢复，只能重新录入凭证——
密钥保管与灾难处置按部署手册执行；密文与密钥材料统一走 S1 脱敏器核查。

## 未做事项（本批范围外）

- `bin/sand crypto:rotate`：遍历存量密文按新主密钥重加密（需要数据库，另行排期）。
- KMS / Vault 的真实实现（本批只立 `KeyProviderInterface`）。
- 上表各业务字段的实际接入改造（读写点切换）。
