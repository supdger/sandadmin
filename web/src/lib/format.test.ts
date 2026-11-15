import { describe, expect, it } from 'vitest';

import { formatDateTime, formatGb, formatRelativeTime, maskPhone } from './format';

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

describe('formatGb', () => {
  it('不足 1 GB 用 MB，整数 GB 不带小数', () => {
    expect(formatGb(0.5)).toBe('512 MB');
    expect(formatGb(1)).toBe('1 GB');
    expect(formatGb(16)).toBe('16 GB');
    expect(formatGb(100)).toBe('100 GB');
  });

  it('1024 进制且 GB 保留一位小数', () => {
    expect(formatGb(14.5)).toBe('14.5 GB');
    expect(formatGb(4.24)).toBe('4.2 GB');
    // 1536 MB = 1.5 GB（1024 进制，不是 1000）。
    expect(formatGb(1.5)).toBe('1.5 GB');
    expect(formatGb(0.99)).toBe('1014 MB');
  });

  it('非法输入返回空串', () => {
    expect(formatGb(null)).toBe('');
    expect(formatGb(undefined)).toBe('');
    expect(formatGb(-1)).toBe('');
    expect(formatGb(Number.NaN)).toBe('');
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
