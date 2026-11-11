import { describe, expect, it } from 'vitest';

import { buildUrlQuery, parseUrlQuery } from './urlQuery';

const fields = {
  keyword: {},
  status: { defaultValue: 'enabled' },
  page: { kind: 'number' as const, defaultValue: 1 },
  page_size: { kind: 'number' as const, defaultValue: 20 },
  mine: { kind: 'boolean' as const, defaultValue: false },
};

describe('parseUrlQuery', () => {
  it('parses numbers/booleans and applies defaults', () => {
    const values = parseUrlQuery('?keyword=张&page=2&mine=true', fields);
    expect(values).toEqual({
      keyword: '张',
      status: 'enabled',
      page: 2,
      page_size: 20,
      mine: true,
    });
  });

  it('rejects unparseable numbers back to the default', () => {
    const values = parseUrlQuery('?page=abc&page_size=-1&mine=0', fields);
    expect(values.page).toBe(1);
    // -1 是有限数，原样保留——范围合法性交给服务端校验。
    expect(values.page_size).toBe(-1);
    expect(values.mine).toBe(false);
  });

  it('treats a present-but-empty string as absent', () => {
    const values = parseUrlQuery('?keyword=', fields);
    expect(values.keyword).toBeUndefined();
  });
});

describe('buildUrlQuery', () => {
  it('omits defaults and empty values, keeps undeclared params', () => {
    const current = new URLSearchParams('tab=list&status=disabled');
    const values = parseUrlQuery(current.toString(), fields);
    const next = buildUrlQuery(current, fields, values, {
      keyword: '李四',
      page: 2,
    });

    expect(next.get('tab')).toBe('list');
    // status 仍是 `values` 里的 'disabled'，非默认值 → 保留。
    expect(next.get('status')).toBe('disabled');
    expect(next.get('keyword')).toBe('李四');
    expect(next.get('page')).toBe('2');
    // page_size 仍是默认值 → 不出现。
    expect(next.has('page_size')).toBe(false);
  });

  it('deletes params when patched back to empty or default', () => {
    const current = new URLSearchParams('keyword=李四&page=3&mine=true');
    const values = parseUrlQuery(current.toString(), fields);
    const next = buildUrlQuery(current, fields, values, {
      keyword: undefined,
      page: 1,
      mine: false,
    });

    expect(next.has('keyword')).toBe(false);
    expect(next.has('page')).toBe(false);
    expect(next.has('mine')).toBe(false);
  });

  it('clears a param when it is reset to the declared default', () => {
    const current = new URLSearchParams('status=disabled');
    const values = parseUrlQuery(current.toString(), fields);
    const next = buildUrlQuery(current, fields, values, { status: 'enabled' });
    expect(next.has('status')).toBe(false);
  });
});
