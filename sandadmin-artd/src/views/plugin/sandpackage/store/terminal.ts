/**
 * 终端状态管理模块 - sandpackage插件
 *
 * 提供终端命令执行任务队列的状态管理
 *
 * @module store/terminal
 */
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { ElMessage } from 'element-plus'

/** 任务状态枚举 */
export enum TaskStatus {
  /** 等待执行 */
  WAITING = 1,
  /** 连接中 */
  CONNECTING = 2,
  /** 执行中 */
  RUNNING = 3,
  /** 执行成功 */
  SUCCESS = 4,
  /** 执行失败 */
  FAILED = 5,
  /** 未知 */
  UNKNOWN = 6
}

/** 终端任务接口 */
export interface TerminalTask {
  /** 任务唯一标识 */
  uuid: string
  /** 命令名称 */
  command: string
  /** 任务状态 */
  status: TaskStatus
  /** 执行消息 */
  message: string[]
  /** 创建时间 */
  createTime: string
  /** 是否显示消息 */
  showMessage: boolean
  /** 回调函数 */
  callback?: (status: number) => void
  /** 扩展参数 */
  extend: string
}

/**
 * 生成UUID
 */
const generateUUID = (): string => {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0
    const v = c === 'x' ? r : (r & 0x3) | 0x8
    return v.toString(16)
  })
}

/**
 * 格式化日期时间
 */
const formatDateTime = (): string => {
  const now = new Date()
  return now.toLocaleString('zh-CN', {
    hour12: false,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  })
}

/**
 * 安全 P0-F5：HTTP 在线终端已停用。
 * 浏览器不再请求 /app/sandpackage/index/terminal，也不触发服务器执行 composer/npm/pnpm；
 * 依赖安装改由运维在服务器命令行完成。
 */
export const TERMINAL_DISABLED_MESSAGE =
  '在线依赖终端已停用（安全策略）：请由运维在服务器命令行执行依赖安装与前端构建，浏览器不再执行服务器命令。'

const terminalMessage = (value: unknown): string | null => {
  if (!value || typeof value !== 'object' || !('data' in value)) return null
  const data = value.data
  return typeof data === 'string' ? data : null
}

export const useTerminalStore = defineStore(
  'sandpackageTerminal',
  () => {
    // 状态
    const show = ref(false)
    const taskList = ref<TerminalTask[]>([])
    const npmRegistry = ref('npm')
    const packageManager = ref('pnpm')
    const composerRegistry = ref('composer')

    /**
     * 设置任务状态
     */
    const setTaskStatus = (idx: number, status: TaskStatus) => {
      if (taskList.value[idx]) {
        taskList.value[idx].status = status
        taskList.value[idx].showMessage = true
      }
    }

    /**
     * 添加任务消息
     */
    const addTaskMessage = (idx: number, message: string) => {
      if (taskList.value[idx]) {
        taskList.value[idx].message = taskList.value[idx].message.concat(message)
      }
    }

    /**
     * 切换任务消息显示
     */
    const setTaskShowMessage = (idx: number, val?: boolean) => {
      if (taskList.value[idx]) {
        taskList.value[idx].showMessage = val ?? !taskList.value[idx].showMessage
      }
    }

    /**
     * 清空任务列表
     */
    const cleanTaskList = () => {
      taskList.value = []
    }

    /**
     * 任务完成回调
     */
    const taskCompleted = (idx: number) => {
      const task = taskList.value[idx]
      if (!task || typeof task.callback !== 'function') return

      const status = task.status
      if (status === TaskStatus.FAILED || status === TaskStatus.UNKNOWN) {
        task.callback(TaskStatus.FAILED)
      } else if (status === TaskStatus.SUCCESS) {
        task.callback(TaskStatus.SUCCESS)
      }
    }

    /**
     * 根据猜测查找任务索引
     */
    const findTaskIdxFromGuess = (idx: number): number | false => {
      if (!taskList.value[idx]) {
        let taskKey = -1
        for (let i = 0; i < taskList.value.length; i++) {
          if (
            taskList.value[i].status === TaskStatus.CONNECTING ||
            taskList.value[i].status === TaskStatus.RUNNING
          ) {
            taskKey = i
          }
        }
        return taskKey === -1 ? false : taskKey
      }
      return idx
    }

    /**
     * F5：不再发起任何终端请求，任务直接以“已停用”失败结束，交由调用方的失败处理引导到命令行。
     */
    const startEventSource = (taskKey: number) => {
      const taskIdx = findTaskIdxFromGuess(taskKey)
      if (taskIdx === false) return
      addTaskMessage(taskIdx, TERMINAL_DISABLED_MESSAGE)
      setTaskStatus(taskIdx, TaskStatus.FAILED)
      taskCompleted(taskIdx)
    }

    /**
     * 添加 Node 相关任务
     */
    const addNodeTask = (
      command: string,
      extend: string = '',
      callback?: (status: TaskStatus) => void
    ) => {
      const manager = packageManager.value === 'unknown' ? 'npm' : packageManager.value
      const fullCommand = `${command}.${manager}`
      addTask(fullCommand, extend, callback)
    }

    /**
     * 添加任务
     */
    const addTask = (
      command: string,
      extend: string = '',
      callback?: (status: TaskStatus) => void
    ) => {
      const task: TerminalTask = {
        uuid: generateUUID(),
        createTime: formatDateTime(),
        status: TaskStatus.WAITING,
        command,
        message: [],
        showMessage: false,
        extend,
        callback
      }
      taskList.value.push(task)

      // 检查是否有已经失败的任务
      if (show.value === false) {
        for (const t of taskList.value) {
          if (t.status === TaskStatus.FAILED || t.status === TaskStatus.UNKNOWN) {
            ElMessage.warning('任务列表中存在失败的任务')
            break
          }
        }
      }

      startTask()
    }

    /**
     * 开始执行任务
     */
    const startTask = () => {
      let taskKey: number | null = null

      // 寻找可以开始执行的命令
      for (let i = 0; i < taskList.value.length; i++) {
        const task = taskList.value[i]
        if (task.status === TaskStatus.WAITING) {
          taskKey = i
          break
        }
        if (task.status === TaskStatus.CONNECTING || task.status === TaskStatus.RUNNING) {
          break
        }
      }

      if (taskKey !== null) {
        setTaskStatus(taskKey, TaskStatus.CONNECTING)
        startEventSource(taskKey)
      }
    }

    /**
     * 重试任务
     */
    const retryTask = (idx: number) => {
      if (taskList.value[idx]) {
        taskList.value[idx].message = []
        setTaskStatus(idx, TaskStatus.WAITING)
        startTask()
      }
    }

    /**
     * 删除任务
     */
    const delTask = (idx: number) => {
      const task = taskList.value[idx]
      if (task && task.status !== TaskStatus.CONNECTING && task.status !== TaskStatus.RUNNING) {
        taskList.value.splice(idx, 1)
      }
    }

    return {
      show,
      taskList,
      npmRegistry,
      packageManager,
      composerRegistry,
      setTaskStatus,
      addTaskMessage,
      setTaskShowMessage,
      cleanTaskList,
      addNodeTask,
      addTask,
      retryTask,
      delTask,
      startTask
    }
  },
  {
    persist: {
      key: 'sandpackageTerminal',
      storage: localStorage,
      pick: ['npmRegistry', 'composerRegistry', 'packageManager']
    }
  }
)

export default useTerminalStore
