export default {
  title: '插件管理',
  subtitle: '给后台加功能，或者升级、卸载已有的功能',
  subtitleReadonly: '查看已经安装的插件',

  compiledNotice: '这个后台不能在网页上安装或升级插件，要加插件请找负责部署的人。',
  opsDisabledNotice: '网页上的安装和升级已关闭，请联系负责部署的人。',
  marketDown: '暂时看不到可以安装的新插件，请稍后再试。已安装的插件不受影响。',

  tabs: {
    all: '全部插件',
    installed: '已安装（{{count}}）',
    upgradeable: '有新版本（{{count}}）',
  },
  searchPlaceholder: '搜插件名或它能做什么',
  clearSearch: '清空搜索',

  states: {
    installed: '已安装',
    uninstalled: '未安装',
    upgradeable: '有新版本',
    rolledBack: '没装好，已自动恢复',
  },
  versionArrow: '{{from}} → {{to}}',

  actions: {
    install: '安装',
    upgrade: '升级',
    open: '打开',
    upgradeAll: '全部升级（{{count}}）',
    upload: '上传安装包',
    detail: '详情',
  },

  detail: {
    title: '插件详情',
    version: '版本',
    latestVersion: '可升级到 {{version}}',
    provides: '装好后会多出这些菜单',
    more: '更多',
    uninstall: '卸载',
  },

  uninstall: {
    title: '卸载「{{name}}」？',
    content: '卸载后左侧菜单里的「{{name}}」相关功能会消失，用它的人也打不开了。数据默认保留，重新安装后会回来。',
    purge: '同时删除这个插件的所有数据（删了就找不回来）',
    confirm: '卸载',
    confirmPurge: '卸载并删除数据',
    cancel: '再想想',
    blockedTitle: '现在还不能卸载「{{name}}」',
    blockedOk: '知道了',
  },

  progress: {
    batchSummary: '已升级 {{done}} / {{total}} 个',
  },

  success: {
    installed: '已安装「{{name}}」，左侧菜单里已经能看到了',
    upgraded: '已升级「{{name}}」到 {{version}}',
    uninstalled: '已卸载「{{name}}」，数据已保留，重新安装后会恢复',
    uninstalledPurged: '已卸载「{{name}}」并删除了它的数据',
    batchDone: '已升级 {{count}} 个插件',
    batchPartial: '{{done}} 个已升级，{{failed}} 个没做完，已自动恢复到原来的版本：{{names}}',
  },

  failed: {
    rolledBackTitle: '没做完，已自动恢复',
    rolledBackDesc: '升级没有完成，系统已自动恢复到原来的版本，你的数据没有变化。可以稍后再试。',
    notRecoveredTitle: '没做完，也没能自动恢复，请马上联系负责部署的人',
  },

  empty: {
    installed: '还没有安装插件',
    installedDesc: '在「全部插件」里挑一个，点「安装」就能用',
    goMarket: '去看全部插件',
    noResult: '没有找到相关的插件',
  },

  loadFailed: '插件列表没能加载出来',
  uploadInvalid: '这个文件不是能用的安装包，请检查后再试',
} as const;
