import { describe, expect, it } from 'vitest';

import { AdminApiError } from './adminClient';
import {
  applyFieldErrors,
  buildFieldRules,
  compactValues,
  fieldErrorsOf,
  isEmptyValue,
} from './formUtils';

function makeError(fields?: Record<string, string[]>): AdminApiError {
  return new AdminApiError({
    code: 'VALIDATION_FAILED',
    message: '请检查表单内容',
    fields,
    httpStatus: 422,
    method: 'POST',
    url: '/api/admin/v1/test-rows',
  });
}

describe('fieldErrorsOf', () => {
  it('takes fields off an AdminApiError envelope', () => {
    expect(fieldErrorsOf(makeError({ name: ['不能为空'] }))).toEqual({ name: ['不能为空'] });
  });

  it('returns null for errors without fields', () => {
    expect(fieldErrorsOf(makeError())).toBeNull();
    expect(fieldErrorsOf(new Error('x'))).toBeNull();
    expect(fieldErrorsOf(makeError({}))).toBeNull();
  });
});

describe('applyFieldErrors', () => {
  it('writes messages to matching fields and reports applied', () => {
    const calls: [string, unknown][] = [];
    const api = {
      setError: (field: string, error: unknown) => {
        calls.push([field, error]);
      },
      getFieldExist: (field: string) => field === 'name',
    };
    const applied = applyFieldErrors(api, makeError({ name: ['不能为空'], ghost: ['无此字段'] }));
    expect(applied).toBe(true);
    expect(calls).toEqual([['name', ['不能为空']]]);
  });

  it('returns false when there is nothing to apply', () => {
    const api = { setError: () => {} };
    expect(applyFieldErrors(api, makeError())).toBe(false);
  });
});

describe('buildFieldRules', () => {
  it('puts the required rule first', () => {
    const rules = buildFieldRules({ required: true, rules: [{ type: 'string', min: 2 }] });
    expect(rules).toHaveLength(2);
    expect(rules[0].required).toBe(true);
  });
});

describe('isEmptyValue / compactValues', () => {
  it('treats empty string, null, undefined and [] as empty', () => {
    expect(isEmptyValue('')).toBe(true);
    expect(isEmptyValue([])).toBe(true);
    expect(isEmptyValue(0)).toBe(false);
    expect(isEmptyValue(false)).toBe(false);
  });

  it('drops empty values and keeps serializable ones', () => {
    expect(
      compactValues({
        keyword: '  ',
        status: 'enabled',
        page: 0,
        flag: false,
        tags: ['a', 'b'],
        empty_list: [],
        obj: { x: 1 },
        none: null,
      }),
    ).toEqual({ keyword: '  ', status: 'enabled', page: 0, flag: false, tags: ['a', 'b'] });
  });
});
