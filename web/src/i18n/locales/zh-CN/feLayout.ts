/**
 * 布局/菜单/多标签/外观命名空间（布局包拥有）。
 * 新增布局域 key 只改本文件。
 */
export default {
  tabs: {
    close: '关闭',
    closeOthers: '关闭其他',
    closeAll: '关闭全部',
  },
  collapse: {
    fold: '收起菜单',
    unfold: '展开菜单',
  },
  breadcrumb: {
    home: '首页',
  },
  header: {
    themeToLight: '切换到浅色模式',
    themeToDark: '切换到深色模式',
    settings: '设置',
  },
  menu: {
    loadFailed: '菜单加载失败了',
  },
  settings: {
    title: '界面设置',
    themeMode: '主题模式',
    modeLight: '浅色',
    modeDark: '深色',
    modeAuto: '跟随系统',
    themeColor: '主题色',
    colors: {
      blue: '蓝色',
      violet: '紫色',
      green: '绿色',
      orange: '橙色',
      red: '红色',
    },
    layoutMode: '布局模式',
    layoutSide: '侧边菜单',
    layoutTop: '顶部菜单',
    layoutMix: '混合菜单',
    tabs: '多标签页',
    lockNow: '立即锁屏',
  },
  lock: {
    title: '屏幕已锁定',
    tip: '页面已锁定，解锁需要重新登录',
    unlock: '解锁',
  },
  fastEnter: {
    empty: '暂无快捷入口',
    loadFailed: '快捷入口加载失败了',
  },
  backToTop: {
    label: '回到顶部',
  },
} as const;
