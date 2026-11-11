import type { RouteObject } from 'react-router';

import { NotificationsPage } from '@/pages/personal/NotificationsPage';
import { ProfilePage } from '@/pages/personal/ProfilePage';
import { TodosPage } from '@/pages/personal/TodosPage';

// 个人域路由：登录即可访问，不额外挂权限；标题经 shell 的 t() 解析，
// 需用 `ns:` 前缀显式指定命名空间。
const routes: RouteObject[] = [
  {
    path: '/profile',
    element: <ProfilePage />,
    handle: { title: 'profile:title' },
  },
  {
    path: '/notifications',
    element: <NotificationsPage />,
    handle: { title: 'notifications:title' },
  },
  {
    path: '/todos',
    element: <TodosPage />,
    handle: { title: 'workbench:todos.title' },
  },
];

export default routes;
