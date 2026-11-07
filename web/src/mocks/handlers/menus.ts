import { http, type HttpHandler } from 'msw';

import { env } from '@/config/env';
import { mockUserForToken } from './auth';
import { fail, ok } from './_shared';

const base = env.apiBaseUrl;

// Only routes that actually exist are advertised — dead links in the menu
// are a mock bug, not a product feature.
const FULL_MENUS = [
  {
    id: 'workbench',
    parentId: null,
    title: '工作台',
    path: '/',
    icon: 'home',
    order: 1,
  },
  {
    id: 'system',
    parentId: null,
    title: '系统设置',
    path: '',
    icon: 'setting',
    order: 2,
    children: [
      {
        id: 'preferences',
        parentId: 'system',
        title: '偏好设置',
        path: '/preferences',
        order: 1,
      },
    ],
  },
];

function bearerToken(request: Request): string {
  return (request.headers.get('authorization') ?? '').replace(/^Bearer\s+/i, '');
}

export default [
  http.get(`${base}/me/menus`, ({ request }) => {
    const user = mockUserForToken(bearerToken(request));
    if (!user) {
      return fail(4010, '登录已过期，请重新登录');
    }
    // A user with zero permissions (the `guest` demo account) gets an empty
    // menu — every protected route then lands on /403 via the route guard.
    if (user.permissions.length === 0) {
      return ok([]);
    }
    return ok(FULL_MENUS);
  }),
] satisfies HttpHandler[];
