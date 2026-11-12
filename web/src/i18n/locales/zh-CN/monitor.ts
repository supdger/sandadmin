export default {
  title: '运行状态',
  subtitle: '看看服务器现在跑得怎么样',

  conclusion: {
    ok: '一切正常',
    dbDown: '数据库连不上，请尽快处理',
    warn: '有资源快不够用了',
  },

  cards: {
    cpu: '处理器忙碌度',
    cpuSub: '本系统占用的处理器',
    memory: '内存使用',
    memorySub: '本系统占用的内存',
    disk: '磁盘空间',
    diskSub: '这台服务器还剩',
    diskWarn: '磁盘快满了',
    diskClean: '磁盘有点紧张，去文件管理清理一下',
    database: '数据库连接',
    dbOk: '正常',
    dbDown: '连不上',
    dbDownDesc: '数据库现在连不上，登录和保存都会受影响。',
    dbNext: '请检查数据库服务是否已启动，或联系负责部署的人。',
    dbCheckTime: '最近检查 {{time}}',
    unread: '暂时读不到',
  },

  info: {
    version: '版本 {{version}}',
    serverTime: '服务器时间 {{time}}',
    uptime: '已连续运行 {{duration}}',
    updatedAt: '数据更新于 {{time}}',
  },

  actions: {
    pause: '暂停更新',
    resume: '继续更新',
    paused: '已暂停，数字不会变',
    copy: '复制状态摘要',
    copied: '已复制，可以直接发给管理员',
    goFiles: '去文件管理',
  },

  loadFailed: '服务器状态没能加载出来',
} as const;
