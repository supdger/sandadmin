export default {
  title: '安装向导',
  intro: '几步就能装好，跟着指引来就行',

  installed: {
    title: '系统已安装',
    description: '检测到系统已经装好了，直接去登录就行。',
    goLogin: '去登录',
  },

  steps: {
    env: '环境检测',
    admin: '管理员账号',
    site: '站点信息',
    confirm: '确认并安装',
  },

  env: {
    checking: '正在检查运行环境…',
    ready: '环境都就绪了，可以继续。',
    retry: '重新检测',
    failed: '环境检测没通过，请处理后再试。',
  },

  admin: {
    tip: '先创建第一个管理员账号，装好后用它登录。',
    username: '管理员用户名',
    nickname: '昵称',
    password: '密码',
    confirmPassword: '确认密码',
    usernamePlaceholder: '比如 admin',
    nicknamePlaceholder: '比如 管理员',
    passwordPlaceholder: '请设置登录密码',
    confirmPasswordPlaceholder: '请再输入一次密码',
    required: '这一项必填',
    mismatch: '两次输入的密码不一样',
    passwordMin: '至少 {{count}} 个字符',
    passwordMixedCase: '要同时包含大写和小写字母',
    passwordDigit: '至少包含一个数字',
    passwordSymbol: '至少包含一个符号（比如 !@#）',
  },

  site: {
    tip: '站点名称会显示在浏览器标题和登录页上，可以先跳过。',
    siteName: '站点名称',
    siteNamePlaceholder: '不填就用默认名称',
  },

  confirm: {
    tip: '再确认一下，没问题就开始安装。',
    adminUsername: '管理员用户名',
    adminNickname: '管理员昵称',
    siteName: '站点名称',
    siteNameDefault: '（默认）',
    start: '开始安装',
  },

  progress: {
    title: '正在安装，请稍等…',
    stage: '当前步骤：{{stage}}',
    resumeTip: '检测到上次未完成的安装，已继续显示进度。',
  },

  failed: {
    title: '这次安装没成功',
    reason: '原因：{{reason}}',
    retry: '重新安装',
  },

  result: {
    title: '安装完成',
    description: '管理员账号已经建好，现在可以去登录了。',
  },

  actions: {
    prev: '上一步',
    next: '下一步',
  },
} as const;
