export default {
  title: '任务中心',
  subtitle: '导出、清理这类后台任务的执行进度和结果',

  search: {
    typePlaceholder: '任务类型，如 user.export',
    stateAll: '全部状态',
    mineOnly: '只看我发起的',
  },

  states: {
    queued: '排队中',
    running: '执行中',
    succeeded: '已完成',
    failed: '失败',
    cancelled: '已取消',
  },

  columns: {
    type: '类型',
    state: '状态',
    progress: '进度',
    stage: '阶段',
    createTime: '发起时间',
    finishTime: '完成时间',
    error: '错误信息',
    actions: '操作',
  },

  actions: {
    refresh: '刷新',
    detail: '详情',
    download: '下载结果',
    downloaded: '已开始下载',
    downloadFailed: '结果文件没能下载，请稍后再试',
  },

  empty: '还没有任务',
  emptyDesc: '后台任务执行时会出现在这里',

  detail: {
    title: '任务详情',
    id: '任务 ID',
    startTime: '开始时间',
    resultFile: '结果文件',
    error: '失败原因',
    download: '下载结果',
    downloading: '下载中',
  },
} as const;
