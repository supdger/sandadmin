import { act, renderHook } from '@testing-library/react';
import type { ReactNode } from 'react';
import { MemoryRouter, useLocation } from 'react-router';
import { describe, expect, it } from 'vitest';

import { useUrlQuery, type UrlQueryFields } from './useUrlQuery';

const fields: UrlQueryFields = {
  keyword: {},
  page: { kind: 'number', defaultValue: 1 },
  page_size: { kind: 'number', defaultValue: 20 },
};

function wrapper(initialEntry: string) {
  return function Wrapper({ children }: { children: ReactNode }) {
    return <MemoryRouter initialEntries={[initialEntry]}>{children}</MemoryRouter>;
  };
}

function setup(initialEntry: string) {
  return renderHook(
    () => {
      const query = useUrlQuery(fields);
      const location = useLocation();
      return { query, location };
    },
    { wrapper: wrapper(initialEntry) },
  );
}

describe('useUrlQuery', () => {
  it('reads declared params from the location', () => {
    const { result } = setup('/list?keyword=张&page=3');
    expect(result.current.query.values.keyword).toBe('张');
    expect(result.current.query.values.page).toBe(3);
    expect(result.current.query.values.page_size).toBe(20);
  });

  it('writes a patch with replace and omits defaults', () => {
    const { result } = setup('/list?keyword=张&page=3');
    act(() => {
      result.current.query.setValues({ keyword: '李四', page: 1 });
    });
    expect(result.current.location.search).toBe('?keyword=%E6%9D%8E%E5%9B%9B');
  });

  it('keeps undeclared params when patching', () => {
    const { result } = setup('/list?tab=list&page=2');
    act(() => {
      result.current.query.setValues({ page: 4 });
    });
    const params = new URLSearchParams(result.current.location.search);
    expect(params.get('tab')).toBe('list');
    expect(params.get('page')).toBe('4');
  });

  it('pushes history when replace is false', () => {
    const { result } = setup('/list?page=1');
    act(() => {
      result.current.query.setValues({ page: 5 }, { replace: false });
    });
    expect(result.current.location.search).toBe('?page=5');
  });
});
