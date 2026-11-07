import { http, type HttpHandler } from 'msw';

import { env } from '@/config/env';
import { ok } from './_shared';

const base = env.apiBaseUrl;

// Only routes that actually exist are advertised — dead links in the menu
// are a mock bug, not a product feature.
export default [
  http.get(`${base}/me/menus`, () =>
    ok([
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
    ]),
  ),
] satisfies HttpHandler[];
