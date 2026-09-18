/**
 * 模块边界 HTTP 替身：只替换 `@/utils/http`，供挂载真实 index.vue 的行为/视口使用。
 * 列表与恢复写接口在内存中切换，不访问网络。
 */
import type { SandpackageInstallRow } from './failed-upgrade-recovery'
import {
  FAILED_UPGRADE_BLOCKED_MESSAGE,
  FAILED_UPGRADE_FAILURE_MESSAGE,
  FAILED_UPGRADE_REPLACED_MESSAGE,
  FAILED_UPGRADE_RETRIED_MESSAGE,
  FAILED_UPGRADE_VERIFIED_MESSAGE
} from './failed-upgrade-recovery'

export type RecoveryListFixture =
  | 'verification_required'
  | 'verification_required_missing_actions'
  | 'verification_required_wrong_actions'
  | 'runtime_restore_required'
  | 'retry_ready'
  | 'mismatched_retry_ready'
  | 'partial_modern_retry_safe'
  | 'invalid_digest_retry_safe'
  | 'ordinary_blocked'
  | 'blocked'
  | 'missing'
  | 'error'

interface HttpRequestConfig {
  url?: string
  data?: unknown
}

function exactFailedRow(): SandpackageInstallRow {
  return {
    app: 'sample-plugin',
    title: 'Sample Plugin',
    about: 'identity service',
    author: 'sand',
    version: '0.7.0',
    state: 8,
    stage: 'failed',
    failed_stage: 'database_update',
    update: 1,
    upgrade_from_version: '0.6.0',
    ordinary_actions_blocked: true
  }
}

function exactReplacementMarkers(): Pick<
  SandpackageInstallRow,
  | 'candidate_archive_sha256'
  | 'candidate_payload_manifest_sha256'
  | 'recovery_descriptor_sha256'
  | 'update_sql_sha256'
  | 'failed_upgrade_replacement_id'
  | 'replacement_candidate_state'
> {
  return {
    candidate_archive_sha256: 'a'.repeat(64),
    candidate_payload_manifest_sha256: 'b'.repeat(64),
    recovery_descriptor_sha256: 'c'.repeat(64),
    update_sql_sha256: 'd'.repeat(64),
    failed_upgrade_replacement_id: 'e'.repeat(32),
    replacement_candidate_state: 'ready'
  }
}

function rowForFixture(
  fixture: Exclude<RecoveryListFixture, 'error' | 'missing'>
): SandpackageInstallRow {
  const row = exactFailedRow()
  if (fixture === 'ordinary_blocked') {
    row.state = 7
    row.state_text = '需要检查旧安装状态'
    row.stage = 'runtime_missing'
    row.failed_stage = undefined
    row.update = 0
    row.recovery_mode = 'none'
    row.recovery_reason = '安装记录存在，但插件运行目录缺失，请从已安装插件管理页处理'
    return row
  }
  if (fixture === 'blocked') {
    row.recovery_mode = 'blocked'
    row.recovery_reason = FAILED_UPGRADE_BLOCKED_MESSAGE
    return row
  }
  if (fixture === 'runtime_restore_required') {
    row.recovery_mode = 'runtime_restore_required'
    row.allowed_actions = ['restore_runtime_from_backup']
    return row
  }
  if (fixture === 'retry_ready') {
    Object.assign(row, exactReplacementMarkers())
    row.recovery_mode = 'retry_safe'
    row.allowed_actions = ['retry_after_replacement']
    row.recovery_reason = FAILED_UPGRADE_REPLACED_MESSAGE
    return row
  }
  if (fixture === 'mismatched_retry_ready') {
    Object.assign(row, exactReplacementMarkers())
    row.version = '0.7.1'
    row.recovery_mode = 'retry_safe'
    row.allowed_actions = ['retry_after_replacement']
    row.recovery_reason = FAILED_UPGRADE_REPLACED_MESSAGE
    return row
  }
  if (fixture === 'partial_modern_retry_safe') {
    row.failed_upgrade_replacement_id = 'e'.repeat(32)
    row.replacement_candidate_state = 'ready'
    row.recovery_mode = 'retry_safe'
    row.allowed_actions = ['retry_after_replacement']
    return row
  }
  if (fixture === 'invalid_digest_retry_safe') {
    Object.assign(row, exactReplacementMarkers())
    row.candidate_archive_sha256 = 'A'.repeat(64)
    row.recovery_mode = 'retry_safe'
    row.allowed_actions = ['retry_after_replacement']
    return row
  }
  row.recovery_mode = 'verification_required'
  if (fixture === 'verification_required_missing_actions') {
    return row
  }
  row.allowed_actions = [
    fixture === 'verification_required_wrong_actions'
      ? 'prepare_replacement'
      : 'prepare_failed_upgrade_replacement'
  ]
  return row
}

const versionInfo = {
  sandadmin_version: { describe: '6.1.0', notes: '正常', state: 'ok' },
  sandpackage_version: { describe: '0.7.0', notes: '正常', state: 'ok' }
}

let listFixture: RecoveryListFixture = 'verification_required'
type RuntimeRestoreRefreshFixture =
  | 'verification_required'
  | 'verification_required_missing_actions'
  | 'verification_required_wrong_actions'

let runtimeRestoreRefreshFixture: RuntimeRestoreRefreshFixture = 'verification_required'
let lastRecoveryPostUrl = ''

function readPostedAppName(data: unknown): string {
  if (data instanceof FormData) {
    const value = data.get('appName')
    return typeof value === 'string' ? value : ''
  }
  if (typeof data === 'object' && data !== null && !Array.isArray(data) && 'appName' in data) {
    const appName = data.appName
    return typeof appName === 'string' ? appName : ''
  }
  return ''
}

function readPostedReplacementId(data: unknown): string {
  if (!data || typeof data !== 'object' || Array.isArray(data)) return ''
  if (!('replacementId' in data) && !('replacement_id' in data)) return ''
  const value = 'replacementId' in data ? data.replacementId : data.replacement_id
  return typeof value === 'string' ? value : ''
}

async function get(config: HttpRequestConfig): Promise<unknown> {
  const url = config.url ?? ''
  if (url.includes('/app/sandpackage/install/index')) {
    if (listFixture === 'error') {
      throw new Error('FAILED_UPGRADE_RECOVERY_LIST_ERROR')
    }
    if (listFixture === 'missing') {
      return { data: [], version: versionInfo }
    }
    return {
      data: [rowForFixture(listFixture)],
      version: versionInfo
    }
  }
  throw new Error(`unmocked GET ${url}`)
}

async function post(config: HttpRequestConfig): Promise<unknown> {
  const url = config.url ?? ''
  lastRecoveryPostUrl = url
  const appName = readPostedAppName(config.data)
  const replacementId = readPostedReplacementId(config.data)
  if (url.includes('inspectFailedUpgradeRecovery')) {
    const runtimeRestoreRequired = listFixture === 'runtime_restore_required'
    return {
      app: appName,
      from_version: '0.6.0',
      to_version: '0.7.0',
      recovery_mode: runtimeRestoreRequired ? 'runtime_restore_required' : 'verification_required',
      allowed_actions: runtimeRestoreRequired
        ? ['restore_runtime_from_backup']
        : ['prepare_failed_upgrade_replacement'],
      ...(runtimeRestoreRequired ? { runtime_drift: true } : {}),
      message: FAILED_UPGRADE_FAILURE_MESSAGE
    }
  }
  if (url.includes('verifyFailedUpgradeRecovery')) {
    return {
      app: appName,
      from_version: '0.6.0',
      to_version: '0.7.0',
      verdict: 'retry_safe',
      replacement_id: replacementId,
      profile_hash: 'f'.repeat(64),
      evidence_fingerprint: 'e'.repeat(64),
      assertions_total: 101,
      assertions_passed: 101,
      failed_assertion_ids: [],
      audit_written: false,
      allowed_actions: ['replace_failed_upgrade_candidate'],
      message: FAILED_UPGRADE_VERIFIED_MESSAGE
    }
  }
  if (url.includes('prepareFailedUpgradeReplacement')) {
    return {
      replacement_id: 'rep-1',
      app: appName,
      from_version: '0.6.0',
      to_version: '0.7.0',
      profile_hash: 'f'.repeat(64),
      message: '插件包检查通过，确认后才会恢复插件文件。'
    }
  }
  if (url.includes('replaceFailedUpgradeCandidate')) {
    listFixture = 'retry_ready'
    return { message: FAILED_UPGRADE_REPLACED_MESSAGE }
  }
  if (url.includes('retryFailedUpgrade')) {
    listFixture = 'missing'
    return { message: FAILED_UPGRADE_RETRIED_MESSAGE }
  }
  if (url.includes('restoreRuntimeFromBackup')) {
    listFixture = runtimeRestoreRefreshFixture
    // This is the exact Controller envelope shape, intentionally without
    // client-only success/status aliases or fabricated production fields.
    return {
      code: 0,
      message: '运行文件恢复完成',
      data: {
        result: {
          restore_id: 'f'.repeat(32),
          status: 'restored',
          runtime_manifest_hash: 'a'.repeat(64)
        },
        presentation: {
          recovery_mode: 'verification_required',
          allowed_actions: ['prepare_failed_upgrade_replacement']
        }
      }
    }
  }
  throw new Error(`unmocked POST ${url}`)
}

const api = {
  get,
  post,
  put: get,
  del: get,
  request: get
}

export function configureRecoveryHttpMock(fixture: RecoveryListFixture): void {
  listFixture = fixture
  runtimeRestoreRefreshFixture = 'verification_required'
}

/** Restore is followed by a fresh index response; keep its shape explicit in each behavior case. */
export function configureRuntimeRestoreRefreshFixture(fixture: RuntimeRestoreRefreshFixture): void {
  runtimeRestoreRefreshFixture = fixture
}

export function currentRecoveryListFixture(): RecoveryListFixture {
  return listFixture
}

export function currentRecoveryPostUrl(): string {
  return lastRecoveryPostUrl
}

export default api
