/**
 * 菜单处理器
 *
 * 负责菜单数据的获取、过滤和处理
 *
 * @module router/core/MenuProcessor
 * @author Art Design Pro Team
 */

import type { AppRouteRecord } from '@/types/router'
import { useUserStore } from '@/store/modules/user'
import { useAppMode } from '@/hooks/core/useAppMode'
import { fetchGetMenuList } from '@/api/auth'
import { asyncRoutes } from '../routes/asyncRoutes'
import { RoutesAlias } from '../routesAlias'

export class MenuProcessor {
  /**
   * 获取菜单数据
   */
  async getMenuList(
    readMenu: () => Promise<AppRouteRecord[]> = fetchGetMenuList
  ): Promise<AppRouteRecord[]> {
    const { isFrontendMode } = useAppMode()

    let menuList: AppRouteRecord[]
    if (isFrontendMode.value) {
      menuList = await this.processFrontendMenu()
    } else {
      menuList = await this.processBackendMenu(readMenu)
    }

    // 规范化路径（将相对路径转换为完整路径）
    return this.normalizeMenuPaths(this.hideDevPages(menuList))
  }

  /**
   * 费用核对、应用试用、AI 工作流、任务排障、材料整理只在开发登录里出现。
   * 路由仍注册，普通构建不放进侧栏。
   */
  private hideDevPages(menuList: AppRouteRecord[]): AppRouteRecord[] {
    if (import.meta.env.VITE_SAND_DEV_LOGIN === '1') {
      return menuList
    }
    const hidden = new Set([
      '/sand-ai/usage-review',
      '/sand-ai/app-trial',
      '/sand-ai/ai-workflows',
      '/sand-ai/task',
      '/sand-ai/material-config'
    ])
    const walk = (items: AppRouteRecord[]): AppRouteRecord[] =>
      items.flatMap((item) => {
        const path = item.path || ''
        if (hidden.has(path)) {
          return []
        }
        const children = item.children ? walk(item.children) : item.children
        return [{ ...item, children }]
      })
    return walk(menuList)
  }

  /**
   * 处理前端控制模式的菜单
   */
  private async processFrontendMenu(): Promise<AppRouteRecord[]> {
    const userStore = useUserStore()
    const roles = userStore.info?.roles

    let menuList = [...asyncRoutes]

    // 根据角色过滤菜单
    if (roles && roles.length > 0) {
      menuList = this.filterMenuByRoles(menuList, roles)
    }

    return this.filterEmptyMenus(menuList)
  }

  /**
   * 处理后端控制模式的菜单
   */
  private async processBackendMenu(
    readMenu: () => Promise<AppRouteRecord[]>
  ): Promise<AppRouteRecord[]> {
    const list = await readMenu()
    return this.filterEmptyMenus(list)
  }

  /**
   * 根据角色过滤菜单
   */
  private filterMenuByRoles(menu: AppRouteRecord[], roles: string[]): AppRouteRecord[] {
    return menu.reduce((acc: AppRouteRecord[], item) => {
      const itemRoles = item.meta?.roles
      const hasPermission = !itemRoles || itemRoles.some((role) => roles?.includes(role))

      if (hasPermission) {
        const filteredItem = { ...item }
        if (filteredItem.children?.length) {
          filteredItem.children = this.filterMenuByRoles(filteredItem.children, roles)
        }
        acc.push(filteredItem)
      }

      return acc
    }, [])
  }

  private isLayoutComponent(component: AppRouteRecord['component']): boolean {
    return component === undefined || component === '' || component === RoutesAlias.Layout
  }

  private hasPageChild(item: AppRouteRecord): boolean {
    return (item.children ?? []).some((child) => {
      if (child.meta?.isIframe === true || Boolean(child.meta?.link)) return true
      if (!this.isLayoutComponent(child.component)) return true
      return this.hasPageChild(child)
    })
  }

  /**
   * 只放按钮权限的目录没有页面。丢掉它会整页 404，继续用目录组件又会把布局嵌进内容区。
   */
  private asButtonDirectory(item: AppRouteRecord): AppRouteRecord {
    if (!item.path || !this.isLayoutComponent(item.component) || this.hasPageChild(item)) {
      return item
    }
    const page = { ...item, component: '/system/button-directory' }
    delete page.children
    return page
  }

  /**
   * 递归过滤空菜单项
   */
  private filterEmptyMenus(menuList: AppRouteRecord[]): AppRouteRecord[] {
    return menuList
      .map((item) => {
        // 如果有子菜单，先递归过滤子菜单
        const withChildren =
          item.children && item.children.length > 0
            ? { ...item, children: this.filterEmptyMenus(item.children) }
            : item
        return this.asButtonDirectory(withChildren)
      })
      .filter((item) => {
        // 如果定义了 children 属性（即使是空数组），说明这是一个目录菜单，应该保留
        if ('children' in item) {
          return true
        }

        // 如果有外链或 iframe，保留
        if (item.meta?.isIframe === true || item.meta?.link) {
          return true
        }

        // 如果有有效的 component，保留
        if (item.component && item.component !== '' && item.component !== RoutesAlias.Layout) {
          return true
        }

        // 其他情况过滤掉
        return false
      })
  }

  /**
   * 验证菜单列表是否有效
   */
  validateMenuList(menuList: AppRouteRecord[]): boolean {
    return Array.isArray(menuList) && menuList.length > 0
  }

  /**
   * 规范化菜单路径
   * 将相对路径转换为完整路径，确保菜单跳转正确
   */
  private normalizeMenuPaths(menuList: AppRouteRecord[], parentPath = ''): AppRouteRecord[] {
    return menuList.map((item) => {
      // 构建完整路径
      const fullPath = this.buildFullPath(item.path || '', parentPath)

      // 递归处理子菜单
      const children = item.children?.length
        ? this.normalizeMenuPaths(item.children, fullPath)
        : item.children

      const redirect = item.redirect || this.resolveDefaultRedirect(children)

      return {
        ...item,
        path: fullPath,
        redirect,
        children
      }
    })
  }

  /**
   * 为目录型菜单推导默认跳转地址
   */
  private resolveDefaultRedirect(children?: AppRouteRecord[]): string | undefined {
    if (!children?.length) {
      return undefined
    }

    for (const child of children) {
      if (this.isNavigableRoute(child)) {
        return child.path
      }

      const nestedRedirect = this.resolveDefaultRedirect(child.children)
      if (nestedRedirect) {
        return nestedRedirect
      }
    }

    return undefined
  }

  /**
   * 判断子路由是否可以作为默认落点
   */
  private isNavigableRoute(route: AppRouteRecord): boolean {
    return Boolean(
      route.path &&
        route.path !== '/' &&
        !route.meta?.link &&
        route.meta?.isIframe !== true &&
        route.component &&
        route.component !== ''
    )
  }

  /**
   * 构建完整路径
   */
  private buildFullPath(path: string, parentPath: string): string {
    if (!path) return ''

    // 外部链接直接返回
    if (path.startsWith('http://') || path.startsWith('https://')) {
      return path
    }

    // 如果已经是绝对路径，直接返回
    if (path.startsWith('/')) {
      return path
    }

    // 拼接父路径和当前路径
    if (parentPath) {
      // 移除父路径末尾的斜杠，移除子路径开头的斜杠，然后拼接
      const cleanParent = parentPath.replace(/\/$/, '')
      const cleanChild = path.replace(/^\//, '')
      return `${cleanParent}/${cleanChild}`
    }

    // 没有父路径，添加前导斜杠
    return `/${path}`
  }
}
