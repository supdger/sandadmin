import type { TFunction } from 'i18next';

function pad(n: number): string {
  return String(n).padStart(2, '0');
}

function sameDay(a: Date, b: Date): boolean {
  return (
    a.getFullYear() === b.getFullYear() &&
    a.getMonth() === b.getMonth() &&
    a.getDate() === b.getDate()
  );
}

/**
 * Spec copy: under an hour "N 分钟前", earlier today "今天 HH:mm",
 * older "MM-DD HH:mm". Empty/invalid input renders as empty string.
 */
export function formatRelativeTime(
  t: TFunction,
  value: string | null | undefined,
  now: Date = new Date(),
): string {
  if (!value) {
    return '';
  }
  const time = new Date(value).getTime();
  if (Number.isNaN(time)) {
    return '';
  }
  const diff = now.getTime() - time;
  if (diff < 60_000) {
    return t('time.justNow');
  }
  if (diff < 3_600_000) {
    return t('time.minutesAgo', { count: Math.floor(diff / 60_000) });
  }
  const date = new Date(time);
  const hm = `${pad(date.getHours())}:${pad(date.getMinutes())}`;
  if (sameDay(date, now)) {
    return t('time.todayAt', { time: hm });
  }
  return t('time.dateTime', {
    date: `${pad(date.getMonth() + 1)}-${pad(date.getDate())}`,
    time: hm,
  });
}

/** `YYYY-MM-DD` for audit-style timestamps such as password_changed_time. */
export function formatDate(value: string | null | undefined): string {
  if (!value) {
    return '';
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return '';
  }
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}
