import type { RouteObject } from 'react-router';

import ForceChangePasswordPage from '@/pages/auth/ForceChangePasswordPage';
import LoginPage from '@/pages/auth/LoginPage';

// Bare public routes — no shell, no auth guard. The force-change page
// self-checks the token via useMe and bounces to /login when absent.
export default [
  {
    path: '/login',
    element: <LoginPage />,
    handle: { public: true, title: '登录' },
  },
  {
    path: '/force-change-password',
    element: <ForceChangePasswordPage />,
    handle: { public: true, title: '修改密码' },
  },
] satisfies RouteObject[];
