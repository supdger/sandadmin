/**
 * 跳转相关的纯函数。路由路径常量在 routes/paths.ts；
 * 这里只放与路径无关、各模块共用的 URL 判定与构造。
 */

/** 外部链接判定：`http(s)://` 与 `//host` 协议相对都算外链。 */
export function isExternalUrl(url: string | null | undefined): boolean {
  return typeof url === 'string' && /^(https?:)?\/\//.test(url);
}

/** 外链 `<a>` 的安全 props：新页打开且不带 window.opener。 */
export function externalLinkProps(): { target: '_blank'; rel: 'noopener noreferrer' } {
  return { target: '_blank', rel: 'noopener noreferrer' };
}

/** 当前地址的 path+search，登录跳回来时做回来地址。 */
export function currentLocation(): string {
  if (typeof window === 'undefined') {
    return '/';
  }
  return `${window.location.pathname}${window.location.search}`;
}
