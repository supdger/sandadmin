import { describe, expect, it } from 'vitest';

import { API_ERROR_CODES } from './errorCodes';
import { API_ERROR_MESSAGES, messageForErrorCode } from './errorMessages';

describe('错误码中文', () => {
  it('每个已登记错误码都有明确中文，没有花括号和笼统词', () => {
    const codes = Object.values(API_ERROR_CODES);
    expect(Object.keys(API_ERROR_MESSAGES).sort()).toEqual([...codes].sort());
    for (const code of codes) {
      const text = API_ERROR_MESSAGES[code];
      expect(text.length, code).toBeGreaterThan(3);
      expect(text, code).not.toContain('{{');
      expect(text, code).not.toContain('name');
      expect(text, code).not.toMatch(/操作失败|冲突|失败了$/);
      expect(text, code).not.toBe(code);
    }
  });

  it('并发修改是产品定死的那一句', () => {
    expect(API_ERROR_MESSAGES.STALE_VERSION).toBe('这条刚被别人改过，刷新后再改');
    expect(API_ERROR_MESSAGES.TASK_SCHEDULE_INVALID).toBe('执行时间填写不正确');
  });

  it('没登记的码带上编号，不把英文码单独给用户', () => {
    expect(messageForErrorCode('NOT_A_CODE')).toBe('系统出了点问题，请稍后再试（NOT_A_CODE）');
  });
});
