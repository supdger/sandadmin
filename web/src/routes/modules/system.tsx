import type { RouteObject } from 'react-router';

import { PluginsPage } from '@/pages/system/plugins/PluginsPage';
import { SystemUpdatePage } from '@/pages/system/update/SystemUpdatePage';
import { TasksPage } from '@/pages/system/tasks/TasksPage';

// 系统域生命周期页面：插件管理、系统更新与任务中心。
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
  {
    path: '/system/tasks',
    element: <TasksPage />,
    handle: { title: 'tasks:title', permission: 'system:task:index' },
  },
];

export default routes;
