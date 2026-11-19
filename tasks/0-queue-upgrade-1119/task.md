【任务】升级本机助理任务队列（只做下面几步，不做别的）

1. 用户 LaunchAgent ~/Library/LaunchAgents/com.assistant.queue.plist：在 <dict> 里加 <key>AbandonProcessGroup</key><true/>（已有就跳过），其余不动。
2. 把 ~/agent-work/assistant/q/scripts/qwatch.sh 和 runjob.sh 复制到 ~/agent-work/assistant/ 下：先复制成同目录临时文件，再用 mv 替换（不要直接覆盖写，旧进程可能正在用）。
3. 运行 launchctl unload ~/Library/LaunchAgents/com.assistant.queue.plist，再 launchctl load 同一文件。
4. 等 10 秒，运行 launchctl list | grep com.assistant 并记录输出。
5. 在 ~/agent-work/assistant/0-queue-upgrade-1119/RESULT.md 写下每步做了什么和输出。

禁止：不改别的文件；不删任何文件；不碰 ~/.shared-skills、仓库代码、数据库；不开下级会话；不用云端；不读不打印秘密。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
