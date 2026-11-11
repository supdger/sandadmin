import type { RouteObject } from 'react-router';

import { AnnouncementsPage } from '@/pages/data/AnnouncementsPage';
import { DictPage } from '@/pages/data/DictPage';
import { FilesPage } from '@/pages/data/FilesPage';

// Data-domain routes: option management, file management, announcements.
// `handle.title` uses the explicit `ns:key` form so the shell-bound `t()`
// resolves the right namespace; `handle.permission` gates each route.
const routes: RouteObject[] = [
  {
    path: '/data/dict',
    element: <DictPage />,
    handle: { title: 'dict:title', permission: 'system:dict:index' },
  },
  {
    path: '/data/file',
    element: <FilesPage />,
    handle: { title: 'files:title', permission: 'system:file:index' },
  },
  {
    path: '/data/notice',
    element: <AnnouncementsPage />,
    handle: { title: 'announcements:title', permission: 'system:announcement:index' },
  },
];

export default routes;
