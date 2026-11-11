import { useEffect } from 'react';

import { useQuery } from '@tanstack/react-query';
import { Navigate, Outlet, useLocation, useMatches } from 'react-router';

import { getMe } from '@/api/auth';
import { Loading } from '@/components/Loading';
import { hasPermission } from '@/hooks/useCan';
import { ME_QUERY_KEY, useMe } from '@/hooks/useMe';
import { clearToken, getToken } from '@/lib/authToken';

function loginUrl(redirect: string): string {
  return `/login?redirect=${encodeURIComponent(redirect)}`;
}

/**
 * Route guard for everything behind the shell:
 * - no token              → /login (with ?redirect= back to here)
 * - mustChangePassword    → /force-change-password
 * - profile still loading → full-page spinner
 * - profile failed        → token dropped, back to /login
 */
export function RequireAuth() {
  const location = useLocation();
  const token = getToken();
  const currentPath = location.pathname + location.search;

  const me = useQuery({
    queryKey: ME_QUERY_KEY,
    queryFn: getMe,
    enabled: Boolean(token),
    retry: false,
  });

  const authFailed = Boolean(token) && me.isError;
  useEffect(() => {
    if (authFailed) {
      clearToken();
    }
  }, [authFailed]);

  if (!token || authFailed) {
    return <Navigate to={loginUrl(currentPath)} replace />;
  }

  if (me.isPending) {
    return <Loading fullPage />;
  }

  if (me.data?.mustChangePassword && location.pathname !== '/force-change-password') {
    return <Navigate to="/force-change-password" replace />;
  }

  return <Outlet />;
}

interface PermissionHandle {
  permission?: string | string[];
}

/**
 * Permission guard mounted inside the shell: any matched route may declare
 * `handle.permission` (a string, or an array that requires ALL of them).
 * `'*'` in the user's permissions satisfies everything. When the check
 * fails the user lands on the bare /403 page — no shell, no white screen.
 * Routes without the metadata pass through untouched.
 */
export function RequirePermission() {
  const matches = useMatches();
  const me = useMe();

  const required = matches.flatMap((match) => {
    const permission = (match.handle as PermissionHandle | undefined)?.permission;
    if (permission === undefined) {
      return [];
    }
    return Array.isArray(permission) ? permission : [permission];
  });

  if (required.length === 0) {
    return <Outlet />;
  }

  // RequireAuth only renders the shell once /me has resolved, so the
  // permission list is already in the query cache here.
  if (me.isPending) {
    return <Loading />;
  }

  if (!hasPermission(me.data?.permissions ?? [], required)) {
    return <Navigate to="/403" replace />;
  }

  return <Outlet />;
}
