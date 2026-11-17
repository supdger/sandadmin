// 示例插件词条：注册进 plugin-sand-hello 命名空间，不撞宿主 key。
export default {
  menu: {
    home: '你好插件',
  },
  page: {
    title: '你好，插件',
    desc: '这是示例插件 sand-hello 的首页，演示插件页面挂在 /plugin/sand-hello 下。',
  },
  about: {
    title: '关于你好插件',
    desc: '插件可以自己声明子路由，这个页面来自插件的 about 路由。',
  },
  action: {
    about: '去看看关于页',
    back: '返回插件首页',
  },
} as const;
