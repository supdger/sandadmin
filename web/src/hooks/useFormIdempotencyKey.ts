import { useState } from 'react';

/**
 * 同一次打开表单共用一把幂等键。关掉再打开才换新的，提交失败后重试仍用这一把。
 */
export function useFormIdempotencyKey(open: boolean): string {
  const [session, setSession] = useState(() => ({
    open,
    key: crypto.randomUUID(),
  }));
  if (open !== session.open) {
    setSession({
      open,
      key: open ? crypto.randomUUID() : session.key,
    });
  }

  return session.key;
}
