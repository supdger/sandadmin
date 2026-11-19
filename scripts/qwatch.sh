#!/bin/bash
# 助理任务队列 v2：launchd com.assistant.queue 每 5 分钟调用；任务在 GitHub supdger/sandadmin 分支 assistant-queue 的 tasks/<名>/，结果推回 results/<名>/。最多同时跑 2 项。脚本自动从 scripts/ 更新。
A=~/agent-work/assistant; Q=$A/q; MAX=2; cd "$A" || exit 0
export LC_ALL=en_US.UTF-8
G=~/agent-work/grokbot/交接-实时进度.md
lock() { until mkdir "$A/.gitlock" 2>/dev/null; do sleep 2; done; }
unlock() { rmdir "$A/.gitlock" 2>/dev/null; }
lock
if [ ! -d "$Q/.git" ]; then git clone -q -b assistant-queue https://github.com/supdger/sandadmin "$Q"; fi
git -C "$Q" pull -q --ff-only origin assistant-queue
unlock
# 自更新（mv 原子替换，不影响正在跑的旧进程）
for s in qwatch.sh runjob.sh; do
  if [ -e "$Q/scripts/$s" ] && ! cmp -s "$Q/scripts/$s" "$A/$s"; then cp "$Q/scripts/$s" "$A/.$s.new" && mv "$A/.$s.new" "$A/$s"; fi
done
hq() { # 结果推回后追加一段到 grokbot 实时进度（时间/任务名/状态/结论/结果路径）
  n=$1; f=$Q/results/$n/RESULT.md; st=done
  tail -1 "$A/$n/status.log" 2>/dev/null | grep -q "gave up" && st=failed
  if [ ! -e "$G" ]; then
    printf '# 助理队列实时进度\n\n由助理队列自动维护，grokbot 开工先读；汇报口径见 交接-进度汇报口径-1119.md 和 交接-给老板汇报规范-1119.md\n' > "$G.new" && mv "$G.new" "$G"
  fi
  {
    printf '\n## %s · %s · %s\n' "$(date '+%Y-%m-%d %H:%M')" "$n" "$st"
    if [ -e "$f" ]; then
      s=$(awk 'c&&i>0&&(!NF||/^#/){exit} c&&i>0{print;i++;if(i>=5)exit;next} c&&/^#/{exit} c&&NF{print;i++;next} c{next} /^#+.*结论/{c=1}' "$f")
      [ -n "$s" ] || s=$(awk '{if(i>0&&(!NF||/^#/))exit; if(!NF||/^#/)next; print;i++;if(i==5)exit}' "$f")
      if [ -n "$s" ]; then printf '%s\n' "$s"; else echo '（RESULT.md 无正文）'; fi
    else echo '（无 RESULT.md）'; fi
    echo "结果：$Q/results/$n/"
  } >> "$G"
}
publish() {
  n=$1; lock; R=$Q/results/$n; mkdir -p "$R"
  for x in RESULT.md status.log; do [ -e "$A/$n/$x" ] && cp "$A/$n/$x" "$R/"; done
  [ -e "$A/$n/run.log" ] && tail -c 6000 "$A/$n/run.log" > "$R/run-tail.log"
  git -C "$Q" add "results/$n"
  git -C "$Q" -c user.name=assistant-queue -c user.email=queue@localhost commit -q -m "result: $n" || true
  git -C "$Q" pull -q --rebase origin assistant-queue; git -C "$Q" push -q origin assistant-queue
  unlock; date > "$A/$n/.published"; hq "$n"
}
running() { c=0; for p in "$A"/*/.pid; do [ -e "$p" ] || continue; d=$(dirname "$p"); [ -e "$d/.published" ] && continue; kill -0 "$(cat "$p")" 2>/dev/null && c=$((c+1)); done; echo $c; }
# 补发：跑完但没推结果的
for d in "$A"/*/; do n=$(basename "$d"); [ -e "$d/.launched" ] || continue; [ -e "$d/.published" ] && continue
  grep -q "done $n\|gave up" "$d/status.log" 2>/dev/null || continue
  if [ -e "$d/.pid" ] && kill -0 "$(cat "$d/.pid")" 2>/dev/null; then continue; fi
  publish "$n"; done
for t in "$Q"/tasks/*/; do
  [ -e "$t/task.md" ] || continue; n=$(basename "$t")
  [ -e "$A/$n/.launched" ] && continue
  [ "$(running)" -ge $MAX ] && break
  mkdir -p "$A/$n"; cp "$t/task.md" "$A/$n/task.md"; [ -e "$t/model.txt" ] && cp "$t/model.txt" "$A/$n/model.txt"
  date > "$A/$n/.launched"
  ( bash "$A/runjob.sh" "$n"; ) >/dev/null 2>&1 &
  echo $! > "$A/$n/.pid"
done
exit 0
