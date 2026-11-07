export default {
  loading: '加载中…',

  actions: {
    confirm: '确认',
    cancel: '取消',
    retry: '重试',
    backHome: '返回首页',
    search: '搜索',
    reset: '重置',
    create: '新建',
    refresh: '刷新',
  },

  empty: {
    title: '暂无数据',
    description: '这里还没有内容',
  },

  errors: {
    network: '网络好像出了点问题，请稍后再试',
    server: '服务好像出了点问题，请稍后再试',
    forbidden: {
      title: '没有权限',
      description: '你还没有访问这个页面的权限，需要的话可以联系管理员开通',
    },
    notFound: {
      title: '页面不存在',
      description: '你要找的页面不存在，可能已被移动或删除',
    },
    serverError: {
      title: '出了点问题',
      description: '服务暂时出了点状况，请稍后再试',
    },
    generic: {
      title: '出了点问题',
      description: '请稍后再试',
    },
  },

  list: {
    total: '共 {{count}} 条',
  },
} as const;
