// <contract-generated> 本文件由 tools/contract-build 生成，请勿手改；
// 改了会在契约一致性门禁（tools/contract-build --check）被打回。
// 源: contract/openapi.yaml
// 源哈希: sha256:0e05c7423b5b7fd78d046ae957a741380629f10e683c91907c04c36be6a890ca
// 生成器: contract-build v1

/** 成功信封：code 固定字符串 OK，成功判定只看这一个字段 */
export interface ApiSuccess<T = unknown> {
  code: 'OK'
  message: string
  data: T
  request_id: string
}

/** 失败信封：code 为契约登记的错误码，fields 是字段级错误 */
export interface ApiFailure {
  code: ErrorCode
  message: string
  fields: Record<string, string[]>
  request_id: string
}

export type ApiEnvelope<T = unknown> = ApiSuccess<T> | ApiFailure

/** 契约登记的错误码（x-error-catalog 全集） */
export type ErrorCode =
  | 'ANNOUNCEMENT_NOT_FOUND'
  | 'CSRF_REJECTED'
  | 'DATA_SCOPE_INVALID'
  | 'DEPT_NAME_TAKEN'
  | 'DEPT_NOT_EMPTY'
  | 'DEPT_NOT_FOUND'
  | 'DEPT_REQUIRED'
  | 'DICT_ITEM_TAKEN'
  | 'DICT_TYPE_TAKEN'
  | 'FILE_NOT_FOUND'
  | 'FILE_TOO_LARGE'
  | 'FILE_TYPE_NOT_ALLOWED'
  | 'IDEMPOTENCY_KEY_REUSED'
  | 'INTERNAL_ERROR'
  | 'LAST_ADMIN'
  | 'LIFECYCLE_REQUEST_NOT_FOUND'
  | 'MCP_BAD_ARGUMENT'
  | 'MCP_FORBIDDEN'
  | 'MCP_GATE_MISSING'
  | 'MCP_HIGH_RISK_NOT_OPEN'
  | 'MCP_IDEMPOTENCY_REQUIRED'
  | 'MCP_ORIGIN_REJECTED'
  | 'MCP_RATE_LIMITED'
  | 'MCP_TARGET_REJECTED'
  | 'MCP_TOOL_FAILED'
  | 'MCP_UNAUTHORIZED'
  | 'MENU_DEPTH_EXCEEDED'
  | 'MENU_LOCKED'
  | 'MENU_NOT_FOUND'
  | 'MENU_URL_INVALID'
  | 'NOT_FOUND'
  | 'PERMISSION_DENIED'
  | 'PLUGIN_ALREADY_INSTALLED'
  | 'PLUGIN_BUSY'
  | 'PLUGIN_CONTRACT_CONFLICT'
  | 'PLUGIN_NOT_FOUND'
  | 'PLUGIN_NOT_INSTALLED'
  | 'PLUGIN_OPERATION_FORBIDDEN'
  | 'PLUGIN_PACKAGE_INVALID'
  | 'PLUGIN_VERSION_INCOMPATIBLE'
  | 'POST_NAME_TAKEN'
  | 'POST_NOT_FOUND'
  | 'ROLE_NAME_TAKEN'
  | 'SELF_LOCKOUT'
  | 'SESSION_EXPIRED'
  | 'STALE_VERSION'
  | 'STREAM_AUTHZ_REVOKED'
  | 'STREAM_DURATION_LIMIT'
  | 'STREAM_IDLE_TIMEOUT'
  | 'STREAM_LIMIT_REACHED'
  | 'SYSTEM_ROLE_READONLY'
  | 'SYSTEM_UPDATE_OPERATION_FORBIDDEN'
  | 'TASK_NOT_FOUND'
  | 'TASK_RUN_NOT_DOWNLOADABLE'
  | 'TASK_RUN_NOT_FOUND'
  | 'TASK_SCHEDULE_INVALID'
  | 'UNAUTHENTICATED'
  | 'UPDATE_NOT_AVAILABLE'
  | 'USERNAME_TAKEN'
  | 'USER_DISABLE_BLOCKED'
  | 'USER_NOT_FOUND'
  | 'VALIDATION_FAILED';

/** 成功码：与后端 ErrorCodeRegistry::OK 一致 */
export const SUCCESS_CODE = 'OK' as const

/** 契约声明的权限码全集 */
export type PermissionCode =
  | 'mcp:dev:errors'
  | 'mcp:dev:gate'
  | 'mcp:dev:phpunit'
  | 'mcp:dev:routes'
  | 'system:announcement:create'
  | 'system:announcement:delete'
  | 'system:announcement:index'
  | 'system:announcement:publish'
  | 'system:announcement:revoke'
  | 'system:announcement:update'
  | 'system:dept:create'
  | 'system:dept:delete'
  | 'system:dept:index'
  | 'system:dept:update'
  | 'system:dict:create'
  | 'system:dict:delete'
  | 'system:dict:index'
  | 'system:dict:update'
  | 'system:file:cleanup'
  | 'system:file:delete'
  | 'system:file:index'
  | 'system:menu:create'
  | 'system:menu:delete'
  | 'system:menu:index'
  | 'system:menu:reset'
  | 'system:menu:update'
  | 'system:plugin:index'
  | 'system:post:create'
  | 'system:post:delete'
  | 'system:post:index'
  | 'system:post:update'
  | 'system:role:create'
  | 'system:role:delete'
  | 'system:role:index'
  | 'system:role:permission:update'
  | 'system:role:update'
  | 'system:task:index'
  | 'system:update:index'
  | 'system:user:assign-role'
  | 'system:user:create'
  | 'system:user:delete'
  | 'system:user:export'
  | 'system:user:index'
  | 'system:user:reset-password'
  | 'system:user:update';

/** MCP 工具名全集 */
export type McpToolName =
  | 'dev_inspect_routes'
  | 'dev_recent_errors'
  | 'dev_run_phpunit'
  | 'dev_run_pre_merge';

export type PermissionKind =
  | 'single' | 'any_of' | 'all_of' | 'authenticated' | 'public' | 'super'

export interface ContractOperation {
  method: string
  path: string
  operationId: string
  permissionKind: PermissionKind
  permissionCodes: readonly PermissionCode[]
  errorCodes: readonly ErrorCode[]
  tags: readonly string[]
}

/** 契约 operation 全集（方法+路径即路由表事实） */
export const OPERATIONS: readonly ContractOperation[] = [
  {
    method: 'POST',
    path: '/api/admin/v1/files',
    operationId: 'filesStore',
    permissionKind: 'authenticated',
    permissionCodes: [],
    errorCodes: ['FILE_TOO_LARGE', 'FILE_TYPE_NOT_ALLOWED', 'IDEMPOTENCY_KEY_REUSED'],
    tags: ['files'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/files',
    operationId: 'filesIndex',
    permissionKind: 'single',
    permissionCodes: ['system:file:index'],
    errorCodes: [],
    tags: ['files'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/files/{file_id}',
    operationId: 'filesShow',
    permissionKind: 'single',
    permissionCodes: ['system:file:index'],
    errorCodes: ['FILE_NOT_FOUND'],
    tags: ['files'],
  },
  {
    method: 'DELETE',
    path: '/api/admin/v1/files/{file_id}',
    operationId: 'filesDestroy',
    permissionKind: 'single',
    permissionCodes: ['system:file:delete'],
    errorCodes: ['FILE_NOT_FOUND', 'IDEMPOTENCY_KEY_REUSED'],
    tags: ['files'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/files/{file_id}/download',
    operationId: 'filesDownload',
    permissionKind: 'authenticated',
    permissionCodes: [],
    errorCodes: ['FILE_NOT_FOUND'],
    tags: ['files'],
  },
  {
    method: 'POST',
    path: '/api/admin/v1/files/cleanup',
    operationId: 'filesCleanup',
    permissionKind: 'single',
    permissionCodes: ['system:file:cleanup'],
    errorCodes: ['IDEMPOTENCY_KEY_REUSED'],
    tags: ['files'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/tasks',
    operationId: 'tasksIndex',
    permissionKind: 'authenticated',
    permissionCodes: [],
    errorCodes: [],
    tags: ['tasks'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/tasks/{task_id}',
    operationId: 'tasksShow',
    permissionKind: 'authenticated',
    permissionCodes: [],
    errorCodes: ['TASK_RUN_NOT_FOUND'],
    tags: ['tasks'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/tasks/{task_id}/download',
    operationId: 'tasksDownload',
    permissionKind: 'authenticated',
    permissionCodes: [],
    errorCodes: ['TASK_RUN_NOT_FOUND', 'TASK_RUN_NOT_DOWNLOADABLE'],
    tags: ['tasks'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/me/announcements/popup',
    operationId: 'announcementsPopup',
    permissionKind: 'authenticated',
    permissionCodes: [],
    errorCodes: [],
    tags: ['announcements'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/announcements',
    operationId: 'announcementsIndex',
    permissionKind: 'single',
    permissionCodes: ['system:announcement:index'],
    errorCodes: [],
    tags: ['announcements'],
  },
  {
    method: 'POST',
    path: '/api/admin/v1/announcements',
    operationId: 'announcementsStore',
    permissionKind: 'single',
    permissionCodes: ['system:announcement:create'],
    errorCodes: ['IDEMPOTENCY_KEY_REUSED'],
    tags: ['announcements'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/announcements/{announcement_id}',
    operationId: 'announcementsShow',
    permissionKind: 'single',
    permissionCodes: ['system:announcement:index'],
    errorCodes: ['ANNOUNCEMENT_NOT_FOUND'],
    tags: ['announcements'],
  },
  {
    method: 'PUT',
    path: '/api/admin/v1/announcements/{announcement_id}',
    operationId: 'announcementsUpdate',
    permissionKind: 'single',
    permissionCodes: ['system:announcement:update'],
    errorCodes: ['ANNOUNCEMENT_NOT_FOUND', 'IDEMPOTENCY_KEY_REUSED'],
    tags: ['announcements'],
  },
  {
    method: 'DELETE',
    path: '/api/admin/v1/announcements/{announcement_id}',
    operationId: 'announcementsDestroy',
    permissionKind: 'single',
    permissionCodes: ['system:announcement:delete'],
    errorCodes: ['ANNOUNCEMENT_NOT_FOUND', 'IDEMPOTENCY_KEY_REUSED'],
    tags: ['announcements'],
  },
  {
    method: 'POST',
    path: '/api/admin/v1/announcements/{announcement_id}/publish',
    operationId: 'announcementsPublish',
    permissionKind: 'single',
    permissionCodes: ['system:announcement:publish'],
    errorCodes: ['ANNOUNCEMENT_NOT_FOUND', 'IDEMPOTENCY_KEY_REUSED'],
    tags: ['announcements'],
  },
  {
    method: 'POST',
    path: '/api/admin/v1/announcements/{announcement_id}/revoke',
    operationId: 'announcementsRevoke',
    permissionKind: 'single',
    permissionCodes: ['system:announcement:revoke'],
    errorCodes: ['ANNOUNCEMENT_NOT_FOUND', 'IDEMPOTENCY_KEY_REUSED'],
    tags: ['announcements'],
  },
  {
    method: 'POST',
    path: '/api/admin/v1/announcements/{announcement_id}/read',
    operationId: 'announcementsMarkRead',
    permissionKind: 'authenticated',
    permissionCodes: [],
    errorCodes: ['ANNOUNCEMENT_NOT_FOUND', 'IDEMPOTENCY_KEY_REUSED'],
    tags: ['announcements'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/announcements/{announcement_id}/read-stats',
    operationId: 'announcementsReadStats',
    permissionKind: 'single',
    permissionCodes: ['system:announcement:index'],
    errorCodes: ['ANNOUNCEMENT_NOT_FOUND'],
    tags: ['announcements'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/notifications/unread-count',
    operationId: 'notificationsUnreadCount',
    permissionKind: 'authenticated',
    permissionCodes: [],
    errorCodes: [],
    tags: ['notifications'],
  },
  {
    method: 'POST',
    path: '/api/admin/v1/notifications/read-all',
    operationId: 'notificationsMarkAllRead',
    permissionKind: 'authenticated',
    permissionCodes: [],
    errorCodes: [],
    tags: ['notifications'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/notifications',
    operationId: 'notificationsIndex',
    permissionKind: 'authenticated',
    permissionCodes: [],
    errorCodes: [],
    tags: ['notifications'],
  },
  {
    method: 'POST',
    path: '/api/admin/v1/notifications/{notification_id}/read',
    operationId: 'notificationsMarkRead',
    permissionKind: 'authenticated',
    permissionCodes: [],
    errorCodes: ['NOT_FOUND'],
    tags: ['notifications'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/lifecycle-requests/{request_id}',
    operationId: 'lifecycleShow',
    permissionKind: 'authenticated',
    permissionCodes: [],
    errorCodes: ['LIFECYCLE_REQUEST_NOT_FOUND'],
    tags: ['lifecycle'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/auth/password-policy',
    operationId: 'authPasswordPolicy',
    permissionKind: 'authenticated',
    permissionCodes: [],
    errorCodes: [],
    tags: ['me'],
  },
  {
    method: 'POST',
    path: '/api/admin/v1/auth/password/change',
    operationId: 'authChangePassword',
    permissionKind: 'authenticated',
    permissionCodes: [],
    errorCodes: [],
    tags: ['me'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/me',
    operationId: 'meShow',
    permissionKind: 'authenticated',
    permissionCodes: [],
    errorCodes: ['USER_NOT_FOUND'],
    tags: ['me'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/me/profile',
    operationId: 'meProfile',
    permissionKind: 'authenticated',
    permissionCodes: [],
    errorCodes: [],
    tags: ['me'],
  },
  {
    method: 'PUT',
    path: '/api/admin/v1/me/profile',
    operationId: 'meUpdateProfile',
    permissionKind: 'authenticated',
    permissionCodes: [],
    errorCodes: ['USER_NOT_FOUND'],
    tags: ['me'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/me/menus',
    operationId: 'meMenus',
    permissionKind: 'authenticated',
    permissionCodes: [],
    errorCodes: [],
    tags: ['me'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/menus/tree',
    operationId: 'menusTree',
    permissionKind: 'single',
    permissionCodes: ['system:menu:index'],
    errorCodes: [],
    tags: ['menus'],
  },
  {
    method: 'POST',
    path: '/api/admin/v1/menus/groups',
    operationId: 'menusStoreGroup',
    permissionKind: 'single',
    permissionCodes: ['system:menu:create'],
    errorCodes: ['MENU_NOT_FOUND', 'MENU_DEPTH_EXCEEDED', 'MENU_LOCKED'],
    tags: ['menus'],
  },
  {
    method: 'POST',
    path: '/api/admin/v1/menus/links',
    operationId: 'menusStoreLink',
    permissionKind: 'single',
    permissionCodes: ['system:menu:create'],
    errorCodes: ['MENU_NOT_FOUND', 'MENU_DEPTH_EXCEEDED', 'MENU_URL_INVALID', 'MENU_LOCKED'],
    tags: ['menus'],
  },
  {
    method: 'POST',
    path: '/api/admin/v1/menus/reset',
    operationId: 'menusReset',
    permissionKind: 'single',
    permissionCodes: ['system:menu:reset'],
    errorCodes: [],
    tags: ['menus'],
  },
  {
    method: 'PATCH',
    path: '/api/admin/v1/menus/{menu_key}',
    operationId: 'menusUpdate',
    permissionKind: 'single',
    permissionCodes: ['system:menu:update'],
    errorCodes: ['MENU_NOT_FOUND', 'MENU_LOCKED', 'MENU_URL_INVALID', 'STALE_VERSION'],
    tags: ['menus'],
  },
  {
    method: 'DELETE',
    path: '/api/admin/v1/menus/{menu_key}',
    operationId: 'menusDestroy',
    permissionKind: 'single',
    permissionCodes: ['system:menu:delete'],
    errorCodes: ['MENU_NOT_FOUND', 'MENU_LOCKED', 'STALE_VERSION'],
    tags: ['menus'],
  },
  {
    method: 'POST',
    path: '/api/admin/v1/menus/{menu_key}/move',
    operationId: 'menusMove',
    permissionKind: 'single',
    permissionCodes: ['system:menu:update'],
    errorCodes: ['MENU_NOT_FOUND', 'MENU_LOCKED', 'MENU_DEPTH_EXCEEDED', 'STALE_VERSION'],
    tags: ['menus'],
  },
  {
    method: 'PUT',
    path: '/api/admin/v1/menus/{menu_key}/visible',
    operationId: 'menusVisible',
    permissionKind: 'single',
    permissionCodes: ['system:menu:update'],
    errorCodes: ['MENU_NOT_FOUND', 'MENU_LOCKED', 'STALE_VERSION'],
    tags: ['menus'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/dashboard/onboarding',
    operationId: 'dashboardOnboarding',
    permissionKind: 'authenticated',
    permissionCodes: [],
    errorCodes: [],
    tags: ['dashboard'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/dashboard/summary',
    operationId: 'dashboardSummary',
    permissionKind: 'authenticated',
    permissionCodes: [],
    errorCodes: [],
    tags: ['dashboard'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/dashboard/todos/all',
    operationId: 'dashboardAllTodos',
    permissionKind: 'authenticated',
    permissionCodes: [],
    errorCodes: [],
    tags: ['dashboard'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/dashboard/todos',
    operationId: 'dashboardTodos',
    permissionKind: 'authenticated',
    permissionCodes: [],
    errorCodes: [],
    tags: ['dashboard'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/dashboard/charts',
    operationId: 'dashboardCharts',
    permissionKind: 'authenticated',
    permissionCodes: [],
    errorCodes: [],
    tags: ['dashboard'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/dashboard/activities',
    operationId: 'dashboardActivities',
    permissionKind: 'authenticated',
    permissionCodes: [],
    errorCodes: [],
    tags: ['dashboard'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/dashboard/notices',
    operationId: 'dashboardNotices',
    permissionKind: 'authenticated',
    permissionCodes: [],
    errorCodes: [],
    tags: ['dashboard'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/dashboard/shortcuts',
    operationId: 'dashboardShortcuts',
    permissionKind: 'authenticated',
    permissionCodes: [],
    errorCodes: [],
    tags: ['dashboard'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/users',
    operationId: 'usersIndex',
    permissionKind: 'single',
    permissionCodes: ['system:user:index'],
    errorCodes: [],
    tags: ['org'],
  },
  {
    method: 'POST',
    path: '/api/admin/v1/users',
    operationId: 'usersStore',
    permissionKind: 'single',
    permissionCodes: ['system:user:create'],
    errorCodes: ['USERNAME_TAKEN', 'IDEMPOTENCY_KEY_REUSED'],
    tags: ['org'],
  },
  {
    method: 'POST',
    path: '/api/admin/v1/users/export',
    operationId: 'usersExport',
    permissionKind: 'single',
    permissionCodes: ['system:user:export'],
    errorCodes: [],
    tags: ['org'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/users/{user_id}',
    operationId: 'usersShow',
    permissionKind: 'single',
    permissionCodes: ['system:user:index'],
    errorCodes: ['USER_NOT_FOUND', 'NOT_FOUND'],
    tags: ['org'],
  },
  {
    method: 'PUT',
    path: '/api/admin/v1/users/{user_id}',
    operationId: 'usersUpdate',
    permissionKind: 'single',
    permissionCodes: ['system:user:update'],
    errorCodes: ['USER_NOT_FOUND', 'USERNAME_TAKEN', 'STALE_VERSION'],
    tags: ['org'],
  },
  {
    method: 'DELETE',
    path: '/api/admin/v1/users/{user_id}',
    operationId: 'usersDestroy',
    permissionKind: 'single',
    permissionCodes: ['system:user:delete'],
    errorCodes: ['USER_NOT_FOUND', 'LAST_ADMIN', 'STALE_VERSION'],
    tags: ['org'],
  },
  {
    method: 'PUT',
    path: '/api/admin/v1/users/{user_id}/status',
    operationId: 'usersStatus',
    permissionKind: 'single',
    permissionCodes: ['system:user:update'],
    errorCodes: ['USER_NOT_FOUND', 'USER_DISABLE_BLOCKED', 'LAST_ADMIN', 'STALE_VERSION'],
    tags: ['org'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/users/{user_id}/disable-check',
    operationId: 'usersDisableCheck',
    permissionKind: 'any_of',
    permissionCodes: ['system:user:update', 'system:user:delete'],
    errorCodes: ['USER_NOT_FOUND'],
    tags: ['org'],
  },
  {
    method: 'POST',
    path: '/api/admin/v1/users/{user_id}/reset-password',
    operationId: 'usersResetPassword',
    permissionKind: 'single',
    permissionCodes: ['system:user:reset-password'],
    errorCodes: ['USER_NOT_FOUND', 'LAST_ADMIN'],
    tags: ['org'],
  },
  {
    method: 'PUT',
    path: '/api/admin/v1/users/{user_id}/roles',
    operationId: 'usersRoles',
    permissionKind: 'single',
    permissionCodes: ['system:user:assign-role'],
    errorCodes: ['USER_NOT_FOUND', 'NOT_FOUND'],
    tags: ['org'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/org/user-options',
    operationId: 'orgUserOptions',
    permissionKind: 'authenticated',
    permissionCodes: [],
    errorCodes: [],
    tags: ['org'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/org/dept-options',
    operationId: 'orgDeptOptions',
    permissionKind: 'authenticated',
    permissionCodes: [],
    errorCodes: [],
    tags: ['org'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/depts/tree',
    operationId: 'deptsTree',
    permissionKind: 'single',
    permissionCodes: ['system:dept:index'],
    errorCodes: [],
    tags: ['org'],
  },
  {
    method: 'POST',
    path: '/api/admin/v1/depts',
    operationId: 'deptsStore',
    permissionKind: 'single',
    permissionCodes: ['system:dept:create'],
    errorCodes: ['DEPT_NAME_TAKEN', 'DEPT_NOT_FOUND', 'IDEMPOTENCY_KEY_REUSED'],
    tags: ['org'],
  },
  {
    method: 'PUT',
    path: '/api/admin/v1/depts/{dept_id}',
    operationId: 'deptsUpdate',
    permissionKind: 'single',
    permissionCodes: ['system:dept:update'],
    errorCodes: ['DEPT_NOT_FOUND', 'DEPT_NAME_TAKEN', 'STALE_VERSION'],
    tags: ['org'],
  },
  {
    method: 'DELETE',
    path: '/api/admin/v1/depts/{dept_id}',
    operationId: 'deptsDestroy',
    permissionKind: 'single',
    permissionCodes: ['system:dept:delete'],
    errorCodes: ['DEPT_NOT_FOUND', 'DEPT_NOT_EMPTY', 'STALE_VERSION'],
    tags: ['org'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/depts/{dept_id}/delete-check',
    operationId: 'deptsDeleteCheck',
    permissionKind: 'single',
    permissionCodes: ['system:dept:delete'],
    errorCodes: ['DEPT_NOT_FOUND'],
    tags: ['org'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/roles',
    operationId: 'rolesIndex',
    permissionKind: 'single',
    permissionCodes: ['system:role:index'],
    errorCodes: [],
    tags: ['org'],
  },
  {
    method: 'POST',
    path: '/api/admin/v1/roles',
    operationId: 'rolesStore',
    permissionKind: 'single',
    permissionCodes: ['system:role:create'],
    errorCodes: ['ROLE_NAME_TAKEN', 'DEPT_REQUIRED', 'DATA_SCOPE_INVALID', 'IDEMPOTENCY_KEY_REUSED'],
    tags: ['org'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/roles/{role_id}',
    operationId: 'rolesShow',
    permissionKind: 'single',
    permissionCodes: ['system:role:index'],
    errorCodes: ['NOT_FOUND'],
    tags: ['org'],
  },
  {
    method: 'PUT',
    path: '/api/admin/v1/roles/{role_id}',
    operationId: 'rolesUpdate',
    permissionKind: 'single',
    permissionCodes: ['system:role:update'],
    errorCodes: ['NOT_FOUND', 'ROLE_NAME_TAKEN', 'SYSTEM_ROLE_READONLY', 'STALE_VERSION', 'DEPT_REQUIRED', 'DATA_SCOPE_INVALID'],
    tags: ['org'],
  },
  {
    method: 'DELETE',
    path: '/api/admin/v1/roles/{role_id}',
    operationId: 'rolesDestroy',
    permissionKind: 'single',
    permissionCodes: ['system:role:delete'],
    errorCodes: ['NOT_FOUND', 'SYSTEM_ROLE_READONLY', 'STALE_VERSION'],
    tags: ['org'],
  },
  {
    method: 'POST',
    path: '/api/admin/v1/roles/{role_id}/copy',
    operationId: 'rolesCopy',
    permissionKind: 'single',
    permissionCodes: ['system:role:create'],
    errorCodes: ['NOT_FOUND', 'ROLE_NAME_TAKEN', 'IDEMPOTENCY_KEY_REUSED'],
    tags: ['org'],
  },
  {
    method: 'PUT',
    path: '/api/admin/v1/roles/{role_id}/status',
    operationId: 'rolesStatus',
    permissionKind: 'single',
    permissionCodes: ['system:role:update'],
    errorCodes: ['NOT_FOUND', 'SYSTEM_ROLE_READONLY', 'STALE_VERSION'],
    tags: ['org'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/roles/{role_id}/permissions',
    operationId: 'rolesPermissions',
    permissionKind: 'single',
    permissionCodes: ['system:role:index'],
    errorCodes: ['NOT_FOUND'],
    tags: ['org'],
  },
  {
    method: 'PUT',
    path: '/api/admin/v1/roles/{role_id}/permissions',
    operationId: 'rolesSavePermissions',
    permissionKind: 'single',
    permissionCodes: ['system:role:permission:update'],
    errorCodes: ['NOT_FOUND', 'SYSTEM_ROLE_READONLY', 'SELF_LOCKOUT', 'STALE_VERSION'],
    tags: ['org'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/permission-tree',
    operationId: 'rolesPermissionTree',
    permissionKind: 'single',
    permissionCodes: ['system:role:index'],
    errorCodes: [],
    tags: ['org'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/posts',
    operationId: 'postsIndex',
    permissionKind: 'single',
    permissionCodes: ['system:post:index'],
    errorCodes: [],
    tags: ['org'],
  },
  {
    method: 'POST',
    path: '/api/admin/v1/posts',
    operationId: 'postsStore',
    permissionKind: 'single',
    permissionCodes: ['system:post:create'],
    errorCodes: ['POST_NAME_TAKEN', 'IDEMPOTENCY_KEY_REUSED'],
    tags: ['org'],
  },
  {
    method: 'PUT',
    path: '/api/admin/v1/posts/{post_id}',
    operationId: 'postsUpdate',
    permissionKind: 'single',
    permissionCodes: ['system:post:update'],
    errorCodes: ['POST_NOT_FOUND', 'POST_NAME_TAKEN', 'STALE_VERSION'],
    tags: ['org'],
  },
  {
    method: 'DELETE',
    path: '/api/admin/v1/posts/{post_id}',
    operationId: 'postsDestroy',
    permissionKind: 'single',
    permissionCodes: ['system:post:delete'],
    errorCodes: ['POST_NOT_FOUND', 'STALE_VERSION'],
    tags: ['org'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/dicts',
    operationId: 'dictsIndex',
    permissionKind: 'single',
    permissionCodes: ['system:dict:index'],
    errorCodes: [],
    tags: ['dicts'],
  },
  {
    method: 'POST',
    path: '/api/admin/v1/dicts',
    operationId: 'dictsStore',
    permissionKind: 'single',
    permissionCodes: ['system:dict:create'],
    errorCodes: ['DICT_TYPE_TAKEN', 'IDEMPOTENCY_KEY_REUSED'],
    tags: ['dicts'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/dicts/{type_id}',
    operationId: 'dictsShow',
    permissionKind: 'single',
    permissionCodes: ['system:dict:index'],
    errorCodes: ['NOT_FOUND'],
    tags: ['dicts'],
  },
  {
    method: 'PUT',
    path: '/api/admin/v1/dicts/{type_id}',
    operationId: 'dictsUpdate',
    permissionKind: 'single',
    permissionCodes: ['system:dict:update'],
    errorCodes: ['NOT_FOUND', 'DICT_TYPE_TAKEN'],
    tags: ['dicts'],
  },
  {
    method: 'DELETE',
    path: '/api/admin/v1/dicts/{type_id}',
    operationId: 'dictsDestroy',
    permissionKind: 'single',
    permissionCodes: ['system:dict:delete'],
    errorCodes: ['NOT_FOUND'],
    tags: ['dicts'],
  },
  {
    method: 'POST',
    path: '/api/admin/v1/dicts/{type_id}/items',
    operationId: 'dictsStoreItem',
    permissionKind: 'single',
    permissionCodes: ['system:dict:create'],
    errorCodes: ['NOT_FOUND', 'DICT_ITEM_TAKEN', 'IDEMPOTENCY_KEY_REUSED'],
    tags: ['dicts'],
  },
  {
    method: 'PUT',
    path: '/api/admin/v1/dicts/items/sort',
    operationId: 'dictsSortItems',
    permissionKind: 'single',
    permissionCodes: ['system:dict:update'],
    errorCodes: ['NOT_FOUND'],
    tags: ['dicts'],
  },
  {
    method: 'PUT',
    path: '/api/admin/v1/dicts/items/{item_id}',
    operationId: 'dictsUpdateItem',
    permissionKind: 'single',
    permissionCodes: ['system:dict:update'],
    errorCodes: ['NOT_FOUND', 'DICT_ITEM_TAKEN'],
    tags: ['dicts'],
  },
  {
    method: 'DELETE',
    path: '/api/admin/v1/dicts/items/{item_id}',
    operationId: 'dictsDestroyItem',
    permissionKind: 'single',
    permissionCodes: ['system:dict:delete'],
    errorCodes: ['NOT_FOUND'],
    tags: ['dicts'],
  },
  {
    method: 'PUT',
    path: '/api/admin/v1/dicts/items/{item_id}/status',
    operationId: 'dictsItemStatus',
    permissionKind: 'single',
    permissionCodes: ['system:dict:update'],
    errorCodes: ['NOT_FOUND'],
    tags: ['dicts'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/plugins/overview',
    operationId: 'pluginsOverview',
    permissionKind: 'authenticated',
    permissionCodes: [],
    errorCodes: [],
    tags: ['plugins'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/plugins/market',
    operationId: 'pluginsMarket',
    permissionKind: 'authenticated',
    permissionCodes: [],
    errorCodes: [],
    tags: ['plugins'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/plugins/installed',
    operationId: 'pluginsInstalled',
    permissionKind: 'authenticated',
    permissionCodes: [],
    errorCodes: [],
    tags: ['plugins'],
  },
  {
    method: 'POST',
    path: '/api/admin/v1/plugins/upgrade-all',
    operationId: 'pluginsUpgradeAll',
    permissionKind: 'super',
    permissionCodes: [],
    errorCodes: ['PLUGIN_BUSY', 'UPDATE_NOT_AVAILABLE', 'PLUGIN_OPERATION_FORBIDDEN'],
    tags: ['plugins'],
  },
  {
    method: 'POST',
    path: '/api/admin/v1/plugins/upload',
    operationId: 'pluginsUpload',
    permissionKind: 'super',
    permissionCodes: [],
    errorCodes: ['PLUGIN_PACKAGE_INVALID', 'PLUGIN_BUSY', 'PLUGIN_OPERATION_FORBIDDEN', 'PLUGIN_ALREADY_INSTALLED'],
    tags: ['plugins'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/plugins/{plugin_code}',
    operationId: 'pluginsShow',
    permissionKind: 'authenticated',
    permissionCodes: [],
    errorCodes: ['PLUGIN_NOT_FOUND'],
    tags: ['plugins'],
  },
  {
    method: 'POST',
    path: '/api/admin/v1/plugins/{plugin_code}/install',
    operationId: 'pluginsInstall',
    permissionKind: 'super',
    permissionCodes: [],
    errorCodes: ['PLUGIN_NOT_FOUND', 'PLUGIN_ALREADY_INSTALLED', 'PLUGIN_VERSION_INCOMPATIBLE', 'PLUGIN_BUSY', 'PLUGIN_OPERATION_FORBIDDEN'],
    tags: ['plugins'],
  },
  {
    method: 'POST',
    path: '/api/admin/v1/plugins/{plugin_code}/upgrade',
    operationId: 'pluginsUpgrade',
    permissionKind: 'super',
    permissionCodes: [],
    errorCodes: ['PLUGIN_NOT_FOUND', 'PLUGIN_NOT_INSTALLED', 'PLUGIN_VERSION_INCOMPATIBLE', 'PLUGIN_BUSY', 'PLUGIN_OPERATION_FORBIDDEN'],
    tags: ['plugins'],
  },
  {
    method: 'POST',
    path: '/api/admin/v1/plugins/{plugin_code}/uninstall',
    operationId: 'pluginsUninstall',
    permissionKind: 'super',
    permissionCodes: [],
    errorCodes: ['PLUGIN_NOT_FOUND', 'PLUGIN_NOT_INSTALLED', 'PLUGIN_BUSY', 'PLUGIN_OPERATION_FORBIDDEN'],
    tags: ['plugins'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/system/update/status',
    operationId: 'systemUpdateStatus',
    permissionKind: 'authenticated',
    permissionCodes: [],
    errorCodes: [],
    tags: ['system'],
  },
  {
    method: 'POST',
    path: '/api/admin/v1/system/update/check',
    operationId: 'systemUpdateCheck',
    permissionKind: 'authenticated',
    permissionCodes: [],
    errorCodes: ['UPDATE_NOT_AVAILABLE'],
    tags: ['system'],
  },
  {
    method: 'GET',
    path: '/api/admin/v1/system/update/probe',
    operationId: 'systemUpdateProbe',
    permissionKind: 'public',
    permissionCodes: [],
    errorCodes: [],
    tags: ['system'],
  },
  {
    method: 'POST',
    path: '/api/admin/v1/system/update/start',
    operationId: 'systemUpdateStart',
    permissionKind: 'super',
    permissionCodes: [],
    errorCodes: ['SYSTEM_UPDATE_OPERATION_FORBIDDEN', 'PLUGIN_BUSY', 'UPDATE_NOT_AVAILABLE'],
    tags: ['system'],
  },
  {
    method: 'PUT',
    path: '/api/admin/v1/scheduled-tasks/{code}',
    operationId: 'scheduledTasksUpdate',
    permissionKind: 'authenticated',
    permissionCodes: [],
    errorCodes: ['TASK_NOT_FOUND', 'TASK_SCHEDULE_INVALID'],
    tags: ['system'],
  },
]

/** method + path 的联合查询键 */
export type RouteKey = `${string} ${string}`

export function findOperation(method: string, path: string): ContractOperation | undefined {
  const upper = method.toUpperCase()
  return OPERATIONS.find((op) => op.method === upper && op.path === path)
}
