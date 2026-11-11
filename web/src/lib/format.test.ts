import { describe, expect, it } from 'vitest';

import { formatDateTime, formatRelativeTime, maskPhone } from './format';

describe('formatDateTime', () => {
  it('formats an ISO timestamp in local time', () => {
    const iso = new Date(2026, 0, 5, 9, 7).toISOString();
    expect(formatDateTime(iso)).toBe('2026-01-05 09:07');
  });

  it('returns empty string for missing or invalid input', () => {
    expect(formatDateTime(null)).toBe('');
    expect(formatDateTime('')).toBe('');
    expect(formatDateTime('not-a-date')).toBe('');
  });
});

describe('formatRelativeTime', () => {
  const now = new Date(2026, 5, 10, 12, 0, 0);

  it('covers the relative buckets', () => {
    expect(formatRelativeTime(new Date(2026, 5, 10, 11, 59, 30).toISOString(), now)).toBe('刚刚');
    expect(formatRelativeTime(new Date(2026, 5, 10, 11, 30, 0).toISOString(), now)).toBe('30 分钟前');
    expect(formatRelativeTime(new Date(2026, 5, 10, 9, 0, 0).toISOString(), now)).toBe('3 小时前');
    expect(formatRelativeTime(new Date(2026, 5, 9, 9, 0, 0).toISOString(), now)).toBe('昨天');
    expect(formatRelativeTime(new Date(2026, 5, 5, 9, 0, 0).toISOString(), now)).toBe('5 天前');
    expect(formatRelativeTime(new Date(2026, 4, 20, 9, 0, 0).toISOString(), now)).toBe('2026-05-20');
  });

  it('returns empty string for missing or invalid input', () => {
    expect(formatRelativeTime(null, now)).toBe('');
    expect(formatRelativeTime('garbage', now)).toBe('');
  });
});

describe('maskPhone', () => {
  it('masks an 11-digit mobile number', () => {
    expect(maskPhone('13812345678')).toBe('138****5678');
  });

  it('keeps the last 4 digits for other lengths', () => {
    expect(maskPhone('1234567890')).toBe('123****7890');
    // 去掉分隔符后仍是 11 位，按手机号处理。
    expect(maskPhone('0755-1234567')).toBe('075****4567');
  });

  it('returns empty string for missing input', () => {
    expect(maskPhone(null)).toBe('');
    expect(maskPhone('')).toBe('');
  });
});
