import type { RouteObject } from 'react-router';

import { DeptsPage } from '@/pages/org/depts/DeptsPage';
import { PostsPage } from '@/pages/org/posts/PostsPage';
import { UsersPage } from '@/pages/org/users/UsersPage';

// 组织管理页面：用户、部门、岗位。`handle.title` 同时充当面包屑兜底——shell
// 会经 i18next 解析，缺失的 key 原样渲染，所以直接写中文标题即可。
// 页面路由只挂 index 权限；行内操作按钮各自检查对应动作权限。
const routes: RouteObject[] = [
  {
    path: '/system/user',
    element: <UsersPage />,
    handle: { title: '用户管理', permission: 'system:user:index' },
  },
  {
    path: '/system/dept',
    element: <DeptsPage />,
    handle: { title: '部门管理', permission: 'system:dept:index' },
  },
  {
    path: '/system/post',
    element: <PostsPage />,
    handle: { title: '岗位管理', permission: 'system:post:index' },
  },
];

export default routes;
