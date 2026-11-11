import { act, renderHook } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import type { LifecycleRequest } from '@/api/v1/lifecycle';
import { getLifecycleRequest } from '@/api/v1/lifecycle';
import { AdminApiError } from '@/lib/adminClient';
import { useLifecycleRequest } from './useLifecycleRequest';

vi.mock('@/api/v1/lifecycle', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/api/v1/lifecycle')>();
  return { ...orig, getLifecycleRequest: vi.fn() };
});

const fetchRequest = vi.mocked(getLifecycleRequest);

function makeRequest(patch: Partial<LifecycleRequest> = {}): LifecycleRequest {
  return {
    id: 'req-1',
    kind: 'system_upgrade',
    target_code: 'host',
    source: 'official',
    from_version: '1.4.2',
    to_version: '1.6.0',
    purge_data: false,
    items: null,
    state: 'running',
    stage: '正在检查',
    stage_key: 'check',
    progress: 10,
    rolled_back: false,
    error_code: null,
    error_message: null,
    create_time: '2026-06-10T10:00:00+08:00',
    start_time: '2026-06-10T10:00:01+08:00',
    finish_time: null,
    ...patch,
  };
}

function networkError(): AdminApiError {
  return new AdminApiError({
    code: 'NETWORK_ERROR',
    message: '系统服务暂时不可用',
    httpStatus: 0,
    method: 'GET',
    url: '/api/admin/v1/lifecycle-requests/req-1',
    system: true,
  });
}

async function flush(ms = 0) {
  await act(async () => {
    await vi.advanceTimersByTimeAsync(ms);
  });
}

describe('useLifecycleRequest', () => {
  afterEach(() => {
    vi.useRealTimers();
    fetchRequest.mockReset();
  });

  it('每 3 秒轮询一次，到终态后停止', async () => {
    vi.useFakeTimers();
    fetchRequest
      .mockResolvedValueOnce(makeRequest({ progress: 10 }))
      .mockResolvedValueOnce(makeRequest({ state: 'running', stage_key: 'backup', progress: 40 }))
      .mockResolvedValue(makeRequest({ state: 'succeeded', progress: 100 }));

    const { result } = renderHook(() =>
      useLifecycleRequest('req-1', { intervalMs: 3_000 }),
    );
    await flush();
    expect(fetchRequest).toHaveBeenCalledTimes(1);
    expect(result.current.request?.progress).toBe(10);

    await flush(3_000);
    expect(fetchRequest).toHaveBeenCalledTimes(2);

    await flush(3_000);
    expect(result.current.request?.state).toBe('succeeded');

    // 终态后不再发请求
    await flush(10_000);
    expect(fetchRequest).toHaveBeenCalledTimes(3);
  });

  it('切换阶段连不上/502/503 容忍重试，不报错', async () => {
    vi.useFakeTimers();
    fetchRequest.mockRejectedValue(networkError());

    const { result } = renderHook(() =>
      useLifecycleRequest('req-1', {
        intervalMs: 3_000,
        unreachableMs: 120_000,
        seed: makeRequest({ stage_key: 'switch', stage: '正在切换' }),
      }),
    );
    await flush(9_000);
    expect(fetchRequest).toHaveBeenCalledTimes(4);
    expect(result.current.switching).toBe(true);
    expect(result.current.unreachable).toBe(false);
    expect(result.current.error).toBeNull();
  });

  it('切换阶段连续 2 分钟连不上 → unreachable', async () => {
    vi.useFakeTimers();
    fetchRequest.mockRejectedValue(networkError());

    const { result } = renderHook(() =>
      useLifecycleRequest('req-1', {
        intervalMs: 3_000,
        unreachableMs: 60_000,
        seed: makeRequest({ stage_key: 'switch' }),
      }),
    );
    await flush(30_000);
    expect(result.current.switching).toBe(true);
    expect(result.current.unreachable).toBe(false);

    await flush(36_000);
    expect(result.current.unreachable).toBe(true);
  });

  it('非切换阶段的故障直接报错并停轮询', async () => {
    vi.useFakeTimers();
    fetchRequest.mockRejectedValue(networkError());

    const { result } = renderHook(() =>
      useLifecycleRequest('req-1', {
        intervalMs: 3_000,
        seed: makeRequest({ stage_key: 'download' }),
      }),
    );
    await flush();
    expect(result.current.error).not.toBeNull();
    expect(result.current.switching).toBe(false);

    await flush(10_000);
    expect(fetchRequest).toHaveBeenCalledTimes(1);
  });

  it('业务错误（404）不当作断连，直接报错', async () => {
    vi.useFakeTimers();
    fetchRequest.mockRejectedValue(
      new AdminApiError({
        code: 'LIFECYCLE_REQUEST_NOT_FOUND',
        message: '要查看的进度不存在或已被清理',
        httpStatus: 404,
        method: 'GET',
        url: '/api/admin/v1/lifecycle-requests/req-1',
        system: false,
      }),
    );

    const { result } = renderHook(() =>
      useLifecycleRequest('req-1', {
        intervalMs: 3_000,
        seed: makeRequest({ stage_key: 'switch' }),
      }),
    );
    await flush();
    expect(result.current.error?.code).toBe('LIFECYCLE_REQUEST_NOT_FOUND');
    expect(result.current.unreachable).toBe(false);
  });

  it('页面不在前台时暂停轮询，回来立刻查一次', async () => {
    vi.useFakeTimers();
    fetchRequest.mockResolvedValue(makeRequest({ progress: 10 }));

    renderHook(() => useLifecycleRequest('req-1', { intervalMs: 3_000 }));
    await flush(3_000);
    expect(fetchRequest).toHaveBeenCalledTimes(2);

    // 模拟切到后台：事件触发后定时器里的 tick 也会被跳过。
    Object.defineProperty(document, 'visibilityState', {
      configurable: true,
      value: 'hidden',
    });
    act(() => {
      document.dispatchEvent(new Event('visibilitychange'));
    });
    await flush(10_000);
    expect(fetchRequest).toHaveBeenCalledTimes(2);

    Object.defineProperty(document, 'visibilityState', {
      configurable: true,
      value: 'visible',
    });
    act(() => {
      document.dispatchEvent(new Event('visibilitychange'));
    });
    await flush();
    expect(fetchRequest).toHaveBeenCalledTimes(3);
  });

  it('retry 失败后重新开始轮询', async () => {
    vi.useFakeTimers();
    fetchRequest
      .mockRejectedValueOnce(networkError())
      .mockResolvedValue(makeRequest({ progress: 20 }));

    const { result } = renderHook(() =>
      useLifecycleRequest('req-1', {
        intervalMs: 3_000,
        seed: makeRequest({ stage_key: 'download' }),
      }),
    );
    await flush();
    expect(result.current.error).not.toBeNull();

    act(() => {
      result.current.retry();
    });
    await flush();
    expect(result.current.error).toBeNull();
    expect(fetchRequest).toHaveBeenCalledTimes(2);
  });
});
