export default {
  title: '定时任务',
  subtitle: '系统和插件自动做的那些事',

  filters: {
    keywordPlaceholder: '搜任务名',
    status: '状态',
    statusAll: '全部',
    enabled: '启用中',
    disabled: '已暂停',
    // 走契约 failed_only 查询参数（last_run_succeeded=false）。
    failedOnly: '上次没有成功',
  },

  failedBanner: '有 {{count}} 个任务上次没有成功',
  failedOnly: '只看失败的',
  showAll: '查看全部',

  columns: {
    task: '任务',
    schedule: '什么时候做',
    last: '上次执行',
    next: '下次执行',
    enabled: '启用',
    actions: '操作',
  },

  source: {
    system: '系统',
    plugin: '插件',
  },

  schedule: {
    interval: '每 {{count}} 分钟',
    hourly: '每小时第 {{minute}} 分',
    daily: '每天 {{time}}',
    weekly: '每周{{weekday}} {{time}}',
    monthly: '每月 {{day}} 号 {{time}}',
    weekdays: ['一', '二', '三', '四', '五', '六', '日'],
    bySystem: '时间由系统定',
    unread: '暂时读不到',
  },

  lastResult: {
    ok: '成功',
    failed: '没有成功',
    never: '还没执行过',
  },

  // 「下次」列：status=disabled 时写「已暂停」而不是具体时间点。
  nextRun: {
    paused: '已暂停',
  },

  actions: {
    runNow: '立即执行',
    running: '执行中…',
    editTime: '改时间',
    runs: '执行记录',
    locked: '这个任务的时间由系统控制，不能在页面上改',
  },

  edit: {
    title: '改「{{name}}」的时间',
    freqLabel: '多久做一次',
    freq: {
      interval: '每隔几分钟',
      hourly: '每小时',
      daily: '每天',
      weekly: '每周几',
      monthly: '每月几号',
    },
    intervalLabel: '间隔分钟',
    intervalRule: '填 5 到 60 之间的整数',
    timeLabel: '几点做',
    minuteLabel: '第几分',
    weekdayLabel: '周几',
    monthdayLabel: '几号',
    monthdayRule: '填 1 到 31',
    monthEndHint: '选了 29 到 31 号时，没有的月份会在当月最后一天执行',
    previewTitle: '接下来三次会在',
    save: '保存',
    cancel: '取消',
    saved: '已保存「{{name}}」的时间，下次执行 {{time}}',
  },

  toggle: {
    pauseConfirmTitle: '暂停「{{name}}」？',
    pauseConfirmContent: '暂停后它就不会自动执行了，随时可以再启用。',
    pause: '暂停',
    think: '再想想',
    paused: '已暂停「{{name}}」',
    enabled: '已启用「{{name}}」',
  },

  run: {
    done: '「{{name}}」已完成',
    failed: '「{{name}}」没有成功：{{reason}}',
  },

  runsDrawer: {
    title: '「{{name}}」的执行记录',
    time: '时间',
    trigger: '怎么触发的',
    manual: '手动执行',
    auto: '自动执行',
    state: '结果',
    duration: '用时',
    durationSeconds: '{{count}} 秒',
    message: '说明',
    close: '关闭',
    empty: '还没有执行记录',
    stateRunning: '执行中',
    stateOk: '成功',
    stateFailed: '没有成功',
    systemFail: '系统出了点问题，请稍后再试',
  },

  noPermission: '你还没有查看定时任务的权限',
  loadFailed: '定时任务没能加载出来',
} as const;
