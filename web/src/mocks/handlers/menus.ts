import { http, type HttpHandler } from 'msw';

import { env } from '@/config/env';
import { ok } from './_shared';

const base = env.apiBaseUrl;

export default [
  http.get(`${base}/me/menus`, () =>
    ok([
      { id: 'workbench', parentId: null, title: '工作台', path: '/', order: 1 },
      { id: 'preferences', parentId: null, title: '偏好设置', path: '/preferences', order: 2 },
    ]),
  ),
] satisfies HttpHandler[];
