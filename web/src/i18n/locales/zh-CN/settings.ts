export default {
  title: '系统设置',
  subtitle: '改名字、登录方式这些全局的东西',

  readonlyBanner: '你只能查看，不能修改',

  groups: {
    // 「登录与安全」组（契约组键 login）的副标。
    loginHint: '一般不用改',
  },

  form: {
    save: '保存',
    saved: '已保存「{{group}}」',
    discardTitle: '放弃刚才的修改吗？',
    discardOk: '放弃',
    discardCancel: '再想想',
    clearedToDefault: '清空后用默认值：{{value}}',
    required: '必填，不能为空',
    secretSet: '已设置，需要改请联系负责部署的人',
    logoLight: '浅色模式效果',
    logoDark: '深色模式效果',
    logoUpload: '上传图片',
    sessionShrinkTitle: '改成 {{days}} 天？',
    sessionShrinkContent: '改短以后，已经登录的人会更快被退出，需要重新登录。',
    sessionShrinkOk: '改成 {{days}} 天',
    think: '再想想',
  },

  noPermission: '你还没有查看系统设置的权限',
  loadFailed: '设置没能加载出来',
} as const;
