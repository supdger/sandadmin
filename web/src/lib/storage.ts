/**
 * UI 偏好本地存储封装：只存界面偏好（主题、折叠、布局等），
 * 会话令牌、票据、密码禁止进 localStorage——Cookie 会话制下
 * 前端不持久化令牌是硬规矩。
 * key 统一带 `sandadmin.` 前缀；读出的值按 unknown 收窄后用，
 * JSON 解析失败退回原始字符串再校验，兼容旧版裸字符串取值。
 */

const PREFIX = 'sandadmin.';

function storage(): Storage | null {
  if (typeof window === 'undefined') {
    return null;
  }
  try {
    return window.localStorage;
  } catch {
    return null;
  }
}

/** 读出偏好值；存储不可用、键不存在或校验失败时返回 fallback。 */
export function loadPref<T>(key: string, fallback: T, isValid?: (value: unknown) => value is T): T {
  let raw: string | null = null;
  try {
    raw = storage()?.getItem(`${PREFIX}${key}`) ?? null;
  } catch {
    return fallback;
  }
  if (raw === null) {
    return fallback;
  }
  let value: unknown;
  try {
    value = JSON.parse(raw);
  } catch {
    value = raw;
  }
  if (isValid) {
    return isValid(value) ? value : fallback;
  }
  return value as T;
}

// 字符串保持裸写：与旧版主题等既有取值兼容（它们存的就是
// 裸字符串），读出时 loadPref 先按 JSON 解析、失败按原文用。
export function savePref(key: string, value: unknown): void {
  try {
    storage()?.setItem(`${PREFIX}${key}`, typeof value === 'string' ? value : JSON.stringify(value));
  } catch {
    // 存储不可用时偏好只活在当前会话
  }
}

export function removePref(key: string): void {
  try {
    storage()?.removeItem(`${PREFIX}${key}`);
  } catch {
    // 同上，无存储可清
  }
}
