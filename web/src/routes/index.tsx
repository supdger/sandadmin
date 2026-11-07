import type { RouteObject } from 'react-router';
import { createBrowserRouter } from 'react-router';

import { ErrorState } from '@/components/ErrorState';
import { AppShell } from '@/layouts/AppShell';
import { RequireAuth, RequirePermission } from './guards';

type RouteModule = { default: RouteObject[] };

interface RouteHandle {
  public?: boolean;
  title?: string;
}

// Each feature area contributes one file under ./modules exporting
// `default: RouteObject[]`. `handle.public: true` routes are mounted bare
// (no auth, no shell); everything else sits behind RequireAuth + AppShell.
const modules = import.meta.glob<RouteModule>('./modules/*.tsx', { eager: true });

const moduleRoutes: RouteObject[] = Object.values(modules).flatMap((m) => m.default);

function isPublic(route: RouteObject): boolean {
  return (route.handle as RouteHandle | undefined)?.public === true;
}

const publicRoutes = moduleRoutes.filter(isPublic);
const privateRoutes = moduleRoutes.filter((r) => !isPublic(r));

export const routes: RouteObject[] = [
  ...publicRoutes,
  {
    element: <RequireAuth />,
    children: [
      {
        element: <AppShell />,
        children: [
          // Permission-gated pages; routes without handle.permission and
          // the catch-all 404 pass straight through.
          {
            element: <RequirePermission />,
            children: privateRoutes,
          },
          // Catch-all 404 inside the shell.
          { path: '*', element: <ErrorState kind="404" /> },
        ],
      },
    ],
  },
];

export function createAppRouter() {
  return createBrowserRouter(routes);
}
