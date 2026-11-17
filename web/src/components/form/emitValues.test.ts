import { describe, expect, it } from 'vitest';

import { emitValues } from './emitValues';
import type { FormFieldConfig } from './FieldItem';

const RANGE_FIELD: FormFieldConfig = {
  type: 'date',
  field: 'create_time',
  kind: 'dateRange',
  rangeKeys: { from: 'create_time_from', to: 'create_time_to' },
};

describe('emitValues', () => {
  it('splits a range field into its from/to keys', () => {
    expect(
      emitValues([RANGE_FIELD], {
        create_time: ['2026-01-01', '2026-01-31'],
        keep: 'x',
      }),
    ).toEqual({ create_time_from: '2026-01-01', create_time_to: '2026-01-31', keep: 'x' });
  });

  it('drops the range key entirely when the field is empty', () => {
    expect(emitValues([RANGE_FIELD], { create_time: undefined })).toEqual({});
  });

  it('keeps a partially filled range', () => {
    expect(emitValues([RANGE_FIELD], { create_time: ['2026-01-01'] })).toEqual({
      create_time_from: '2026-01-01',
    });
  });
});
