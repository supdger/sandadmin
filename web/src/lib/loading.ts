import { useSyncExternalStore } from 'react';

import { onAppEvent } from './eventBus';

/**
 * 全局请求加载计数。request 层在请求开始/结束时发出
 * request:begin / request:end，这里按计数推进——并发请求
 * 不会互相提前关掉。导出与渲染无关，长任务（导出、轮询）
 * 也可手动 loadingBegin/loadingEnd 进出。
 */

let activeCount = 0;
const listeners = new Set<() => void>();

function notify(): void {
  for (const listener of listeners) {
    listener();
  }
}

export function loadingBegin(): void {
  activeCount += 1;
  notify();
}

export function loadingEnd(): void {
  activeCount = Math.max(0, activeCount - 1);
  notify();
}

export function activeLoadingCount(): number {
  return activeCount;
}

function subscribe(callback: () => void): () => void {
  listeners.add(callback);
  return () => {
    listeners.delete(callback);
  };
}

function isActive(): boolean {
  return activeCount > 0;
}

/** 是否有在途请求；组件用它渲染全局加载态。 */
export function useGlobalLoading(): boolean {
  return useSyncExternalStore(subscribe, isActive, () => false);
}

// request 层只发事件、不认识本模块；订阅在这里完成接线。
onAppEvent('request:begin', loadingBegin);
onAppEvent('request:end', loadingEnd);

/** 测试钩子：归零计数并回到初始态。 */
export function resetLoading(): void {
  activeCount = 0;
  notify();
}
