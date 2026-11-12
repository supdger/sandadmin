export default {
  title: '登录日志',
  subtitle: '谁在什么时间登录过这个后台',

  filters: {
    keywordPlaceholder: '搜用户',
    result: '登录结果',
    resultAll: '全部',
    succeeded: '成功',
    failed: '没有成功',
    rangePlaceholder: ['开始日期', '结束日期'],
    rangeLimit: '只能查最近 180 天',
    clear: '清空筛选',
    export: '导出',
  },

  columns: {
    user: '用户',
    result: '结果',
    reason: '失败原因',
    time: '登录时间',
    location: '所在地区',
    device: '登录设备',
  },

  result: {
    ok: '成功',
    failed: '没有成功',
  },

  empty: {
    noData: '这段时间还没有登录记录',
    noResult: '没有找到相关的登录记录',
  },

  detail: {
    title: '登录详情',
    address: '登录设备地址',
    close: '关闭',
    viewUser: '查看用户',
  },

  noPermission: '你还没有查看登录日志的权限',
  loadFailed: '登录日志没能加载出来',
  exportAccepted: '导出已开始，完成后到任务中心下载',
} as const;
