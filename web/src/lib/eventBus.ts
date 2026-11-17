import mitt from 'mitt';

import type { AdminApiError } from './adminClient';

/** 外观变化的具体面：明暗模式 / 主题色 / 布局模式。 */
export type AppearanceKind = 'mode' | 'color' | 'layout';

/**
 * 全局事件表。跨模块、非父子关系的通知都走这里；
 * 新事件先登记到本表再使用，事件名 `<域>:<动作>`。
 */
export type AppEvents = {
  /** adminClient 每个请求的开始/结束；全局 loading 计数消费。 */
  'request:begin': { url: string; method: string };
  'request:end': { url: string; method: string };
  /** 401/会话失效类错误；路由层订阅后跳登录页（带回来地址）。 */
  'session:expired': { message: string };
  /** 契约错误码 SUDO_REQUIRED；全局 sudo 弹窗的唯一挂点。 */
  'sudo:required': { error: AdminApiError };
  /** 主题色、明暗、布局模式变化；图表等需要跟随外观的模块订阅。 */
  'appearance:changed': { kind: AppearanceKind };
};

export const eventBus = mitt<AppEvents>();

/** 订阅并返回退订函数，配合 useEffect 清理使用。 */
export function onAppEvent<K extends keyof AppEvents>(
  type: K,
  handler: (event: AppEvents[K]) => void,
): () => void {
  eventBus.on(type, handler);
  return () => {
    eventBus.off(type, handler);
  };
}
