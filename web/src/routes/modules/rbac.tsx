import type { RouteObject } from 'react-router';

import { MenusPage } from '@/pages/rbac/menus/MenusPage';
import { RolePermissionPage } from '@/pages/rbac/role-permission/RolePermissionPage';
import { RolesPage } from '@/pages/rbac/roles/RolesPage';

// RBAC 页面：角色列表、单角色权限编辑器和菜单管理。
// `handle.title` 同时充当面包屑兜底——缺失的 i18n key 会原样渲染。
// 权限编辑器不进菜单。
const routes: RouteObject[] = [
  {
    path: '/system/role',
    element: <RolesPage />,
    handle: { title: '角色与权限', permission: 'system:role:index' },
  },
  {
    path: '/system/role/:roleId/permission',
    element: <RolePermissionPage />,
    handle: { title: '设置权限', permission: 'system:role:permission:index' },
  },
  {
    path: '/system/menu',
    element: <MenusPage />,
    handle: { title: '菜单管理', permission: 'system:menu:index' },
  },
];

export default routes;
