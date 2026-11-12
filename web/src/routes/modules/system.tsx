import type { RouteObject } from 'react-router';

import { AuditLogPage } from '@/pages/system/auditLog/AuditLogPage';
import { CronPage } from '@/pages/system/cron/CronPage';
import { LoginLogPage } from '@/pages/system/loginLog/LoginLogPage';
import { MonitorPage } from '@/pages/system/monitor/MonitorPage';
import { PluginsPage } from '@/pages/system/plugins/PluginsPage';
import { SettingsPage } from '@/pages/system/settings/SettingsPage';
import { SystemUpdatePage } from '@/pages/system/update/SystemUpdatePage';

// 系统域页面：监控、日志、定时任务、插件、更新与设置。
const routes: RouteObject[] = [
  {
    path: '/system/monitor',
    element: <MonitorPage />,
    handle: { title: 'monitor:title', permission: 'system:monitor:index' },
  },
  {
    path: '/system/login-log',
    element: <LoginLogPage />,
    handle: { title: 'loginLog:title', permission: 'system:login-log:index' },
  },
  {
    path: '/system/oper-log',
    element: <AuditLogPage />,
    handle: { title: 'auditLog:title', permission: 'system:audit-log:index' },
  },
  {
    path: '/system/cron',
    element: <CronPage />,
    handle: { title: 'cron:title', permission: 'system:cron:index' },
  },
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
    path: '/system/settings',
    element: <SettingsPage />,
    handle: { title: 'settings:title', permission: 'system:config:index' },
  },
];

export default routes;
