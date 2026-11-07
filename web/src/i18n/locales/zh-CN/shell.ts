export default {
  menu: {
    loadFailed: '菜单加载失败了',
  },

  header: {
    themeToLight: '切换到浅色模式',
    themeToDark: '切换到深色模式',
    preferences: '偏好设置',
    logout: '退出登录',
  },

  workbench: {
    title: '工作台',
    greeting: {
      morning: '上午好，{{name}}',
      afternoon: '下午好，{{name}}',
      evening: '晚上好，{{name}}',
      fallbackName: '朋友',
    },
    subtitle: '欢迎回来，开始今天的工作吧',
    recentVisits: {
      title: '最近访问',
      empty: '你最近访问的页面会显示在这里',
    },
    announcements: {
      title: '系统公告',
      empty: '暂时没有新的公告',
    },
    todos: {
      title: '待办事项',
      empty: '太棒了，现在没有待办事项',
    },
  },

  preferences: {
    title: '偏好设置',
    appearance: {
      title: '外观',
      themeLabel: '主题模式',
      auto: '跟随系统',
      light: '浅色',
      dark: '深色',
    },
    notifications: {
      title: '通知',
      emailLabel: '邮件通知',
      emailHint: '有重要消息时给你发邮件提醒',
    },
    saved: '偏好已保存',
    saveFailed: '保存失败了，请稍后再试',
  },

  result: {
    success: {
      title: '操作成功',
      description: '一切顺利，可以继续下一步了',
    },
    fail: {
      title: '操作失败',
      description: '出了点问题，请返回重试',
    },
    continue: '继续',
  },
} as const;
