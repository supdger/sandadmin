export default {
  title: '系统更新',
  subtitle: '一键升级后台和所有已安装的插件',

  compiledNotice: '这是编译版，不能在网页上升级。请让负责部署的人在开发环境升级后重新打包上传。',
  opsDisabledNotice: '网页上的安装和升级已关闭，请联系负责部署的人。',

  currentVersion: '现在的版本 {{version}}',
  latestTag: '已经是最新版本 {{version}}',
  latestTip: '有新版本时会在工作台提醒你',
  canUpgradeTo: '可以升级到 {{version}}',
  upgradeTo: '升级到 {{version}}',
  uploadPackage: '上传升级包',
  checkAgain: '再检查一次',

  changesTitle: '你会看到的变化',
  bundledTitle: '这些插件会一起升级',
  bundledEmpty: '本次只升级系统本身',

  upgrading: '正在升级系统',
  success: '已升级到 {{version}}',
  successRefresh: '升级好了，刷新页面后用新版本',
  refreshPage: '刷新页面',
  rolledBackTitle: '没做完，已自动恢复',
  rolledBackDesc: '升级没有完成，系统已自动恢复到原来的版本，你的数据没有变化。可以稍后再试。',
  notRecoveredTitle: '没做完，也没能自动恢复，请马上联系负责部署的人',
  upgradeAgain: '再试一次',

  loadFailed: '版本信息没能加载出来',
  uploadInvalid: '这个文件不是能用的升级包，请检查后再试',
  checkFailed: '没能检查到更新，请稍后再试',
} as const;
