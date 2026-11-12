import { ref, nextTick } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'

/**
 * SandAdmin Composable
 * SandAdmin状态管理
 */
export function useSandAdmin() {
  // 弹窗相关
  const dialogType = ref('add')
  const dialogVisible = ref(false)
  const dialogData = ref<Partial<Record<string, any>>>({})

  // 选中行
  const selectedRows = ref<Record<string, any>[]>([])

  // 显示弹窗
  const showDialog = (type: string, row?: Record<string, any>): void => {
    dialogType.value = type
    dialogData.value = row || {}
    nextTick(() => {
      dialogVisible.value = true
    })
  }

  // 隐藏弹窗
  const hideDialog = (): void => {
    dialogVisible.value = false
  }

  // 表格行选择变化
  const handleSelectionChange = (selection: Record<string, any>[]): void => {
    selectedRows.value = selection
  }

  // 删除数据；name 传入后确认框与提示里带上具体名字
  const deleteRow = (
    row: Record<string, any>,
    apiFn: (params: any) => Promise<any>,
    callback?: () => void,
    name?: string
  ): void => {
    ElMessageBox.confirm(
      name ? `删除「${name}」？删除后不能恢复。` : '确定要删除该数据吗？删除后不能恢复。',
      '删除数据',
      {
        confirmButtonText: '删除',
        cancelButtonText: '再想想',
        confirmButtonClass: 'el-button--danger',
        type: 'warning'
      }
    ).then(() => {
      apiFn({ ids: [row.id] }).then(() => {
        ElMessage.success(name ? `已删除「${name}」` : '已删除')
        if (callback) callback()
      })
    })
  }

  // 批量删除数据
  const deleteSelectedRows = (
    apiFn: (params: any) => Promise<any>,
    callback?: () => void
  ): void => {
    if (selectedRows.value.length === 0) {
      ElMessage.warning('先勾选要删除的项')
      return
    }
    ElMessageBox.confirm(
      `确定要删除选中的 ${selectedRows.value.length} 项吗？删除后不能恢复。`,
      '删除选中数据',
      {
        confirmButtonText: '删除',
        cancelButtonText: '再想想',
        confirmButtonClass: 'el-button--danger',
        type: 'warning'
      }
    ).then(() => {
      const count = selectedRows.value.length
      apiFn({ ids: selectedRows.value.map((row) => row.id) }).then(() => {
        ElMessage.success(`已删除 ${count} 项`)
        if (callback) callback()
        selectedRows.value = []
      })
    })
  }

  return {
    dialogType,
    dialogVisible,
    dialogData,
    selectedRows,
    showDialog,
    hideDialog,
    handleSelectionChange,
    deleteRow,
    deleteSelectedRows
  }
}
