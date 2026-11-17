const STORAGE_KEY = 'sandadmin.token';

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

export function getToken(): string | null {
  try {
    const value = storage()?.getItem(STORAGE_KEY) ?? null;
    // 旧版本曾把 "undefined" 字符串写进 storage，按未登录处理并拒绝回读。
    return value !== null && value !== 'undefined' ? value : null;
  } catch {
    return null;
  }
}

// 令牌缺失（含后端响应缺字段导致的 undefined）等价于清令牌；
// 绝不把 "undefined" 字符串写进 storage。
export function setToken(token: string | null | undefined): void {
  try {
    if (!token) {
      storage()?.removeItem(STORAGE_KEY);
      return;
    }
    storage()?.setItem(STORAGE_KEY, token);
  } catch {
    // storage unavailable — token lives in memory of the caller only
  }
}

export function clearToken(): void {
  try {
    storage()?.removeItem(STORAGE_KEY);
  } catch {
    // storage unavailable — nothing to clear
  }
}
