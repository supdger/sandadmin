import type { AppRouteRecord } from '@/types/router'

const DEV_MENU_TITLES = new Set(['应用试用', 'AI 工作流', '任务排障', '材料整理'])
const DEV_MENU_PATHS = [
  '/sand-ai/app-trial',
  '/sand-ai/ai-workflows',
  '/sand-ai/task',
  '/sand-ai/material-config'
]

export function devSurfaceOpen(query: { dev?: unknown }): boolean {
  const value = query.dev
  if (value === '1') return true
  return Array.isArray(value) && value.includes('1')
}

/** 路径是否属于开发专用页面；路由守卫与菜单过滤共用同一份清单。 */
export function isDevSurfacePath(path: string): boolean {
  return DEV_MENU_PATHS.some((devPath) => path === devPath || path.endsWith(devPath))
}

function isDevMenu(item: AppRouteRecord): boolean {
  const title = item.meta?.title
  if (typeof title === 'string' && DEV_MENU_TITLES.has(title)) return true
  const path = item.path ?? ''
  return DEV_MENU_PATHS.some((devPath) => path === devPath || path.endsWith(devPath))
}

/** 开发页只在地址带 dev=1 时出现；登录用的 dev=1 跳走后不再留在菜单里。 */
export function hideDevMenus(menus: AppRouteRecord[], open: boolean): AppRouteRecord[] {
  if (open) return menus
  return menus.flatMap((item) => {
    if (isDevMenu(item)) return []
    if (!item.children?.length) return [item]
    return [{ ...item, children: hideDevMenus(item.children, open) }]
  })
}
