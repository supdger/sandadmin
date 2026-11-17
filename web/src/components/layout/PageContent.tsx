import { useState } from 'react';
import type { ReactNode } from 'react';

import { useLocation, useOutlet } from 'react-router';

import { useTabs } from '@/stores/tabs';
import { useGlobalUi } from './globalUi';
import { useTabRouteSync } from './useTabRouteSync';

/**
 * 页面内容区 + 多标签 keep-alive：隐藏页面的副作用与其
 * useLocation 读取仍按最新路由运行；多标签关闭时退化
 * 为只渲染当前页。
 */
export function PageContent() {
  const { tabsEnabled } = useGlobalUi();
  const { tabs } = useTabs();
  useTabRouteSync(tabsEnabled);
  const { pathname } = useLocation();
  const outlet = useOutlet();
  // useState 的惰性 Map 当稳定可变缓存用：渲染期同步增删是幂等的，
  // 不触发重渲染，也不需要 effect 时机。
  const [cache] = useState(() => new Map<string, ReactNode>());

  if (!tabsEnabled) {
    cache.clear();
    return <>{outlet}</>;
  }

  // 标签关闭即清缓存：重开是全新实例，不残留上一次的页面态。
  const alive = new Set(tabs.map((tab) => tab.key));
  for (const key of cache.keys()) {
    if (!alive.has(key)) {
      cache.delete(key);
    }
  }
  if (outlet !== null) {
    cache.set(pathname, outlet);
  }

  // 当前路径可能还没被 useTabRouteSync 登记进 tabs（effect 滞后
  // 一拍），渲染集 = 已开标签 ∪ 当前路径，保证新页面首帧就在
  // 稳定的 DOM 位置上，登记后不发生重挂载。
  const keys = tabs.map((tab) => tab.key);
  if (!alive.has(pathname)) {
    keys.push(pathname);
  }

  return (
    <>
      {keys.map((key) => {
        const node = cache.get(key);
        if (node === undefined) {
          return null;
        }
        return (
          <div key={key} style={{ display: key === pathname ? 'block' : 'none' }}>
            {node}
          </div>
        );
      })}
    </>
  );
}
