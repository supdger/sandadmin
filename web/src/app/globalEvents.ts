import { Toast } from '@douyinfe/semi-ui';
import type { QueryClient } from '@tanstack/react-query';

import { ME_QUERY_KEY } from '@/hooks/useMe';
import { clearToken } from '@/lib/authToken';
import { onAppEvent } from '@/lib/eventBus';
import { loginUrl, PATH_LOGIN } from '@/routes/paths';

/**
 * 全局事件接线：request 层发出的事件在这里转成路由跳转、
 * 缓存清理等框架动作。独立于组件树挂载——登录跳转不该依赖
 * 某个页面组件在场。
 *
 * - session:expired → 清会话缓存，跳登录页（带回来地址），
 *   提示用服务端 message，前端不自己编。
 * - sudo:required → 事件即挂点：全局 sudo 弹窗（后续包）订阅同名事件。
 */

/** 便于测试的最小路由形状；生产传入 createAppRouter() 的产物。 */
export interface NavigateTarget {
  state: { location: { pathname: string; search: string } };
  navigate: (to: string, opts?: { replace?: boolean }) => void;
}

export function bindGlobalEvents(router: NavigateTarget, queryClient: QueryClient): () => void {
  return onAppEvent('session:expired', ({ message }) => {
    const { pathname, search } = router.state.location;
    clearToken();
    queryClient.removeQueries({ queryKey: ME_QUERY_KEY });
    if (pathname !== PATH_LOGIN) {
      Toast.warning({ content: message });
      router.navigate(loginUrl(`${pathname}${search}`), { replace: true });
    }
  });
}

