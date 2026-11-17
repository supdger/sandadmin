import { act, renderHook } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';

import { eventBus } from './eventBus';
import { activeLoadingCount, loadingBegin, loadingEnd, resetLoading, useGlobalLoading } from './loading';

describe('loading', () => {
  afterEach(() => {
    resetLoading();
  });

  it('counts concurrent requests instead of a boolean', () => {
    loadingBegin();
    loadingBegin();
    expect(activeLoadingCount()).toBe(2);
    loadingEnd();
    expect(activeLoadingCount()).toBe(1);
    loadingEnd();
    expect(activeLoadingCount()).toBe(0);
  });

  it('never drops below zero', () => {
    loadingEnd();
    expect(activeLoadingCount()).toBe(0);
  });

  it('advances on request lifecycle events', () => {
    eventBus.emit('request:begin', { url: '/a', method: 'GET' });
    expect(activeLoadingCount()).toBe(1);
    eventBus.emit('request:end', { url: '/a', method: 'GET' });
    expect(activeLoadingCount()).toBe(0);
  });

  it('reports active state through useGlobalLoading', () => {
    const { result } = renderHook(() => useGlobalLoading());
    expect(result.current).toBe(false);
    act(() => loadingBegin());
    expect(result.current).toBe(true);
  });
});
