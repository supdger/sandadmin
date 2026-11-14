import type { TaskSchedule } from '@/api/v1/schedules';

/*
 * 时区换算（不引第三方库）：Intl.DateTimeFormat 读指定时区下的日历字段；
 * 「先按 UTC 猜、再按偏移校正」把某时区的钟面时刻还原成 UTC 瞬间。
 */

export interface ZoneParts {
  year: number;
  month: number;
  day: number;
  hour: number;
  minute: number;
  /** 1=周一 … 7=周日，与 schedule.weekday 同口径。 */
  weekday: number;
}

/** 换算后的钟面：scheduleText/预览只关心这三个读出值。 */
export interface ZoneClock {
  /** HH:mm。 */
  time: string;
  /** 1=周一 … 7=周日。 */
  weekday: number;
  /** 几号（可能跨到前后一天）。 */
  monthday: number;
}

const formatters = new Map<string, Intl.DateTimeFormat>();

function formatterFor(zone: string): Intl.DateTimeFormat {
  let fmt = formatters.get(zone);
  if (!fmt) {
    fmt = new Intl.DateTimeFormat('en-US', {
      timeZone: zone,
      year: 'numeric',
      month: 'numeric',
      day: 'numeric',
      hour: 'numeric',
      minute: 'numeric',
      hour12: false,
      weekday: 'short',
    });
    formatters.set(zone, fmt);
  }
  return fmt;
}

const WEEKDAYS: Record<string, number> = {
  Sun: 7,
  Mon: 1,
  Tue: 2,
  Wed: 3,
  Thu: 4,
  Fri: 5,
  Sat: 6,
};

/** 瞬间在 zone 时区读出的日历字段；en-US 的 24 点归一成 0。 */
export function zonePartsAt(instant: Date, zone: string): ZoneParts {
  const bag: Record<string, string> = {};
  for (const part of formatterFor(zone).formatToParts(instant)) {
    bag[part.type] = part.value;
  }
  return {
    year: Number(bag.year),
    month: Number(bag.month),
    day: Number(bag.day),
    hour: Number(bag.hour) % 24,
    minute: Number(bag.minute),
    weekday: WEEKDAYS[bag.weekday] ?? 1,
  };
}

/** 当前浏览器时区的 IANA 名（如 Asia/Shanghai）。 */
export function getBrowserZone(): string {
  return Intl.DateTimeFormat().resolvedOptions().timeZone;
}

/** zone 时区的钟面时刻 → UTC 瞬间；年月日时分可以越界（Date.UTC 会进位）。 */
export function utcFromZone(
  wall: { year: number; month: number; day: number; hour: number; minute: number },
  zone: string,
): Date {
  const target = Date.UTC(wall.year, wall.month - 1, wall.day, wall.hour, wall.minute);
  let guess = target;
  for (let i = 0; i < 3; i += 1) {
    const at = zonePartsAt(new Date(guess), zone);
    const shown = Date.UTC(at.year, at.month - 1, at.day, at.hour, at.minute);
    const next = guess + (target - shown);
    if (next === guess) break;
    guess = next;
  }
  return new Date(guess);
}

const pad2 = (n: number) => String(n).padStart(2, '0');

/** 瞬间在 zone 时区读出的 `YYYY-MM-DD HH:mm`；与 formatDateTime 同格式、时区可指定。 */
export function formatInZone(instant: Date, zone: string): string {
  const at = zonePartsAt(instant, zone);
  return `${at.year}-${pad2(at.month)}-${pad2(at.day)} ${pad2(at.hour)}:${pad2(at.minute)}`;
}

function parseClock(raw: string | null | undefined): { hour: number; minute: number } | null {
  const match = /^(\d{1,2}):(\d{2})/.exec(raw ?? '');
  if (!match) return null;
  const hour = Number(match[1]);
  const minute = Number(match[2]);
  if (hour > 23 || minute > 59) return null;
  return { hour, minute };
}

/** 取「sourceZone 今天起第 offset 天」的钟面日期（Date.UTC 进位后回读）。 */
function wallDayAfter(base: ZoneParts, offset: number): { year: number; month: number; day: number; weekday: number } {
  const wall = new Date(Date.UTC(base.year, base.month - 1, base.day + offset));
  return {
    year: wall.getUTCFullYear(),
    month: wall.getUTCMonth() + 1,
    day: wall.getUTCDate(),
    weekday: wall.getUTCDay() === 0 ? 7 : wall.getUTCDay(),
  };
}

function lastDayOfMonth(year: number, month: number): number {
  return new Date(Date.UTC(year, month, 0)).getUTCDate();
}

/**
 * 把 schedule 的一次代表性发生时刻（按 sourceZone 解释）换算成
 * targetZone 读出的钟点/星期/几号。interval 与非法时间返回 null。
 */
export function clockInZone(
  schedule: TaskSchedule,
  sourceZone: string,
  targetZone: string = getBrowserZone(),
): ZoneClock | null {
  const now = zonePartsAt(new Date(), sourceZone);
  const read = (wall: { year: number; month: number; day: number; hour: number; minute: number }): ZoneClock => {
    const at = zonePartsAt(utcFromZone(wall, sourceZone), targetZone);
    return { time: `${pad2(at.hour)}:${pad2(at.minute)}`, weekday: at.weekday, monthday: at.day };
  };

  switch (schedule.freq) {
    case 'hourly': {
      const clock = parseClock(schedule.time);
      if (!clock) return null;
      // 小时数对换算结果无影响，用正午防 DST 边界。
      return read({ year: now.year, month: now.month, day: now.day, hour: 12, minute: clock.minute });
    }
    case 'daily': {
      const clock = parseClock(schedule.time);
      if (!clock) return null;
      return read({ year: now.year, month: now.month, day: now.day, hour: clock.hour, minute: clock.minute });
    }
    case 'weekly': {
      const clock = parseClock(schedule.time);
      const weekday = schedule.weekday;
      if (!clock || weekday == null || weekday < 1 || weekday > 7) return null;
      const offset = (weekday - now.weekday + 7) % 7;
      const day = wallDayAfter(now, offset);
      return read({ ...day, hour: clock.hour, minute: clock.minute });
    }
    case 'monthly': {
      const clock = parseClock(schedule.time);
      const monthday = schedule.monthday;
      if (!clock || monthday == null || monthday < 1 || monthday > 31) return null;
      // 找一个放得下该日的月份（29–31 遇短月顺延），再读换算后的几号。
      for (let i = 0; i < 14; i += 1) {
        const first = new Date(Date.UTC(now.year, now.month - 1 + i, 1));
        const year = first.getUTCFullYear();
        const month = first.getUTCMonth() + 1;
        if (monthday <= lastDayOfMonth(year, month)) {
          return read({ year, month, day: monthday, hour: clock.hour, minute: clock.minute });
        }
      }
      return null;
    }
    default:
      return null;
  }
}
