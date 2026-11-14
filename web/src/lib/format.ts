/**
 * 列表页共用的展示格式化。输出一律为简体中文；
 * 空输入的兜底文案由调用方决定。
 */

function pad2(n: number): string {
  return n < 10 ? `0${n}` : String(n);
}

/** 带时区的 ISO 8601 → 本地时间 `YYYY-MM-DD HH:mm`；非法输入返回 ''。 */
export function formatDateTime(iso: string | null | undefined): string {
  if (!iso) {
    return '';
  }
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) {
    return '';
  }
  return `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())} ${pad2(date.getHours())}:${pad2(date.getMinutes())}`;
}

/**
 * 「最近登录」类列的相对时间：刚刚 / N 分钟前 / N 小时前 /
 * 昨天 / N 天前；超过一周输出 `YYYY-MM-DD`。空或无法解析返回 ''。
 */
export function formatRelativeTime(iso: string | null | undefined, now: Date = new Date()): string {
  if (!iso) {
    return '';
  }
  const date = new Date(iso);
  const time = date.getTime();
  if (Number.isNaN(time)) {
    return '';
  }
  const diffMs = now.getTime() - time;
  if (diffMs < 0) {
    return formatDateTime(iso).slice(0, 10);
  }
  const minutes = Math.floor(diffMs / 60_000);
  if (minutes < 1) {
    return '刚刚';
  }
  if (minutes < 60) {
    return `${minutes} 分钟前`;
  }
  const hours = Math.floor(minutes / 60);
  if (hours < 24) {
    return `${hours} 小时前`;
  }
  const days = Math.floor(hours / 24);
  if (days === 1) {
    return '昨天';
  }
  if (days < 7) {
    return `${days} 天前`;
  }
  return formatDateTime(iso).slice(0, 10);
}

/**
 * 容量人话：1024 进制一位小数，不足 1 GB 用 MB；
 * 整数省掉「.0」。非法输入返回 ''。
 */
export function formatGb(gb: number | null | undefined): string {
  if (gb == null || !Number.isFinite(gb) || gb < 0) {
    return '';
  }
  if (gb < 1) {
    return `${Math.round(gb * 1024)} MB`;
  }
  const rounded = Math.round(gb * 10) / 10;
  return `${Number.isInteger(rounded) ? String(rounded) : rounded.toFixed(1)} GB`;
}

/**
 * 表格展示用的手机号脱敏（`138****8000`）。
 * 非 11 位的号码保留末 4 位可见。
 */
export function maskPhone(phone: string | null | undefined): string {
  if (!phone) {
    return '';
  }
  const digits = phone.replace(/\D/g, '');
  if (digits.length === 11) {
    return `${digits.slice(0, 3)}****${digits.slice(7)}`;
  }
  if (digits.length > 7) {
    return `${digits.slice(0, digits.length - 7)}****${digits.slice(-4)}`;
  }
  return phone;
}
