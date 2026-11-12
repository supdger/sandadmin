import type { RouteObject } from 'react-router';

import { PluginsPage } from '@/pages/system/plugins/PluginsPage';
import { SystemUpdatePage } from '@/pages/system/update/SystemUpdatePage';

// 系统域生命周期页面：插件管理与系统更新。
const routes: RouteObject[] = [
  {
    path: '/system/plugins',
    element: <PluginsPage />,
    handle: { title: 'plugins:title', permission: 'system:plugin:index' },
  },
  {
    path: '/system/update',
    element: <SystemUpdatePage />,
    handle: { title: 'systemUpdate:title', permission: 'system:update:index' },
  },
];

export default routes;
