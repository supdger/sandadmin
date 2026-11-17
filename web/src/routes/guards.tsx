import { useEffect } from 'react';

import { useQuery } from '@tanstack/react-query';
import { Navigate, Outlet, useLocation, useMatches } from 'react-router';

import { getMe } from '@/api/v1/me';
import { Loading } from '@/components/Loading';
import { hasPermission } from '@/hooks/useCan';
import { ME_QUERY_KEY, useMe } from '@/hooks/useMe';
import { clearToken, getToken } from '@/lib/authToken';
import { loginUrl, PATH_FORBIDDEN, PATH_FORCE_CHANGE_PASSWORD } from './paths';

/**
 * Guard for everything behind the shell: no token → /login (with
 * ?redirect=), must_change_password → /force-change-password,
 * profile failure drops the token and returns to /login.
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

  if (me.data?.must_change_password && location.pathname !== PATH_FORCE_CHANGE_PASSWORD) {
    return <Navigate to={PATH_FORCE_CHANGE_PASSWORD} replace />;
  }

  return <Outlet />;
}

interface PermissionHandle {
  permission?: string | string[];
}

/**
 * Permission guard inside the shell: `handle.permission` declares the
 * required code(s); `'*'` satisfies everything; failure lands on the
 * bare /403 page. Routes without the metadata pass through.
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
    return <Navigate to={PATH_FORBIDDEN} replace />;
  }

  return <Outlet />;
}
