#!/bin/bash
# usage: runjob.sh <name>   (task file ~/agent-work/assistant/<name>/task.md)
export LC_ALL=en_US.UTF-8
D=/Applications/Devin.app/Contents/Resources/app/extensions/windsurf/devin/bin/devin
name=$1; W=~/agent-work/assistant/$name; cd "$W" || exit 1
log=run.log; tries=0
M=""; [ -s model.txt ] && M="--model $(cat model.txt)"
echo "[$(date)] start $name" >> status.log
$D $M --permission-mode dangerous --respect-workspace-trust false -p --prompt-file task.md >> "$log" 2>&1
while ! grep -q "=== RUN COMPLETE ===" "$log"; do
  tries=$((tries+1))
  if [ $tries -gt 8 ]; then echo "[$(date)] gave up after $tries" >> status.log; exit 1; fi
  if tail -c 2000 "$log" | grep -qi "rate limit\|quota"; then sleep 900; else sleep 60; fi
  cat > resume.md <<P
# 续跑（第 $tries 次）：上一轮被打断
任务文件：$W/task.md（要求、约束、完成标记照旧有效，必须完整读）。
进度日志：$W/run.log（读末尾，弄清已完成到哪一步，不重复已完成的工作）。
从中断处继续，直到全部完成，最后单独一行输出 === RUN COMPLETE ===。
P
  echo "[$(date)] resume #$tries" >> status.log
  $D $M --permission-mode dangerous --respect-workspace-trust false -p --prompt-file resume.md >> "$log" 2>&1
done
echo "[$(date)] done $name" >> status.log
