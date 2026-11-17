/**
 * 其他通用组件命名空间（通用件包拥有：右键菜单、水印、
 * 图标选择器、上传组件、登录页 UI 件等）。
 * 新增通用件 key 只改本文件。
 */
export default {
  iconPicker: {
    placeholder: '选择图标',
    search: '搜索图标',
    clear: '清除',
    empty: '没有匹配的图标',
  },
  upload: {
    choose: '选择文件',
    chooseImage: '选择图片',
    sizeError: '文件「{{name}}」超过了 {{limit}}MB 的大小上限',
    exceed: '最多只能上传 {{count}} 个文件',
    failed: '上传失败，请重试',
    deleteConfirm: '确定删除这个文件？',
    deleteConfirmContent: '删除后引用它的内容会看不到这个文件',
    confirmDelete: '删除',
    imageAlt: '预览图',
  },
  imageCropper: {
    title: '裁剪图片',
    zoom: '缩放',
    confirm: '确定',
    cancel: '取消',
  },
  authTopBar: {
    toggleTheme: '切换深色/浅色',
  },
  loginLeft: {
    slogan: '一套界面，管好整摊生意',
    description: '菜单、权限、字典、文件和后台任务都在一个地方，点开就能用。',
  },
} as const;
