import { useEffect, useRef, useState } from 'react';

import { getLifecycleRequest, type LifecycleRequest } from '@/api/v1/lifecycle';
import { AdminApiError, isAdminSystemError } from '@/lib/adminClient';

const POLL_INTERVAL_MS = 3_000;
const UNREACHABLE_AFTER_MS = 120_000;

/** 申请还在进行中（继续轮询）的两种状态。 */
export function isLifecycleRunning(request: LifecycleRequest | null | undefined): boolean {
  return request?.state === 'queued' || request?.state === 'running';
}

/**
 * 系统升级「切换」阶段服务正在换新，连不上或 502/503 都不算出错：
 * 继续每 3 秒重试；只有连续 2 分钟还连不上才提示。
 */
function isSwitchTolerated(request: LifecycleRequest | null, error: unknown): boolean {
  if (request?.kind !== 'system_upgrade' || request.stage_key !== 'switch') {
    return false;
  }
  if (!isAdminSystemError(error)) {
    return false;
  }
  return (
    error.code === 'NETWORK_ERROR' || error.httpStatus === 502 || error.httpStatus === 503
  );
}

export interface LifecyclePoll {
  /** 最近一次成功的申请详情；从未成功为 null。 */
  request: LifecycleRequest | null;
  /** 非容忍的查询失败（业务错误或不是切换阶段的系统故障）。 */
  error: AdminApiError | null;
  /** 切换阶段断连重试中，还未满 2 分钟。 */
  switching: boolean;
  /** 切换阶段连续 2 分钟连不上：提示「连不上服务，可刷新页面再看进度」。 */
  unreachable: boolean;
  /** 查询失败后重新开始轮询。 */
  retry: () => void;
}

export interface LifecyclePollOptions {
  /** 已知的申请详情（如 /system/update/status 的 running_request），首轮直接展示。 */
  seed?: LifecycleRequest | null;
  intervalMs?: number;
  unreachableMs?: number;
}

interface PollMachine {
  request: LifecycleRequest | null;
  error: AdminApiError | null;
  switching: boolean;
  unreachable: boolean;
}

/**
 * 每 3 秒轮询一次生命周期申请；页面不在前台时停，回来接着查。
 * 进度存在库里，离开页面再回来凭 request_id 继续看。
 */
export function useLifecycleRequest(
  requestId: string | null,
  options?: LifecyclePollOptions,
): LifecyclePoll {
  const intervalMs = options?.intervalMs ?? POLL_INTERVAL_MS;
  const unreachableMs = options?.unreachableMs ?? UNREACHABLE_AFTER_MS;
  const [machine, setMachine] = useState<PollMachine>({
    request: options?.seed ?? null,
    error: null,
    switching: false,
    unreachable: false,
  });
  const machineRef = useRef(machine);
  const [round, setRound] = useState(0);

  const commit = (patch: Partial<PollMachine>) => {
    machineRef.current = { ...machineRef.current, ...patch };
    setMachine(machineRef.current);
  };

  useEffect(() => {
    machineRef.current = {
      request: options?.seed ?? null,
      error: null,
      switching: false,
      unreachable: false,
    };
    setMachine(machineRef.current);
    if (!requestId) return;

    let timer: ReturnType<typeof setInterval> | null = null;
    let failureSince: number | null = null;
    let stopped = false;

    const visible = () =>
      typeof document === 'undefined' || document.visibilityState === 'visible';

    const tick = async () => {
      try {
        const next = await getLifecycleRequest(requestId);
        if (stopped) return;
        failureSince = null;
        commit({ request: next, error: null, switching: false, unreachable: false });
        if (!isLifecycleRunning(next)) stop();
      } catch (e) {
        if (stopped) return;
        if (isSwitchTolerated(machineRef.current.request, e)) {
          if (failureSince === null) failureSince = Date.now();
          commit({
            switching: Date.now() - failureSince < unreachableMs,
            unreachable: Date.now() - failureSince >= unreachableMs,
          });
          return;
        }
        const apiError =
          e instanceof AdminApiError
            ? e
            : new AdminApiError({
                code: 'INTERNAL_ERROR',
                message: String(e),
                httpStatus: 0,
                method: 'GET',
                url: `/lifecycle-requests/${requestId}`,
                system: true,
              });
        commit({ error: apiError, switching: false, unreachable: false });
        stop();
      }
    };

    const start = () => {
      if (
        timer !== null ||
        machineRef.current.error !== null ||
        !isLifecycleRunning(machineRef.current.request)
      )
        return;
      timer = setInterval(() => {
        if (visible()) void tick();
      }, intervalMs);
    };

    const stop = () => {
      if (timer !== null) {
        clearInterval(timer);
        timer = null;
      }
    };

    const onVisibility = () => {
      if (!visible()) {
        stop();
        return;
      }
      void tick();
      start();
    };

    void tick().then(() => {
      if (!stopped) start();
    });
    document.addEventListener('visibilitychange', onVisibility);
    return () => {
      stopped = true;
      stop();
      document.removeEventListener('visibilitychange', onVisibility);
    };
    // options.seed 只在 requestId/round 变化时取一次。
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [requestId, round, intervalMs, unreachableMs]);

  return {
    request: machine.request,
    error: machine.error,
    switching: machine.switching,
    unreachable: machine.unreachable,
    retry: () => {
      setRound((r) => r + 1);
    },
  };
}
