/**
 * 路由路径常量集中登记。新增页面在这里加常量并在
 * routes/modules/* 引用；跳转一律用常量，不散写字面量。
 */

export const PATH_HOME = '/';
export const PATH_LOGIN = '/login';
export const PATH_FORCE_CHANGE_PASSWORD = '/force-change-password';
export const PATH_PREFERENCES = '/preferences';
export const PATH_PROFILE = '/profile';
export const PATH_NOTIFICATIONS = '/notifications';
export const PATH_TODOS = '/todos';

export const PATH_RESULT_SUCCESS = '/result/success';
export const PATH_RESULT_FAIL = '/result/fail';
export const PATH_FORBIDDEN = '/403';
export const PATH_NOT_FOUND = '/404';
export const PATH_SERVER_ERROR = '/500';

export const PATH_SYSTEM_USER = '/system/user';
export const PATH_SYSTEM_DEPT = '/system/dept';
export const PATH_SYSTEM_POST = '/system/post';
export const PATH_SYSTEM_ROLE = '/system/role';
export const PATH_SYSTEM_MENU = '/system/menu';
export const PATH_SYSTEM_PLUGINS = '/system/plugins';
export const PATH_SYSTEM_UPDATE = '/system/update';
export const PATH_SYSTEM_TASKS = '/system/tasks';

export const PATH_DATA_DICT = '/data/dict';
export const PATH_DATA_FILE = '/data/file';
export const PATH_DATA_ANNOUNCEMENT = '/data/notice';

export const PATH_INSTALL = '/install';

/** 登录页地址；`?redirect=` 带回来地址，登录成功后回到原页面。 */
export function loginUrl(redirect: string): string {
  return `${PATH_LOGIN}?redirect=${encodeURIComponent(redirect)}`;
}
