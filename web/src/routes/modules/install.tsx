import type { RouteObject } from 'react-router';

import InstallPage from '@/pages/install/InstallPage';

export default [
  {
    path: '/install',
    element: <InstallPage />,
    handle: { public: true, title: '安装向导' },
  },
] satisfies RouteObject[];
