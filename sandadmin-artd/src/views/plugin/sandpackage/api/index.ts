/**
 * 插件管理 API
 *
 * 提供插件安装、卸载、上传等功能接口
 *
 * @module api/tool/sandpackage
 */
import request from '@/utils/http'

export interface AppInfo {
  app: string
  title: string
  about: string
  author: string
  version: string
  support?: string
  website?: string
  state: number
  npm_dependent_wait_install?: number
  composer_dependent_wait_install?: number
  registration_candidate?: number
  update?: number
  stage?: string
  package_backup_id?: string
  registration_manifest?: string
  runtime_manifest?: string
  upgrade_from_version?: string
  state_text?: string
  stage_label?: string
  last_error?: string
  backup_id?: string
  legacy_recoverable?: boolean
  derived_upgrade_from_version?: string
  legacy_recovery_reason?: string
  upgrade_candidate_verified?: boolean
}

export interface VersionInfo {
  sandadmin_version?: {
    describe: string
    notes: string
    state: string
  }
  sandpackage_version?: {
    describe: string
    notes: string
    state: string
  }
}

export interface AppListResponse {
  data: AppInfo[]
  version: VersionInfo
}

export interface StoreApp {
  id: number
  title: string
  about: string
  logo: string
  version: string
  price: string
  avatar?: string
  username: string
  sales_num: number
  content?: string
  screenshots?: string[]
}

export interface StoreUser {
  nickname?: string
  username: string
  avatar?: string
}

export interface PurchasedApp {
  id: number
  app_id: number
  appname: string
  title: string
  logo: string
  version: string
  developer: string
  about: string
}

export interface AppVersion {
  id: number
  version: string
  create_time: string
  remark: string
}

/** 失败升级恢复接口的外部响应都在页面层按 unknown 收窄。 */
export interface FailedUpgradeRecoveryRequest {
  appName: string
}

export default {
  /**
   * 获取已安装的插件列表
   */
  getAppList() {
    return request.get<AppListResponse>({ url: '/app/sandpackage/install/index' })
  },

  /**
   * 上传插件包
   */
  uploadApp(data: FormData) {
    return request.post<AppInfo>({ url: '/app/sandpackage/install/upload', data })
  },

  /**
   * 安装插件
   */
  installApp(data: { appName: string; confirmation?: string }) {
    return request.post<AppInfo>({ url: '/app/sandpackage/install/install', data })
  },

  /**
   * 登记与上传包完全一致的已部署插件，不复制文件也不执行 SQL。
   */
  registerExisting(data: { appName: string; confirmation: string }) {
    return request.post<AppInfo>({ url: '/app/sandpackage/install/registerExisting', data })
  },

  /**
   * 撤回尚未执行的升级候选；后端会再次校验备份与运行时清单。
   */
  discardCandidate(data: { appName: string; confirmation: string }) {
    return request.post<AppInfo>({ url: '/app/sandpackage/install/discardCandidate', data })
  },

  /** 只读诊断失败升级是否存在运行文件漂移。 */
  inspectFailedUpgradeRecovery(data: FailedUpgradeRecoveryRequest) {
    return request.post<unknown>({
      url: '/app/sandpackage/install/inspectFailedUpgradeRecovery',
      data
    })
  },

  /** 封存并预检替换 ZIP；不替换候选、不执行升级。 */
  prepareFailedUpgradeReplacement(data: FormData) {
    return request.post<unknown>({
      url: '/app/sandpackage/install/prepareFailedUpgradeReplacement',
      data
    })
  },

  /** Gate A：只读核验已封存的替换候选。 */
  verifyFailedUpgradeRecovery(data: { appName: string; replacementId: string }) {
    return request.post<unknown>({
      url: '/app/sandpackage/install/verifyFailedUpgradeRecovery',
      data
    })
  },

  replaceFailedUpgradeCandidate(data: {
    appName: string
    replacementId: string
    confirmation: string
  }) {
    return request.post<unknown>({
      url: '/app/sandpackage/install/replaceFailedUpgradeCandidate',
      data
    })
  },

  retryFailedUpgrade(data: { appName: string; confirmation: string }) {
    return request.post<unknown>({
      url: '/app/sandpackage/install/retryFailedUpgrade',
      data
    })
  },

  restoreRuntimeFromBackup(data: { appName: string; confirmation: string }) {
    return request.post<unknown>({
      url: '/app/sandpackage/install/restoreRuntimeFromBackup',
      data
    })
  },

  /**
   * 卸载插件
   */
  uninstallApp(data: { appName: string }) {
    return request.post<unknown>({ url: '/app/sandpackage/install/uninstall', data })
  },

  /**
   * 重载后端
   */
  reloadBackend() {
    return request.post<unknown>({ url: '/app/sandpackage/install/reload' })
  },

  /**
   * 获取在线商店应用列表
   */
  getOnlineAppList(params: {
    page?: number
    limit?: number
    price?: string
    type?: string | number
    keywords?: string
  }) {
    return request.get<{ data: StoreApp[]; total: number }>({
      url: '/tool/install/online/appList',
      params
    })
  },

  /**
   * 获取验证码
   */
  getStoreCaptcha() {
    return request.get<{ image: string; uuid: string }>({
      url: '/tool/install/online/storeCaptcha'
    })
  },

  /**
   * 商店登录
   */
  storeLogin(data: { username: string; password: string; code: string; uuid: string }) {
    return request.post<{ access_token: string }>({
      url: '/tool/install/online/storeLogin',
      data
    })
  },

  /**
   * 获取商店用户信息
   */
  getStoreUserInfo(token: string) {
    return request.get<StoreUser>({
      url: '/tool/install/online/storeUserInfo',
      params: { token }
    })
  },

  /**
   * 获取已购应用列表
   */
  getPurchasedApps(token: string) {
    return request.get<PurchasedApp[]>({
      url: '/tool/install/online/storePurchasedApps',
      params: { token }
    })
  },

  /**
   * 获取应用版本列表
   */
  getAppVersions(token: string, app_id: number) {
    return request.get<AppVersion[]>({
      url: '/tool/install/online/storeAppVersions',
      params: { token, app_id }
    })
  },

  /**
   * 下载应用
   */
  downloadApp(data: { token: string; id: number }) {
    return request.post<unknown>({
      url: '/tool/install/online/storeDownloadApp',
      data
    })
  }
}
