import { useEffect } from 'react';

import { useQuery } from '@tanstack/react-query';
import { Navigate, Outlet, useLocation } from 'react-router';

import { getMe } from '@/api/auth';
import { Loading } from '@/components/Loading';
import { ME_QUERY_KEY } from '@/hooks/useMe';
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
