import { useEffect, useMemo } from 'react';

import { useLocation } from 'react-router';

import { reportVisits } from '@/api/dashboard';
import type { MenuItem } from '@/api/menus';
import { buildMenuTree, findMenuChain } from '@/layouts/menuTree';

const THROTTLE_MS = 60_000;

// Module-level so the throttle survives remounts within the same session.
const reportedAt = new Map<string, number>();

/**
 * Reports the menu item matching the current path to /dashboard/visits.
 * One report per menu key per 60 seconds; failures are silent — visit
 * reporting must never disturb the user.
 */
export function useVisitReport(menus: MenuItem[] | undefined): void {
  const { pathname } = useLocation();
  const tree = useMemo(() => buildMenuTree(menus ?? []), [menus]);

  useEffect(() => {
    if (!menus || menus.length === 0) {
      return;
    }
    const chain = findMenuChain(tree, pathname);
    const leaf = chain[chain.length - 1];
    const menuKey = leaf?.path ? leaf.id : null;
    if (!menuKey) {
      return;
    }
    const now = Date.now();
    if (now - (reportedAt.get(menuKey) ?? 0) < THROTTLE_MS) {
      return;
    }
    reportedAt.set(menuKey, now);
    void reportVisits([menuKey]).catch(() => undefined);
  }, [pathname, tree, menus]);
}
