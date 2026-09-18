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
  lifecycle_driver?: string
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

export interface RepositoryPluginVersion {
  version: string
  tag: string
  asset: string
  sha256: string
  host_min: string
  host_max?: string
  notes: string
}

export interface RepositoryPlugin {
  app: string
  title: string
  about: string
  author: string
  versions: RepositoryPluginVersion[]
}

export interface RepositoryCatalog {
  repository: string
  ref: string
  plugins: RepositoryPlugin[]
}

export interface RepositoryDownloadRequest {
  app: string
  version: string
  sha256: string
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

  /** 获取服务端固定仓库的插件清单。 */
  getRepositoryCatalog() {
    return request.get<RepositoryCatalog>({
      url: '/tool/install/repository/catalog',
      timeout: 65000
    })
  },

  /** 下载、校验并准备插件候选；不会执行安装或升级。 */
  downloadRepositoryPlugin(data: RepositoryDownloadRequest) {
    return request.post<AppInfo>({
      url: '/tool/install/repository/download',
      data,
      timeout: 135000
    })
  }
}
