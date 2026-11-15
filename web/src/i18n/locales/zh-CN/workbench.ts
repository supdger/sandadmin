export default {
  greeting: {
    morning: '上午好，{{name}}',
    noon: '中午好，{{name}}',
    afternoon: '下午好，{{name}}',
    evening: '晚上好，{{name}}',
    fallbackName: '朋友',
    date: '今天是 {{month}}月{{day}}日 {{weekday}}',
    weekday: {
      sun: '星期日',
      mon: '星期一',
      tue: '星期二',
      wed: '星期三',
      thu: '星期四',
      fri: '星期五',
      sat: '星期六',
    },
    subtitle: '这里是每天先看的地方',
  },

  notices: {
    more: '还有 {{count}} 条提示',
    dismissTooltip: '先不提醒',
    dismissFailed: '没能处理这条提示，请稍后再试',
    pluginUpgrade: '有 {{count}} 个插件可以升级到新版本',
    aiProviderMissing: '还没有连接 AI 服务商，AI 功能暂时用不了',
    actions: {
      goUpgrade: '去升级',
      goConnect: '去连接',
    },
  },

  onboarding: {
    title: '开始使用前，建议先做这几件事',
    steps: {
      add_user: { title: '添加用户', action: '去添加' },
      set_roles: { title: '设置角色与权限', action: '去设置' },
      connect_ai: { title: '连接 AI 服务商', action: '去连接' },
    },
    links: {
      add_user: '/permission/user',
      set_roles: '/permission/role',
      connect_ai: '/plugin/sand-ai/provider',
    },
  },

  cards: {
    users: { title: '用户', hint: '全部账号' },
    logins: { title: '今天登录', hint: '今天登录过的人数' },
    ai_usage: { title: '本月 AI 使用', hint: '本月 AI 调用次数' },
    workflow_pending: { title: '待处理流程', hint: '还没处理完的流程' },
    error: '这张卡没能加载出来',
  },

  todosSection: {
    title: '待我处理',
    viewAll: '查看全部',
    empty: '都处理完了，没有要你处理的事',
    overdue: '已超时',
  },

  todos: {
    title: '我的待办',
    subtitle: '需要你处理的事都在这里',
    empty: '都处理完了，没有要你处理的事',
    emptyHint: '新待办出现时会显示在这里',
    overdue: '已超时',
    sourceLabel: '来源',
    timeLabel: '时间',
    handle: '去处理',
  },

  shortcuts: {
    title: '快捷入口',
    adjust: '调整',
    empty: '还没有快捷入口',
    modalTitle: '调整快捷入口',
    addLabel: '添加入口',
    addPlaceholder: '搜索要添加的入口',
    remove: '删除',
    save: '保存',
    restore: '恢复默认',
    maxReached: '最多 8 个，先删掉一个再添加',
    autoTag: '推荐',
    saved: '快捷入口已更新',
    restored: '已恢复默认',
    saveFailed: '快捷入口没能保存，请稍后再试',
  },

  charts: {
    title: '数据趋势',
    range7: '最近 7 天',
    range30: '最近 30 天',
    range90: '最近 90 天',
    empty: '还没有数据，有了会显示在这里',
    titles: {
      login_trend: '最近登录人数',
      ai_usage: 'AI 使用量',
    },
    units: {
      login_trend: '人',
      ai_usage: '次',
    },
  },

  activities: {
    title: '最近动态',
    empty: '还没有动态，有人操作后会显示在这里',
  },

  time: {
    justNow: '刚刚',
    minutesAgo: '{{count}} 分钟前',
    todayAt: '今天 {{time}}',
    dateTime: '{{date}} {{time}}',
  },
} as const;
