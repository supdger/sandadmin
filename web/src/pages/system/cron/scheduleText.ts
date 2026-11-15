import i18n from '@/i18n/i18n';

import type { TaskSchedule } from '@/api/v1/schedules';
import { clockInZone, getBrowserZone, utcFromZone, zonePartsAt } from '@/lib/zonedTime';

const pad2 = (n: number) => String(n).padStart(2, '0');

/** HH:mm 规整化；非法输入返回 null。 */
export function normalizeTime(raw: string | null | undefined): string | null {
  if (!raw) return null;
  const match = /^(\d{1,2}):(\d{2})/.exec(raw);
  if (!match) return null;
  const hour = Number(match[1]);
  const minute = Number(match[2]);
  if (hour > 23 || minute > 59) return null;
  return `${pad2(hour)}:${pad2(minute)}`;
}

/**
 * 结构化 schedule → 界面上的人话（「每天 03:00」「每 10 分钟」）。
 * cron/表达式永远不出现；schedule 为 null 表示时间由系统定。
 * sourceZone 是 schedule 钟点的设置时区：给了就换算成浏览器时区
 * 再显示，weekly/monthly 换算后星期、几号可能跨到前后一天。
 */
export function scheduleText(
  schedule: TaskSchedule | null | undefined,
  sourceZone?: string | null,
  targetZone: string = getBrowserZone(),
): string {
  const t = (key: string, opts?: Record<string, unknown>) => i18n.t(key, { ns: 'cron', ...opts });
  if (!schedule) return t('schedule.bySystem');
  const clock = sourceZone ? clockInZone(schedule, sourceZone, targetZone) : null;
  switch (schedule.freq) {
    case 'interval': {
      const minutes = schedule.interval;
      return minutes && minutes > 0 ? t('schedule.interval', { count: minutes }) : t('schedule.unread');
    }
    case 'hourly': {
      const time = clock?.time ?? normalizeTime(schedule.time);
      const minute = time ? Number(time.slice(3)) : null;
      return minute != null ? t('schedule.hourly', { minute: pad2(minute) }) : t('schedule.unread');
    }
    case 'daily': {
      const time = clock?.time ?? normalizeTime(schedule.time);
      return time ? t('schedule.daily', { time }) : t('schedule.unread');
    }
    case 'weekly': {
      const time = clock?.time ?? normalizeTime(schedule.time);
      const weekday = clock?.weekday ?? schedule.weekday;
      const names = t('schedule.weekdays', { returnObjects: true }) as unknown as string[];
      return time && weekday && weekday >= 1 && weekday <= 7
        ? t('schedule.weekly', { weekday: names[weekday - 1], time })
        : t('schedule.unread');
    }
    case 'monthly': {
      const time = clock?.time ?? normalizeTime(schedule.time);
      const day = clock?.monthday ?? schedule.monthday;
      return time && day && day >= 1 && day <= 31
        ? t('schedule.monthly', { day, time })
        : t('schedule.unread');
    }
    default:
      return t('schedule.unread');
  }
}

/**
 * 按 schedule 估算接下来三次执行时间（页面预览用，与后端无关）。
 * monthly 29–31 号遇短月落到当月最后一天。
 * sourceZone 是 schedule 钟点的设置时区：给了就按它解释钟面时刻，
 * 返回的 Date 仍是瞬间，格式化时自动落到浏览器时区。
 */
export function nextRunTimes(
  schedule: TaskSchedule,
  from: Date = new Date(),
  sourceZone?: string | null,
): Date[] {
  if (sourceZone && schedule.freq !== 'interval') {
    return nextRunInZone(schedule, from, sourceZone);
  }
  const out: Date[] = [];

  const timeOf = (d: Date, hhmm: string | null | undefined) => {
    const normalized = normalizeTime(hhmm) ?? '00:00';
    d.setHours(Number(normalized.slice(0, 2)), Number(normalized.slice(3)), 0, 0);
  };

  switch (schedule.freq) {
    case 'interval': {
      const minutes = Math.max(5, schedule.interval ?? 5);
      const cursor = new Date(from.getTime() + minutes * 60_000);
      for (let i = 0; i < 3; i += 1) {
        out.push(new Date(cursor));
        cursor.setTime(cursor.getTime() + minutes * 60_000);
      }
      break;
    }
    case 'hourly': {
      const time = normalizeTime(schedule.time);
      const minute = time ? Number(time.slice(3)) : 0;
      const cursor = new Date(from);
      cursor.setMinutes(minute, 0, 0);
      if (cursor.getTime() <= from.getTime()) cursor.setHours(cursor.getHours() + 1);
      for (let i = 0; i < 3; i += 1) {
        out.push(new Date(cursor));
        cursor.setHours(cursor.getHours() + 1);
      }
      break;
    }
    case 'daily': {
      const cursor = new Date(from);
      timeOf(cursor, schedule.time);
      if (cursor.getTime() <= from.getTime()) cursor.setDate(cursor.getDate() + 1);
      for (let i = 0; i < 3; i += 1) {
        out.push(new Date(cursor));
        cursor.setDate(cursor.getDate() + 1);
      }
      break;
    }
    case 'weekly': {
      const target = schedule.weekday ?? 1; // 1=周一 … 7=周日
      const cursor = new Date(from);
      timeOf(cursor, schedule.time);
      const jsDay = (d: Date) => (d.getDay() === 0 ? 7 : d.getDay());
      cursor.setDate(cursor.getDate() + ((target - jsDay(cursor) + 7) % 7));
      if (cursor.getTime() <= from.getTime()) cursor.setDate(cursor.getDate() + 7);
      for (let i = 0; i < 3; i += 1) {
        out.push(new Date(cursor));
        cursor.setDate(cursor.getDate() + 7);
      }
      break;
    }
    case 'monthly': {
      const want = schedule.monthday ?? 1;
      const cursor = new Date(from);
      for (let i = 0; i < 3; i += 1) {
        const y = cursor.getFullYear();
        const mo = cursor.getMonth();
        const lastDay = new Date(y, mo + 1, 0).getDate();
        const day = Math.min(want, lastDay);
        const candidate = new Date(y, mo, day);
        timeOf(candidate, schedule.time);
        if (candidate.getTime() <= from.getTime() || out.some((o) => o.getTime() === candidate.getTime())) {
          cursor.setMonth(cursor.getMonth() + 1, 1);
          i -= 1;
          continue;
        }
        out.push(candidate);
        cursor.setMonth(cursor.getMonth() + 1, 1);
      }
      break;
    }
  }
  return out;
}

/** sourceZone 版：候选钟面时刻按设置时区解释成瞬间，逐日/逐小时推进。 */
function nextRunInZone(schedule: TaskSchedule, from: Date, zone: string): Date[] {
  const out: Date[] = [];
  const clock = normalizeTime(schedule.time) ?? '00:00';
  const hour = Number(clock.slice(0, 2));
  const minute = Number(clock.slice(3));
  const start = zonePartsAt(from, zone);

  if (schedule.freq === 'hourly') {
    for (let i = 0; out.length < 3 && i < 50; i += 1) {
      const at = utcFromZone(
        { year: start.year, month: start.month, day: start.day, hour: start.hour + i, minute },
        zone,
      );
      if (at.getTime() > from.getTime()) out.push(at);
    }
    return out;
  }

  for (let i = 0; out.length < 3 && i < 400; i += 1) {
    const wall = new Date(Date.UTC(start.year, start.month - 1, start.day + i));
    const year = wall.getUTCFullYear();
    const month = wall.getUTCMonth() + 1;
    const day = wall.getUTCDate();
    const weekday = wall.getUTCDay() === 0 ? 7 : wall.getUTCDay();
    const lastDay = new Date(Date.UTC(year, month, 0)).getUTCDate();
    const hit =
      schedule.freq === 'daily' ||
      (schedule.freq === 'weekly' && weekday === (schedule.weekday ?? 1)) ||
      (schedule.freq === 'monthly' &&
        day === Math.min(schedule.monthday ?? 1, lastDay));
    if (!hit) continue;
    const at = utcFromZone({ year, month, day, hour, minute }, zone);
    if (at.getTime() <= from.getTime()) continue;
    if (out.some((o) => o.getTime() === at.getTime())) continue;
    out.push(at);
  }
  return out;
}
