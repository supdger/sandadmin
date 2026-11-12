/** `/api/admin/v1` 各端点共用的契约类型（snake_case 原样保留）。 */

export type Id = string;

export interface Page<T> {
  items: T[];
  total: number;
  page: number;
  page_size: number;
}

export type UserStatus = 'enabled' | 'disabled';

/** 角色「能看的数据」范围（契约字段 data_scope 的取值）。 */
export type PermScope = 'all' | 'dept_and_child' | 'dept' | 'self' | 'custom';

export type TaskState = 'queued' | 'running' | 'succeeded' | 'failed' | 'cancelled';

/** 受理回执：task_run 类后台任务（导出、清理等）。 */
export interface TaskAccepted {
  task_id: string;
  state: TaskState;
}

/** 契约里 data 为 `{type: object}` 的写操作统一返回空对象。 */
export type EmptyResult = Record<string, never>;
