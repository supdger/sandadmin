import { useMe } from './useMe';

/**
 * Permission check against the current user's permission array.
 * A string asks for one permission; an array requires ALL of them.
 * Returns false while the user is still loading or has no permissions.
 */
export function useCan(permission: string | string[]): boolean {
  const { data: me } = useMe();
  const granted = me?.permissions ?? [];
  const required = Array.isArray(permission) ? permission : [permission];
  return required.every((p) => granted.includes(p));
}
