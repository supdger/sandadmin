export default {
  title: '文件管理',
  subtitle: '系统里上传过的所有文件和图片',

  upload: '上传',
  dropHint: '松开即可上传',
  cleanup: '清理没用的旧文件',
  usedSpace: '已用 {{size}}',

  filters: {
    keywordPlaceholder: '搜索文件名',
    kind: '类型',
    kindAll: '全部类型',
    kindImage: '图片',
    kindDocument: '文档',
    kindSheet: '表格',
    kindArchive: '压缩包',
    kindOther: '其他',
    timeRange: '上传时间',
    timeFrom: '开始日期',
    timeTo: '结束日期',
    moreFilters: '更多条件',
    hideFilters: '收起条件',
    usage: '使用情况',
    usageAll: '全部',
    usageUsed: '正在使用',
    usageUnused: '没有被使用',
    usageUnknown: '暂时看不出用在哪里',
    clearFilters: '清空筛选条件',
  },

  view: {
    list: '列表',
    grid: '图片',
  },

  table: {
    file: '文件',
    size: '大小',
    usage: '用在哪里',
    uploader: '上传人',
    createTime: '上传时间',
    actions: '操作',
    preview: '预览',
    download: '下载',
    more: '更多',
    delete: '删除',
    usageUsed: '正在使用',
    usageUnused: '没有被使用',
    usageUnknown: '暂时看不出用在哪里',
  },

  batch: {
    selected: '已选 {{count}} 项',
    download: '下载',
    delete: '删除',
    deleteBlockedTitle: '不能批量删除',
    deleteBlocked: '包含“暂时看不出用在哪里”的文件，不能批量删除',
  },

  empty: {
    title: '还没有上传过文件',
    desc: '把文件拖到这里，或点“上传”',
  },
  noResult: '没有找到符合条件的文件',

  uploadPanel: {
    title: '正在上传',
    uploading: '上传中…',
    done: '已完成',
    failed: '失败',
    dismiss: '收起',
  },

  sizeLimit: '这个文件超过 100 MB，传不上去',
  uploaded: '已上传 {{count}} 个文件',
  deleted: '已删除 {{count}} 个文件，空出 {{size}}',
  cleaned: '已清理 {{count}} 个文件，空出 {{size}}',
  cleanedDone: '清理完成',
  cleanedFailed: '清理失败了，请稍后再试',
  downloadFailed: '下载失败了，请稍后再试',

  confirmDeleteUnusedTitle: '删除这 {{count}} 个文件？',
  confirmDeleteUnusedContent: '删除后不能找回，会空出 {{size}}。',
  confirmDeleteUsedTitle: '其中 {{count}} 个文件正在使用',
  confirmDeleteUsedContent:
    '删除后，用到它们的地方（{{labels}}）会显示“文件已删除”。不能找回。',
  confirmDeleteUnknownTitle: '删除“{{name}}”？',
  confirmDeleteUnknownContent:
    '可能有地方还在用这个文件，删了以后那里会显示“文件已删除”，不能找回。',
  confirmDeleteUsedSingleTitle: '删除“{{name}}”？',
  confirmDeleteUsedSingleContent:
    '它正在被使用（{{usage}}），删除后那里会显示“文件已删除”，不能找回。',
  deleteAnyway: '仍然删除',
  onlyUnused: '只删没被使用的',
  deleteOk: '删除',
  think: '再想想',

  confirmCleanupTitle: '清理没用的旧文件？',
  confirmCleanupContent:
    '只清理确认没有地方在用、并且上传超过 30 天的文件，删除后不能找回。',
  cleanupOk: '清理',
} as const;
