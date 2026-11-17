import type { RouteObject } from 'react-router';

import { PluginHostPage } from '@/pages/plugin/PluginHostPage';

// 插件统一挂载点：各插件的权限码由 PluginHostPage 按插件声明分别判定，
// 这里不挂 handle.permission（否则一个码会挡住全部插件）。
const routes: RouteObject[] = [
  {
    path: '/plugin/:pluginCode/*',
    element: <PluginHostPage />,
    handle: { title: 'pluginHost:title' },
  },
];

export default routes;
