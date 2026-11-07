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
    return storage()?.getItem(STORAGE_KEY) ?? null;
  } catch {
    return null;
  }
}

export function setToken(t: string): void {
  try {
    storage()?.setItem(STORAGE_KEY, t);
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
