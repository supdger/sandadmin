import { describe, expect, it } from 'vitest';

import { currentLocation, externalLinkProps, isExternalUrl } from './navigation';
import { loginUrl, PATH_LOGIN } from '@/routes/paths';

describe('navigation', () => {
  it('flags http(s) and protocol-relative URLs as external', () => {
    expect(isExternalUrl('https://a.com')).toBe(true);
    expect(isExternalUrl('//a.com/x')).toBe(true);
    expect(isExternalUrl('/system/user')).toBe(false);
    expect(isExternalUrl(null)).toBe(false);
    expect(isExternalUrl('ftp://a.com')).toBe(false);
  });

  it('opens external links in a new tab without opener', () => {
    expect(externalLinkProps()).toEqual({ target: '_blank', rel: 'noopener noreferrer' });
  });

  it('encodes the redirect target in the login URL', () => {
    expect(loginUrl('/system/user?x=1')).toBe(
      `${PATH_LOGIN}?redirect=${encodeURIComponent('/system/user?x=1')}`,
    );
  });

  it('reads the current path and search', () => {
    expect(currentLocation()).toContain(window.location.pathname);
  });
});
