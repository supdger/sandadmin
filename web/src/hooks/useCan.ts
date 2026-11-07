import { useMe } from './useMe';

/**
 * Pure permission check against a granted permission array.
 * `'*'` in the granted list is the admin wildcard and satisfies anything;
 * otherwise every required permission must be present.
 */
export function hasPermission(granted: string[], required: string | string[]): boolean {
  const list = Array.isArray(required) ? required : [required];
  if (list.length === 0) {
    return true;
  }
  if (granted.includes('*')) {
    return true;
  }
  return list.every((p) => granted.includes(p));
}

/**
 * Permission check against the current user's permission array.
 * A string asks for one permission; an array requires ALL of them.
 * Returns false while the user is still loading or has no permissions.
 */
export function useCan(permission: string | string[]): boolean {
  const { data: me } = useMe();
  return hasPermission(me?.permissions ?? [], permission);
}
