/**
 * 挂载真实 index.vue 的行为检查：replace → refresh → retry，以及刷新失败后的可观察回退。
 */
import { nextTick } from 'vue'
import {
  FAILED_UPGRADE_VERIFY_INCOMPLETE_MESSAGE,
  FAILED_UPGRADE_VERIFY_MISMATCH_MESSAGE,
  FAILED_UPGRADE_VERIFIED_MESSAGE,
  FAILED_UPGRADE_RUNTIME_RESTORE_UNCONFIRMED_MESSAGE,
  FAILED_UPGRADE_ZIP_MAX_BYTES,
  assertFailedUpgradeZip,
  createFailedUpgradeSession,
  exactReplaceConfirmation,
  exactRetryConfirmation,
  humanizeRecoveryMessage,
  isDurableRetryReadyRow,
  parseRuntimeRestoreResult,
  parseInspectionResult,
  parsePrepareResult,
  restoreRuntimeFromBackup,
  parseVerifyResult,
  reconcileFailedUpgradeSession,
  replaceConfirmationText,
  type FailedUpgradeRecoveryPhase,
  type FailedUpgradeRecoverySession,
  type SandpackageInstallRow
} from './failed-upgrade-recovery'
import {
  configureRecoveryHttpMock,
  configureRuntimeRestoreRefreshFixture,
  currentRecoveryPostUrl
} from './failed-upgrade-recovery.http-mock'
import { mountSandpackageInstallPage } from './failed-upgrade-recovery.index-mount'

function textOf(el: Element | null): string {
  return el?.textContent?.replace(/\s+/g, ' ').trim() ?? ''
}

function buttonsOf(root: ParentNode): HTMLButtonElement[] {
  return Array.from(root.querySelectorAll('button'))
}

function buttonByText(root: ParentNode, label: string): HTMLButtonElement | null {
  for (const button of buttonsOf(root)) {
    if (textOf(button).includes(label)) return button
  }
  return null
}

function sessionPhase(session: FailedUpgradeRecoverySession): FailedUpgradeRecoveryPhase {
  return session.phase
}

function expectTrue(condition: boolean, label: string): void {
  if (!condition) throw new Error(label)
}

/** Vue/Element Plus 需要原生 value setter，仅改 DOM 属性不会更新 v-model。 */
function setNativeInputValue(input: HTMLInputElement, value: string): void {
  const descriptor = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')
  descriptor?.set?.call(input, value)
  input.dispatchEvent(new Event('input', { bubbles: true, composed: true }))
  input.dispatchEvent(new Event('change', { bubbles: true }))
}

async function assignZipFile(root: ParentNode, zip: File): Promise<void> {
  await waitFor(() => root.querySelector('input[type="file"]') !== null, 'zip input rendered')
  const fileInput = root.querySelector('input[type="file"]')
  if (!(fileInput instanceof HTMLInputElement)) throw new Error('zip input missing')
  const transfer = new DataTransfer()
  transfer.items.add(zip)
  fileInput.files = transfer.files
  fileInput.dispatchEvent(new Event('change', { bubbles: true }))
  fileInput.dispatchEvent(new Event('input', { bubbles: true }))
}

function exactFailedRow(overrides: Partial<SandpackageInstallRow> = {}): SandpackageInstallRow {
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
    recovery_mode: 'verification_required',
    ...overrides
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

async function waitFor(predicate: () => boolean, label: string, tries = 80): Promise<void> {
  for (let index = 0; index < tries; index += 1) {
    await nextTick()
    if (predicate()) return
    await new Promise((resolve) => {
      window.setTimeout(resolve, 50)
    })
  }
  throw new Error(`timeout: ${label}`)
}

async function confirmPrompt(root: ParentNode, value: string): Promise<void> {
  await waitFor(() => root.querySelector('.el-message-box') !== null, 'message box')
  const messageBoxes = Array.from(root.querySelectorAll('.el-message-box'))
  const messageBox = messageBoxes.at(-1)
  if (!(messageBox instanceof HTMLElement)) throw new Error('message box missing')
  const input = messageBox.querySelector('input')
  if (!(input instanceof HTMLInputElement)) throw new Error('confirmation input missing')
  setNativeInputValue(input, value)
  await waitFor(() => input.value === value, 'confirmation value applied')
  await nextTick()
  const confirm =
    buttonByText(messageBox, '确认替换') ??
    buttonByText(messageBox, '确认恢复') ??
    buttonByText(messageBox, '确认升级') ??
    buttonByText(messageBox, '确定')
  if (!confirm) throw new Error('confirmation button missing')
  confirm.click()
}

export async function runFailedUpgradeRecoveryBehaviorHarness(): Promise<string[]> {
  const passed: string[] = []
  const expectedTuple = { app: 'sample-plugin', fromVersion: '0.6.0', toVersion: '0.7.0' }

  const v2Inspection = parseInspectionResult(
    {
      app: 'sample-plugin',
      from_version: '0.6.0',
      to_version: '0.7.0',
      recovery_mode: 'verification_required',
      allowed_actions: ['prepare_failed_upgrade_replacement'],
      message: '恢复状态检查完成'
    },
    expectedTuple
  )
  expectTrue(
    !v2Inspection.runtimeDrift,
    'v2 inspection does not require a fabricated runtime field'
  )
  const v2RuntimeInspection = parseInspectionResult(
    {
      app: 'sample-plugin',
      from_version: '0.6.0',
      to_version: '0.7.0',
      recovery_mode: 'runtime_restore_required',
      runtime_drift: true,
      allowed_actions: ['restore_runtime_from_backup'],
      message: '需要恢复运行文件'
    },
    expectedTuple
  )
  expectTrue(
    v2RuntimeInspection.runtimeDrift,
    'explicit runtime restore DTO opens the restore path'
  )
  for (const runtimeDrift of [undefined, 'true']) {
    try {
      parseInspectionResult(
        {
          app: 'sample-plugin',
          from_version: '0.6.0',
          to_version: '0.7.0',
          recovery_mode: 'runtime_restore_required',
          runtime_drift: runtimeDrift,
          allowed_actions: ['restore_runtime_from_backup']
        },
        expectedTuple
      )
      throw new Error('runtime restore without a boolean drift must fail')
    } catch (error: unknown) {
      if (!(error instanceof Error) || error.message !== FAILED_UPGRADE_VERIFY_INCOMPLETE_MESSAGE) {
        throw error
      }
    }
  }
  passed.push('v2 inspect DTO uses exact mode/action and explicit runtime drift only when required')

  for (const payload of [
    {
      app: 'sample-plugin',
      from_version: '0.6.0',
      replacement_id: 'rep-1',
      profile_hash: 'f'.repeat(64)
    },
    {
      app: 'sample-plugin',
      from_version: '0.6.0',
      to_version: '0.7.1',
      replacement_id: 'rep-1',
      profile_hash: 'f'.repeat(64)
    }
  ]) {
    try {
      parsePrepareResult(payload, expectedTuple)
      throw new Error('prepare tuple mismatch must fail')
    } catch (error: unknown) {
      if (!(error instanceof Error) || error.message !== FAILED_UPGRADE_VERIFY_MISMATCH_MESSAGE) {
        throw error
      }
    }
  }
  passed.push('prepare requires the complete matching failed-upgrade tuple')

  try {
    parseVerifyResult(
      {
        app: 'sample-plugin',
        from_version: '0.6.0',
        to_version: '0.7.0',
        message: FAILED_UPGRADE_VERIFIED_MESSAGE
      },
      { app: 'sample-plugin', fromVersion: '0.6.0', toVersion: '0.7.0' },
      'rep-1',
      'f'.repeat(64)
    )
    throw new Error('missing verdict must fail')
  } catch (error: unknown) {
    if (!(error instanceof Error) || error.message !== FAILED_UPGRADE_VERIFY_INCOMPLETE_MESSAGE) {
      throw error
    }
  }
  const prepareOnlyVerify = parseVerifyResult(
    {
      app: 'sample-plugin',
      from_version: '0.6.0',
      to_version: '0.7.0',
      verdict: 'retry_safe',
      replacement_id: 'rep-1',
      profile_hash: 'f'.repeat(64),
      allowed_actions: ['replace_failed_upgrade_candidate']
    },
    { app: 'sample-plugin', fromVersion: '0.6.0', toVersion: '0.7.0' },
    'rep-1',
    'f'.repeat(64)
  )
  expectTrue(
    prepareOnlyVerify.app === 'sample-plugin',
    'Gate A accepts matching sealed replacement'
  )
  try {
    parseVerifyResult(
      {
        app: 'sample-plugin',
        from_version: '0.6.0',
        to_version: '0.7.0',
        verdict: 'retry_safe',
        replacement_id: 'rep-1',
        profile_hash: 'f'.repeat(64),
        allowed_actions: ['replace_failed_upgrade_candidate', 'retry_after_replacement']
      },
      { app: 'sample-plugin', fromVersion: '0.6.0', toVersion: '0.7.0' },
      'rep-1',
      'f'.repeat(64)
    )
    throw new Error('verify retry action must fail')
  } catch (error: unknown) {
    if (!(error instanceof Error) || error.message !== FAILED_UPGRADE_VERIFY_INCOMPLETE_MESSAGE) {
      throw error
    }
  }
  try {
    parseVerifyResult(
      {
        app: 'other',
        from_version: '0.6.0',
        to_version: '0.7.0',
        verdict: 'retry_safe',
        replacement_id: 'other-rep',
        profile_hash: 'f'.repeat(64),
        allowed_actions: ['replace_failed_upgrade_candidate']
      },
      { app: 'sample-plugin', fromVersion: '0.6.0', toVersion: '0.7.0' },
      'rep-1',
      'f'.repeat(64)
    )
    throw new Error('mismatched app must fail')
  } catch (error: unknown) {
    if (!(error instanceof Error) || error.message !== FAILED_UPGRADE_VERIFY_MISMATCH_MESSAGE) {
      throw error
    }
  }
  try {
    replaceConfirmationText('sample-plugin', '0.7.0', 'REPLACE other@9.9.9')
    throw new Error('foreign REPLACE must fail')
  } catch (error: unknown) {
    if (!(error instanceof Error) || !error.message.includes('确认内容与当前应用版本不一致')) {
      throw error
    }
  }
  expectTrue(
    humanizeRecoveryMessage('SELECT * FROM pg_catalog.pg_class', 'fallback') === 'fallback',
    'sql hidden'
  )
  const zipOk = new File([new Uint8Array(8)], 'ok.zip', {
    type: 'application/zip'
  })
  const zipOver = new File([new Uint8Array(FAILED_UPGRADE_ZIP_MAX_BYTES + 1)], 'over.zip', {
    type: 'application/zip'
  })
  expectTrue(assertFailedUpgradeZip(zipOk) === null, 'small zip ok')
  expectTrue(assertFailedUpgradeZip(zipOver) === '插件包不能超过 5MB', 'oversize zip')

  const durable = exactFailedRow({
    ...exactReplacementMarkers(),
    recovery_mode: 'retry_safe',
    allowed_actions: ['retry_after_replacement']
  })
  expectTrue(isDurableRetryReadyRow(durable), 'durable retry shape')
  expectTrue(
    !isDurableRetryReadyRow(
      exactFailedRow({
        recovery_mode: 'retry_safe',
        ...exactReplacementMarkers(),
        allowed_actions: ['prepare_replacement', 'retry_after_replacement']
      })
    ),
    'verify-shaped retry_safe is not phase 3'
  )
  const session = createFailedUpgradeSession('sample-plugin', '0.6.0', '0.7.0')
  session.phase = 'replaced'
  reconcileFailedUpgradeSession(session, exactFailedRow())
  expectTrue(
    sessionPhase(session) === 'needs_verify',
    'missing retry_safe after refresh returns to verify'
  )
  passed.push('helper contracts remain fail-closed')

  const controllerEnvelope = {
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
  const parsedRestore = parseRuntimeRestoreResult(controllerEnvelope)
  expectTrue(
    parsedRestore.status === 'restored' &&
      parsedRestore.presentation.recoveryMode === 'verification_required',
    'Controller envelope requires durable restore fields and fresh presentation'
  )
  const restored = await restoreRuntimeFromBackup({
    appName: 'sample-plugin',
    confirmation: 'RESTORE RUNTIME sample-plugin@0.6.0',
    expected: { app: 'sample-plugin', fromVersion: '0.6.0', toVersion: '0.7.0' }
  })
  expectTrue(
    restored.presentation.allowedActions[0] === 'prepare_failed_upgrade_replacement' &&
      currentRecoveryPostUrl() === '/app/sandpackage/install/restoreRuntimeFromBackup',
    'restore uses the established POST route and v2 durable presentation'
  )
  for (const malformed of [
    { code: 0, message: '运行文件恢复完成', data: {} },
    {
      code: 0,
      message: '运行文件恢复完成',
      data: {
        ...controllerEnvelope.data,
        presentation: {
          recovery_mode: 'verification_required'
        }
      }
    },
    {
      code: 0,
      message: '运行文件恢复完成',
      data: {
        ...controllerEnvelope.data,
        presentation: {
          recovery_mode: 'verification_required',
          allowed_actions: ['prepare_replacement']
        }
      }
    },
    {
      code: 0,
      message: '运行文件恢复完成',
      data: {
        ...controllerEnvelope.data,
        presentation: {
          recovery_mode: 'retry_safe',
          allowed_actions: ['retry_after_replacement']
        }
      }
    }
  ]) {
    try {
      parseRuntimeRestoreResult(malformed)
      throw new Error('missing or contradictory restore envelope must fail')
    } catch (error: unknown) {
      if (
        !(error instanceof Error) ||
        error.message !== FAILED_UPGRADE_RUNTIME_RESTORE_UNCONFIRMED_MESSAGE
      ) {
        throw error
      }
    }
  }
  passed.push('restore envelope rejects missing or contradictory durable evidence')

  configureRecoveryHttpMock('verification_required')
  const host = document.createElement('div')
  document.body.appendChild(host)
  const app = mountSandpackageInstallPage(host)
  await waitFor(() => textOf(host).includes('数据库升级未完成'), 'install page rendered')
  expectTrue(buttonByText(host, '上传插件包') === null, 'exact-failed hides upload')
  expectTrue(buttonByText(host, '重新执行升级') === null, 'retry hidden before diagnosis')
  const inspectBtn = buttonByText(host, '诊断恢复状态')
  expectTrue(inspectBtn !== null, 'diagnosis button present')
  inspectBtn?.click()
  await waitFor(() => buttonByText(host, '预检替换候选') !== null, 'prepare after diagnosis')
  expectTrue(buttonByText(host, '重新执行升级') === null, 'diagnosis alone cannot expose retry')
  passed.push('mounted index.vue diagnosis opens only ZIP preflight')

  const zip = new File([new Uint8Array([0x50, 0x4b, 0x03, 0x04])], 'sample-plugin.zip', {
    type: 'application/zip'
  })
  await assignZipFile(host, zip)
  await waitFor(() => {
    const prepare = buttonByText(host, '预检替换候选')
    return prepare !== null && !prepare.disabled
  }, 'prepare enabled after zip')
  buttonByText(host, '预检替换候选')?.click()
  await waitFor(() => buttonByText(host, '只读 Gate A 核验') !== null, 'Gate A after prepare')
  expectTrue(buttonByText(host, '替换已核验候选') === null, 'prepare cannot expose replace')
  buttonByText(host, '只读 Gate A 核验')?.click()
  await waitFor(() => buttonByText(host, '替换已核验候选') !== null, 'replace after Gate A')
  expectTrue(buttonByText(host, '重新执行升级') === null, 'Gate A cannot expose retry')
  buttonByText(host, '替换已核验候选')?.click()
  await confirmPrompt(document, exactReplaceConfirmation('sample-plugin', '0.7.0'))
  await waitFor(() => buttonByText(host, '重新执行升级') !== null, 'retry after replace refresh')
  expectTrue(buttonByText(host, '替换已核验候选') === null, 'replace hidden on phase 3')
  passed.push('prepare then Gate A then replace refreshes into retry')

  configureRecoveryHttpMock('mismatched_retry_ready')
  buttonByText(host, '刷新')?.click()
  await waitFor(
    () =>
      buttonByText(host, '重新执行升级') === null && buttonByText(host, '诊断恢复状态') !== null,
    'mismatched refresh returns to diagnosis'
  )
  passed.push('mismatched durable tuple clears retry eligibility')

  for (const fixture of ['partial_modern_retry_safe', 'invalid_digest_retry_safe'] as const) {
    configureRecoveryHttpMock(fixture)
    buttonByText(host, '刷新')?.click()
    await waitFor(
      () =>
        buttonByText(host, '重新执行升级') === null && buttonByText(host, '诊断恢复状态') !== null,
      `${fixture} refresh clears retry`
    )
  }
  passed.push('partial or malformed modern list markers clear stale retry actions')

  app.unmount()
  host.remove()

  configureRecoveryHttpMock('runtime_restore_required')
  const restoreHost = document.createElement('div')
  document.body.appendChild(restoreHost)
  const restoreApp = mountSandpackageInstallPage(restoreHost)
  await waitFor(
    () => buttonByText(restoreHost, '恢复升级前运行文件') !== null,
    'runtime restore action rendered only for exact drift presentation'
  )
  for (const blockedAction of [
    '诊断恢复状态',
    '预检替换候选',
    '只读 Gate A 核验',
    '替换已核验候选',
    '重新执行升级'
  ]) {
    expectTrue(
      buttonByText(restoreHost, blockedAction) === null,
      `runtime drift only permits restore: ${blockedAction}`
    )
  }
  buttonByText(restoreHost, '恢复升级前运行文件')?.click()
  await confirmPrompt(document, 'RESTORE RUNTIME sample-plugin@0.6.0')
  await waitFor(
    () => buttonByText(restoreHost, '预检替换候选') !== null,
    'exact refreshed verification action advances runtime restore to ZIP preflight'
  )
  expectTrue(
    buttonByText(restoreHost, '诊断恢复状态') === null &&
      buttonByText(restoreHost, '重新执行升级') === null,
    'restore refresh opens only needs_prepare'
  )
  passed.push('runtime restore refreshes exact list action into ZIP preflight only')
  restoreApp.unmount()
  restoreHost.remove()

  for (const refreshFixture of [
    'verification_required_missing_actions',
    'verification_required_wrong_actions'
  ] as const) {
    configureRecoveryHttpMock('runtime_restore_required')
    configureRuntimeRestoreRefreshFixture(refreshFixture)
    const malformedRestoreHost = document.createElement('div')
    document.body.appendChild(malformedRestoreHost)
    const malformedRestoreApp = mountSandpackageInstallPage(malformedRestoreHost)
    await waitFor(
      () => buttonByText(malformedRestoreHost, '恢复升级前运行文件') !== null,
      `${refreshFixture} restore action rendered`
    )
    buttonByText(malformedRestoreHost, '恢复升级前运行文件')?.click()
    await confirmPrompt(document, 'RESTORE RUNTIME sample-plugin@0.6.0')
    await waitFor(
      () => buttonByText(malformedRestoreHost, '诊断恢复状态') !== null,
      `${refreshFixture} refresh fails closed`
    )
    expectTrue(
      buttonByText(malformedRestoreHost, '预检替换候选') === null &&
        buttonByText(malformedRestoreHost, '重新执行升级') === null,
      `${refreshFixture} cannot infer a write action`
    )
    malformedRestoreApp.unmount()
    malformedRestoreHost.remove()
  }
  passed.push('restore refresh missing or wrong list action fails closed')

  configureRecoveryHttpMock('retry_ready')
  const retryHost = document.createElement('div')
  document.body.appendChild(retryHost)
  const retryApp = mountSandpackageInstallPage(retryHost)
  await waitFor(() => buttonByText(retryHost, '重新执行升级') !== null, 'durable retry ready')
  expectTrue(
    exactRetryConfirmation('sample-plugin', '0.6.0', '0.7.0') ===
      'RETRY sample-plugin@0.6.0->0.7.0',
    'retry confirmation is exact'
  )
  passed.push('durable retry readiness requires the exact retry confirmation tuple')

  configureRecoveryHttpMock('verification_required')
  const toolbarRefresh = retryHost.querySelector('button')
  if (!(toolbarRefresh instanceof HTMLButtonElement)) throw new Error('toolbar refresh missing')
  toolbarRefresh.click()
  await waitFor(() => buttonByText(retryHost, '诊断恢复状态') !== null, 'recovery row restored')

  configureRecoveryHttpMock('blocked')
  buttonByText(retryHost, '刷新')?.click()
  await waitFor(
    () => buttonByText(retryHost, '重新执行升级') === null,
    'blocked refresh hides retry'
  )
  expectTrue(
    textOf(retryHost).includes('无法继续当前升级') || textOf(retryHost).includes('不属于已验证'),
    'blocked copy'
  )
  expectTrue(
    buttonsOf(retryHost).filter((button) => textOf(button) === '刷新').length === 0,
    'blocked has no recovery buttons'
  )
  passed.push('blocked hides every recovery button')

  configureRecoveryHttpMock('error')
  const errorHost = document.createElement('div')
  document.body.appendChild(errorHost)
  const errorApp = mountSandpackageInstallPage(errorHost)
  await waitFor(
    () => textOf(errorHost).includes('插件列表加载失败') || textOf(errorHost).includes('未能加载'),
    'list error visible'
  )
  expectTrue(buttonByText(errorHost, '重新执行升级') === null, 'list error hides retry')
  expectTrue(buttonByText(errorHost, '预检替换候选') === null, 'list error closes writes')
  passed.push('list failure is observable and closes recovery writes')
  errorApp.unmount()
  errorHost.remove()

  retryApp.unmount()
  retryHost.remove()
  return passed
}
