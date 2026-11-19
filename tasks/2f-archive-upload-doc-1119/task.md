【任务】归档分段上传方案文档（只复制一个文件）

1. 从 supdger/sandadmin 分支 docs/1x-chunked-upload（头 4d4f597）取出 docs/1x-分段上传方案.md（用 git show 或 gh api 只读取出，不改该分支）。
2. 存为 ~/agent-work/grokbot/review-1117-r3/分段上传方案-1119.md（与顶层架构设计-v4.md 同目录；本机没有 /workspace/1x-docs，这个目录就是它）。已存在同名文件就停下写明，不覆盖。
3. 用 shasum -a 256 记下源与目标哈希，确认一致。
4. 在本任务目录 ~/agent-work/assistant/2f-archive-upload-doc-1119/RESULT.md 写下路径和哈希。

禁止：不改任何仓库、不推送、不删文件、不碰其他文件；不开下级会话；不用云端；不读不打印秘密。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
