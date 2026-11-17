import { createElement, lazy } from 'react';
import type { RouteObject } from 'react-router';

import type { PluginModule } from '../registry';
import zhCN from './locales/zh-CN';

// 示例插件前端：验证宿主插件挂载与 i18n 收集机制。
// 真实插件由构建期 collect-plugin-pages.mjs 从
// server/plugin/<app>/plugin-web/<app>/ 同步进 src/plugins/<app>/。
const routes: RouteObject[] = [
  { index: true, element: createElement(lazy(() => import('./pages/HelloPage'))) },
  { path: 'about', element: createElement(lazy(() => import('./pages/AboutPage'))) },
];

const sandHello: PluginModule = {
  code: 'sand-hello',
  permission: 'sand_hello:page:read',
  routes,
  locales: { 'zh-CN': zhCN },
};

export default sandHello;
