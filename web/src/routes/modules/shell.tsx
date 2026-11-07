import type { RouteObject } from 'react-router';

import { ErrorPage } from '@/pages/shell/ErrorPages';
import { PreferencesPage } from '@/pages/shell/PreferencesPage';
import { ResultRoutePage } from '@/pages/shell/ResultRoutePage';
import { WorkbenchPage } from '@/pages/shell/WorkbenchPage';

// Protected routes mount under RequireAuth + AppShell; `handle.public` routes
// are bare top-level pages. `handle.title` is a `shell` namespace i18n key
// used as a breadcrumb fallback when the path is not in the menu tree.
const routes: RouteObject[] = [
  {
    path: '/',
    element: <WorkbenchPage />,
    handle: { title: 'workbench.title' },
  },
  {
    path: '/preferences',
    element: <PreferencesPage />,
    handle: { title: 'preferences.title' },
  },
  {
    path: '/result/success',
    element: <ResultRoutePage status="success" />,
    handle: { public: true },
  },
  {
    path: '/result/fail',
    element: <ResultRoutePage status="fail" />,
    handle: { public: true },
  },
  {
    path: '/403',
    element: <ErrorPage kind="403" />,
    handle: { public: true },
  },
  {
    path: '/500',
    element: <ErrorPage kind="500" />,
    handle: { public: true },
  },
];

export default routes;
