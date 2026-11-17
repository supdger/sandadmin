export default {
  title: '插件页面',
  notFound: {
    title: '插件页面不存在',
    description: '这个页面没有对应的功能，可能地址输错了',
  },
  notInstalled: {
    title: '插件未安装',
    description: '这个功能对应的插件没有安装或已经卸载',
  },
  forbidden: {
    title: '无权访问该插件',
    description: '当前账号没有使用这个插件的权限',
  },
} as const;
