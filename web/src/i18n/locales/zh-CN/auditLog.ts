export default {
  title: '操作日志',
  subtitle: '谁在后台改过什么，改了什么',

  filters: {
    keywordPlaceholder: '搜操作内容',
    actorPlaceholder: '操作人',
    rangePlaceholder: ['开始日期', '结束日期'],
    rangeToday: '今天',
    range7d: '近 7 天',
    range30d: '近 30 天',
    more: '更多条件',
    collapse: '收起条件',
    module: '功能',
    moduleAll: '全部功能',
    result: '结果',
    resultAll: '全部',
    succeeded: '成功',
    failed: '没有成功',
    clear: '清空筛选',
    export: '导出',
  },

  columns: {
    actor: '操作人',
    action: '做了什么',
    module: '功能',
    result: '结果',
    location: '地点',
    time: '时间',
  },

  result: {
    ok: '成功',
    failed: '没有成功',
  },

  empty: {
    noData: '还没有操作记录',
    noDataDesc: '有人添加用户、改权限或改设置后，会记在这里',
    noResult: '没有找到符合条件的操作记录',
    noResultDesc: '换个关键词或时间范围试试',
    clearFilters: '清空筛选条件',
  },

  detail: {
    title: '操作详情',
    target: '对象',
    changes: '改动内容',
    before: '改前',
    after: '改后',
    masked: '已修改',
    noChanges: '这次操作没有可展示的改动明细',
    device: '设备',
    close: '关闭',
  },

  noPermission: '你还没有查看操作日志的权限',
  loadFailed: '操作日志没能加载出来',
  exportAccepted: '导出已开始，完成后到任务中心下载',
} as const;
