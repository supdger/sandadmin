/**
 * 表单/搜索/表格/编辑器命名空间（表单包拥有）。
 * 新增表单域 key 只改本文件。
 */
export default {
  validation: {
    required: '该项为必填',
  },
  actions: {
    edit: '编辑',
    delete: '删除',
    more: '更多',
    submit: '提交',
    search: '查询',
    reset: '重置',
    create: '新建',
    refresh: '刷新',
    expand: '展开',
    collapse: '收起',
  },
  table: {
    selected: '已选 {{count}} 项',
    columns: '列设置',
    density: '密度',
    densityDefault: '默认',
    densityMiddle: '中等',
    densitySmall: '紧凑',
    confirmDanger: '确定执行该操作吗？',
    batchDeleteConfirm: '确定删除选中的 {{count}} 项吗？删除后不可恢复',
  },
  dict: {
    loading: '选项加载中…',
    empty: '暂无选项',
    placeholder: '请选择',
    emptyValue: '—',
  },
  form: {
    uploadFile: '选择文件',
  },
  user: {
    placeholder: '搜索并选择用户',
  },
  editor: {
    placeholder: '请输入内容',
    pickImage: '从图库选图',
    galleryTitle: '选择图片',
    galleryEmpty: '图库还没有图片',
    uploadImage: '上传图片',
    uploadFailed: '图片上传失败，请稍后再试',
    write: '编辑',
    split: '双栏',
    preview: '预览',
    markdownPlaceholder: '支持 Markdown 语法',
  },
  crud: {
    saveFailed: '保存失败，请稍后再试',
  },
} as const;
