# 常用弱密码命中表

`weak-passwords.txt` 是随包分发的常用弱密码表：新密码命中即拒绝（C-18 密码策略的一部分）。运行时进程启动时载入内存比对，不提供查询或导出接口。

## 来源

- SecLists `Passwords/Common-Credentials/100k-most-used-passwords-NCSC.txt`
- SecLists `Passwords/Common-Credentials/Language-Specific/Chinese-common-password-list-top-100000.txt`

上游仓库：https://github.com/danielmiessler/SecLists ，MIT License（同目录 `LICENSE.SecLists.txt` 为许可证原文）。

两张表均取自上游 commit `6b19c2f5088c45b5dadb4d8918d2ab4f7efb03a9`（master 分支 pin 住，防止上游变动导致产物漂移）。

## 生成方法

```
php tools/build-weak-passwords.php            # 按 pin 住的 commit 下载两张源表后合并
php tools/build-weak-passwords.php --src-dir <目录>   # 或用本地副本（文件名与上游一致）
```

合并规则：中文表按源文件顺序只取前 20000 条；两张表统一去空白行与注释行、剔除含空白字符、含 `@`、以及像手机号、身份证号、QQ 号的条目；只保留长度 ≥12 的条目（密码最小 12 位，更短的候选由长度检查先行拒绝）；统一转小写后排序去重。脚本可重复运行，产出幂等，结束时打印行数与 sha256。
