import { useEffect, useState } from 'react';

/** 输入防抖：值稳定 delay 毫秒后才更新，避免每个按键都发请求。 */
export function useDebouncedValue<T>(value: T, delay = 300): T {
  const [debounced, setDebounced] = useState(value);
  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delay);
    return () => clearTimeout(timer);
  }, [value, delay]);
  return debounced;
}
