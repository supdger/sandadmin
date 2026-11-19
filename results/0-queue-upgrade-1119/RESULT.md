# Queue upgrade 1119 — 执行记录

日期：2026-11-19（macOS）

## Step 1 — plist 加 AbandonProcessGroup

- 目标文件：`~/Library/LaunchAgents/com.assistant.queue.plist`
- 结果：**跳过**。文件已有 `<key>AbandonProcessGroup</key><true/>`（第 9 行），按要求「已有就跳过」，未做任何修改。

## Step 2 — 脚本复制（先临时文件，再 mv 替换）

执行命令（在 `~/agent-work/assistant/` 下）：

```
cp q/scripts/qwatch.sh .qwatch.sh.tmp && mv .qwatch.sh.tmp qwatch.sh
cp q/scripts/runjob.sh .runjob.sh.tmp && mv .runjob.sh.tmp runjob.sh
```

校验（md5 一致）：

```
MD5 (q/scripts/qwatch.sh) = a8779121acf5a90ca28f4382358bfb4c
MD5 (qwatch.sh)           = a8779121acf5a90ca28f4382358bfb4c
MD5 (q/scripts/runjob.sh) = f0524ecd671958b52435e4a62036e13f
MD5 (runjob.sh)           = f0524ecd671958b52435e4a62036e13f
```

- `qwatch.sh` 2244 字节，`runjob.sh` 1308 字节，均与源一致。

## Step 3 — 重新加载 LaunchAgent

```
launchctl unload ~/Library/LaunchAgents/com.assistant.queue.plist  → rc=0
launchctl load   ~/Library/LaunchAgents/com.assistant.queue.plist  → rc=0
```

## Step 4 — 等 10 秒后 launchctl list

命令：`sleep 10 && launchctl list | grep com.assistant`

输出：

```
-	0	com.assistant.1x-skills-1119
-	0	com.assistant.queue
-	0	com.assistant.progress-1119
```

`com.assistant.queue` 已加载：PID 列 `-`（当前未在跑，属正常，WatchPaths/StartInterval 触发时启动），LastExitStatus `0`。

## 备注

- 未改动任何其他文件，未删除任何文件，未触碰 ~/.shared-skills、仓库代码、数据库。
- plist 内容保持原样（仅确认 AbandonProcessGroup 已存在）。
