export default {
  title: '消息',
  subtitle: '系统和各功能发给你的提醒',

  bell: {
    tooltip: '消息',
  },

  panel: {
    title: '消息',
    markAll: '全部标为已读',
    viewAll: '查看全部',
    empty: '还没有消息',
    emptyDesc: '有需要你知道的事情时，会在这里提醒你',
    loadFailed: '消息没能加载出来，系统出了点问题，请稍后再试',
    unread: '未读',
  },

  page: {
    tabAll: '全部',
    tabUnread: '未读',
    markAll: '全部标为已读',
    markedAll: '已全部标为已读',
    markAllFailed: '没能全部标为已读，请稍后再试',
    empty: '还没有消息',
    emptyDesc: '有需要你知道的事情时，会在这里提醒你',
    emptyUnread: '消息都看过了',
    announcementTag: '公告',
  },

  announcement: {
    gotIt: '知道了',
    readFailed: '没能标记已读，请稍后再试',
  },

  time: {
    justNow: '刚刚',
    minutesAgo: '{{count}} 分钟前',
    todayAt: '今天 {{time}}',
    dateTime: '{{date}} {{time}}',
  },
} as const;
