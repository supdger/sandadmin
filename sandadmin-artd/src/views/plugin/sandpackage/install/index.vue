<template>
  <div class="sandpackage-page">
    <ElCard class="sandpackage-page-card" shadow="never">
      <!-- 提示警告 -->
      <ElAlert type="warning" :closable="false">
        仅支持可信来源的 ZIP 插件包，请通过配置的插件仓库准备候选，或确认手动上传包的来源与完整性。
      </ElAlert>

      <ElAlert
        v-if="listError"
        class="mt-3"
        type="error"
        :closable="false"
        title="插件列表未能加载"
      >
        <div class="failed-upgrade-error-row">
          <span>{{ listError }}</span>
          <ElButton size="small" @click="getList()">重新加载</ElButton>
        </div>
      </ElAlert>

      <section
        v-for="row in failedUpgradeRows"
        :key="'failed-upgrade-' + row.app"
        class="failed-upgrade-card"
      >
        <ElAlert type="warning" :closable="false" :title="failedUpgradeTitle(row)">
          {{ recoveryReason(row) }}
        </ElAlert>
        <p class="failed-upgrade-steps" aria-label="失败升级恢复步骤">
          <span :class="{ current: recoveryStep(row) === 1 }">1 诊断</span>
          <span class="step-arrow">→</span>
          <span :class="{ current: recoveryStep(row) === 2 }">2 预检 ZIP</span>
          <span class="step-arrow">→</span>
          <span :class="{ current: recoveryStep(row) === 2 }">3 Gate A</span>
          <span class="step-arrow">→</span>
          <span :class="{ current: recoveryStep(row) === 2 }">4 替换</span>
          <span class="step-arrow">→</span>
          <span :class="{ current: recoveryStep(row) === 3 }">5 重试</span>
        </p>
        <p class="failed-upgrade-meta">
          {{ row.title || row.app }}：{{ recoveryFromVersion(row) }} →
          {{ row.version }}
        </p>

        <ElAlert
          v-if="sessionOf(row).errorMessage"
          class="mt-3"
          type="error"
          :closable="false"
          title="恢复未完成"
        >
          {{ sessionOf(row).errorMessage }}
        </ElAlert>
        <ElAlert
          v-else-if="sessionOf(row).phase === 'success'"
          class="mt-3"
          type="success"
          :closable="false"
        >
          {{ sessionOf(row).message }}
        </ElAlert>
        <ElAlert
          v-else-if="
            sessionOf(row).message &&
            (sessionOf(row).phase !== 'needs_verify' ||
              sessionOf(row).message === FAILED_UPGRADE_RUNTIME_RESTORED_MESSAGE) &&
            !sessionOf(row).blocked
          "
          class="mt-3"
          type="info"
          :closable="false"
        >
          {{ sessionOf(row).message }}
        </ElAlert>

        <div v-if="canRestoreRuntime(row)" class="failed-upgrade-actions">
          <ElButton :disabled="loading || !!listError || pluginOperationBusy" @click="getList()">
            刷新
          </ElButton>
          <ElButton
            type="primary"
            :disabled="pluginOperationBusy"
            :loading="sessionOf(row).phase === 'restoring_runtime'"
            @click="handleRestoreRuntime(row)"
            >恢复升级前运行文件</ElButton
          >
        </div>
        <div v-else-if="sessionOf(row).blocked" class="failed-upgrade-actions"></div>
        <div
          v-else-if="
            sessionOf(row).phase === 'needs_verify' || sessionOf(row).phase === 'diagnosing'
          "
          class="failed-upgrade-actions"
        >
          <ElButton
            :disabled="loading || isRecoveryBusy(row) || pluginOperationBusy"
            @click="getList()"
          >
            刷新
          </ElButton>
          <ElButton
            :disabled="loading || !!listError || pluginOperationBusy"
            :loading="sessionOf(row).phase === 'diagnosing'"
            @click="handleInspectRecovery(row)"
          >
            诊断恢复状态
          </ElButton>
        </div>
        <div v-else class="failed-upgrade-actions" v-loading="isRecoveryBusy(row)">
          <ElButton
            :disabled="loading || isRecoveryBusy(row) || pluginOperationBusy"
            @click="getList()"
          >
            刷新
          </ElButton>
          <template
            v-if="sessionOf(row).phase === 'needs_prepare' || sessionOf(row).phase === 'preparing'"
          >
            <ElUpload
              accept=".zip"
              :auto-upload="false"
              :limit="1"
              :show-file-list="true"
              :disabled="isRecoveryBusy(row) || pluginOperationBusy"
              :on-change="(file) => onRecoveryFileChange(row, file)"
              :on-remove="() => onRecoveryFileRemove(row)"
            >
              <ElButton :disabled="isRecoveryBusy(row) || pluginOperationBusy">
                选择 ZIP 插件包
              </ElButton>
              <template #tip>
                <div class="failed-upgrade-upload-tip"> 仅接受 ZIP，且不超过 5MB </div>
              </template>
            </ElUpload>
            <ElButton
              type="primary"
              :disabled="
                isRecoveryBusy(row) || pluginOperationBusy || sessionOf(row).selectedFileName === ''
              "
              :loading="
                sessionOf(row).phase === 'preparing' || sessionOf(row).phase === 'replacing'
              "
              @click="handlePrepareRecoveryCandidate(row)"
            >
              预检替换候选
            </ElButton>
          </template>
          <ElButton
            v-if="sessionOf(row).phase === 'needs_gate_a' || sessionOf(row).phase === 'verifying'"
            type="primary"
            :disabled="pluginOperationBusy"
            :loading="sessionOf(row).phase === 'verifying'"
            @click="handleVerifyRecovery(row)"
          >
            只读 Gate A 核验
          </ElButton>
          <ElButton
            v-if="sessionOf(row).phase === 'retry_safe'"
            type="primary"
            :disabled="pluginOperationBusy"
            :loading="sessionOf(row).phase === 'replacing'"
            @click="handleReplaceRecoveryCandidate(row)"
          >
            替换已核验候选
          </ElButton>
          <ElButton
            v-if="recoveryStep(row) === 3 && sessionOf(row).phase !== 'success'"
            type="primary"
            :disabled="pluginOperationBusy"
            :loading="sessionOf(row).phase === 'retrying'"
            @click="handleRetryRecovery(row)"
          >
            重新执行升级
          </ElButton>
        </div>
      </section>

      <!-- 工具栏 -->
      <div class="flex flex-wrap items-center my-2 gap-2">
        <ElButton @click="getList()" v-ripple :loading="loading" :disabled="pluginOperationBusy">
          <template #icon>
            <ArtSvgIcon icon="ri:refresh-line" />
          </template>
        </ElButton>
        <ElButton
          v-if="!hideGlobalPluginWrites"
          @click="handleUpload"
          v-ripple
          :disabled="pluginOperationBusy"
        >
          <template #icon>
            <ArtSvgIcon icon="ri:upload-line" />
          </template>
          上传插件包
        </ElButton>
        <ElButton
          v-if="!hideGlobalPluginWrites"
          type="danger"
          @click="handleTerminal"
          v-ripple
          :disabled="pluginOperationBusy"
        >
          <template #icon>
            <ArtSvgIcon icon="ri:terminal-box-line" />
          </template>
        </ElButton>

        <div class="flex flex-wrap items-center gap-1 ml-auto max-w-full">
          <div class="version-title">sandadmin版本</div>
          <div class="version-value">
            {{ version?.sandadmin_version?.describe }}
          </div>
          <div class="version-title">状态</div>
          <div
            class="version-value"
            :class="[
              version?.sandadmin_version?.notes === '正常' ? 'text-green-500' : 'text-red-500'
            ]"
          >
            {{ version?.sandadmin_version?.notes }}
          </div>
          <div class="version-title">sandpackage安装器</div>
          <div class="version-value">
            {{ version?.sandpackage_version?.describe }}
          </div>
          <div class="version-title">状态</div>
          <div
            class="version-value"
            :class="[
              version?.sandpackage_version?.notes === '正常' ? 'text-green-500' : 'text-red-500'
            ]"
          >
            {{ version?.sandpackage_version?.notes }}
          </div>
        </div>
      </div>

      <!-- Tab切换 -->
      <ElTabs v-model="activeTab" type="border-card">
        <!-- 插件管理 Tab -->
        <ElTabPane label="插件管理" name="local">
          <ArtTable
            :loading="loading"
            :data="installList"
            :columns="columns"
            :show-table-header="false"
          >
            <!-- 插件标识列 -->
            <template #app="{ row }">
              <ElLink :href="row.website" target="_blank" type="primary">{{ row.app }}</ElLink>
            </template>

            <!-- 状态列 -->
            <template #state="{ row }">
              <ElTooltip v-if="isLegacyFailedUpgradeRecovery(row)" placement="top">
                <template #content>
                  <div>{{ recoveryReason(row) }}</div>
                </template>
                <ElTag type="danger">{{ stateText(row) }}</ElTag>
              </ElTooltip>
              <ElTooltip
                v-else-if="row.stage_label || row.last_error || row.recovery_reason"
                placement="top"
              >
                <template #content>
                  <div>{{ row.stage_label }}</div>
                  <div v-if="row.last_error" class="mt-1">
                    {{ row.last_error }}
                  </div>
                  <div v-if="row.recovery_reason" class="mt-1">
                    {{ row.recovery_reason }}
                  </div>
                </template>
                <ElTag :type="stateTagType(row)">{{ stateText(row) }}</ElTag>
              </ElTooltip>
              <ElTag v-else :type="stateTagType(row)">{{ stateText(row) }}</ElTag>
            </template>

            <!-- 前端依赖列 -->
            <template #npm="{ row }">
              <ElTag v-if="isLegacyFailedUpgradeRecovery(row)" type="info">-</ElTag>
              <ElLink
                v-else-if="row.npm_dependent_wait_install === 1"
                type="primary"
                :disabled="pluginOperationBusy"
                @click="handleExecFront(row)"
              >
                <ArtSvgIcon icon="ri:download-line" class="mr-1" />点击安装
              </ElLink>
              <ElTag v-else-if="row.state === 1" type="success">已安装</ElTag>
              <ElTag v-else type="info">-</ElTag>
            </template>

            <!-- 后端依赖列 -->
            <template #composer="{ row }">
              <ElTag v-if="isLegacyFailedUpgradeRecovery(row)" type="info">-</ElTag>
              <ElLink
                v-else-if="row.composer_dependent_wait_install === 1"
                type="primary"
                :disabled="pluginOperationBusy"
                @click="handleExecBackend(row)"
              >
                <ArtSvgIcon icon="ri:download-line" class="mr-1" />点击安装
              </ElLink>
              <ElTag v-else-if="row.state === 1" type="success">已安装</ElTag>
              <ElTag v-else type="info">-</ElTag>
            </template>

            <!-- 操作列 -->
            <template #operation="{ row }">
              <span v-if="isLegacyFailedUpgradeRecovery(row)" class="failed-upgrade-op-hint">
                请按上方恢复流程处理
              </span>
              <ElSpace v-else wrap>
                <ElLink
                  v-if="row.registration_candidate === 1"
                  type="primary"
                  :disabled="pluginOperationBusy"
                  @click="handleRegisterExisting(row)"
                >
                  <ArtSvgIcon icon="ri:shield-check-line" class="mr-1" />登记现有插件
                </ElLink>
                <template v-else-if="isCompatibleUpgradeCandidate(row)">
                  <ElLink
                    type="primary"
                    :disabled="pluginOperationBusy"
                    @click="handleUpgradeCandidate(row)"
                  >
                    <ArtSvgIcon icon="ri:arrow-up-circle-line" class="mr-1" />确认升级
                  </ElLink>
                  <ElLink
                    v-if="!isPostgresqlLifecycleRecord(row)"
                    type="warning"
                    :disabled="pluginOperationBusy"
                    @click="handleDiscardCandidate(row)"
                  >
                    <ArtSvgIcon icon="ri:arrow-go-back-line" class="mr-1" />撤回候选
                  </ElLink>
                </template>
                <template v-else-if="isLegacyRecoverableCandidate(row)">
                  <ElTag type="warning">这是较早版本上传的候选，请先撤回后重新上传</ElTag>
                  <ElLink
                    type="warning"
                    :disabled="pluginOperationBusy"
                    @click="handleDiscardCandidate(row)"
                  >
                    <ArtSvgIcon icon="ri:arrow-go-back-line" class="mr-1" />撤回候选
                  </ElLink>
                </template>
                <template v-else-if="isReadyUpgradeCandidate(row)">
                  <ElTag type="warning">
                    {{
                      isPostgresqlLifecycleRecord(row)
                        ? '与当前宿主版本不兼容，不能升级'
                        : '与当前宿主版本不兼容，仅可撤回候选'
                    }}
                  </ElTag>
                  <ElLink
                    v-if="!isPostgresqlLifecycleRecord(row)"
                    type="warning"
                    :disabled="pluginOperationBusy"
                    @click="handleDiscardCandidate(row)"
                  >
                    <ArtSvgIcon icon="ri:arrow-go-back-line" class="mr-1" />撤回候选
                  </ElLink>
                </template>
                <ElTag v-else-if="isUpgradeCandidateStage(row)" type="danger"
                  >候选不完整，请联系管理员</ElTag
                >
                <ElPopconfirm
                  v-else-if="canInstallLocal(row)"
                  title="确定要安装当前插件吗?"
                  @confirm="handleInstall(row)"
                  confirm-button-text="确定"
                  cancel-button-text="取消"
                >
                  <template #reference>
                    <ElLink type="warning" :disabled="pluginOperationBusy">
                      <ArtSvgIcon icon="ri:apps-2-add-line" class="mr-1" />安装
                    </ElLink>
                  </template>
                </ElPopconfirm>
                <ElPopconfirm
                  v-if="canUninstallLocal(row)"
                  title="确定要卸载当前插件吗?"
                  @confirm="handleUninstall(row)"
                  confirm-button-text="确定"
                  cancel-button-text="取消"
                >
                  <template #reference>
                    <ElLink type="danger" :disabled="pluginOperationBusy">
                      <ArtSvgIcon icon="ri:delete-bin-5-line" class="mr-1" />卸载
                    </ElLink>
                  </template>
                </ElPopconfirm>
                <ElTag
                  v-if="
                    !canInstallLocal(row) &&
                    !canUninstallLocal(row) &&
                    row.registration_candidate !== 1 &&
                    !isUpgradeCandidateStage(row)
                  "
                  :type="row.state === 1 ? 'success' : 'warning'"
                >
                  {{ localActionReason(row) }}
                </ElTag>
              </ElSpace>
            </template>
          </ArtTable>
        </ElTabPane>

        <!-- 插件仓库 Tab -->
        <ElTabPane label="插件仓库" name="repository">
          <div class="repository-toolbar">
            <ElInput
              v-model="repositoryKeyword"
              placeholder="搜索插件名称、标识、作者或简介"
              clearable
              class="repository-search"
            >
              <template #prefix>
                <ArtSvgIcon icon="ri:search-line" />
              </template>
            </ElInput>
            <ElButton
              :loading="repositoryLoading"
              :disabled="repositoryDownloading"
              @click="fetchRepositoryCatalog"
            >
              <template #icon>
                <ArtSvgIcon icon="ri:refresh-line" />
              </template>
              刷新仓库
            </ElButton>
            <div v-if="repositoryCatalog" class="repository-source">
              <span>{{ repositoryCatalog.repository }}</span>
              <ElTag size="small" type="info">{{ repositoryCatalog.ref }}</ElTag>
            </div>
          </div>

          <div v-if="repositoryLoading" class="repository-state" v-loading="true">
            正在读取插件仓库
          </div>
          <ElAlert
            v-else-if="repositoryError"
            type="error"
            :closable="false"
            title="插件仓库读取失败"
          >
            <div class="repository-error-row">
              <span>{{ repositoryError }}</span>
              <ElButton size="small" @click="fetchRepositoryCatalog">重新加载</ElButton>
            </div>
          </ElAlert>
          <ElEmpty
            v-else-if="repositoryLoaded && repositoryPlugins.length === 0"
            description="仓库暂未发布可用插件"
          />
          <ElEmpty
            v-else-if="repositoryLoaded && filteredRepositoryPlugins.length === 0"
            description="没有匹配当前关键词的插件"
          />
          <div v-else class="app-grid">
            <article v-for="item in filteredRepositoryPlugins" :key="item.app" class="app-card">
              <div class="app-card-header">
                <div class="repository-plugin-icon" aria-hidden="true">
                  <ArtSvgIcon icon="ri:plug-line" />
                </div>
                <div class="app-info">
                  <div class="app-title">{{ item.title }}</div>
                  <div class="app-version">{{ item.app }}</div>
                </div>
                <ElTag :type="repositoryLocalTagType(item.local)" size="small">
                  {{ repositoryLocalLabel(item.local) }}
                </ElTag>
              </div>
              <p class="app-about">{{ item.about }}</p>
              <ElAlert
                v-if="item.local.reason"
                class="repository-local-alert"
                :type="item.local.blocked ? 'warning' : 'info'"
                :closable="false"
              >
                {{ item.local.reason }}
              </ElAlert>
              <div class="app-footer">
                <span>{{ item.author }}</span>
                <ElSpace wrap>
                  <ElButton
                    v-if="item.versions[0]"
                    size="small"
                    @click="openRepositoryDocument(item, item.versions[0])"
                  >
                    查看文档
                  </ElButton>
                  <ElButton
                    v-if="item.local.blocked"
                    size="small"
                    type="warning"
                    @click="goToPluginManagement"
                  >
                    去插件管理
                  </ElButton>
                  <ElButton
                    type="primary"
                    size="small"
                    :disabled="item.versions.length === 0"
                    @click="showRepositoryVersions(item)"
                  >
                    查看版本
                  </ElButton>
                </ElSpace>
              </div>
            </article>
          </div>
        </ElTabPane>
      </ElTabs>
    </ElCard>

    <!-- 上传插件弹窗 -->
    <InstallForm
      ref="installFormRef"
      :disabled="pluginOperationBusy"
      :can-start-write="canStartUploadWrite"
      :after-upload="refreshAfterUpload"
      @busy-change="handleUploadBusyChange"
    />

    <!-- 终端弹窗 -->
    <TerminalBox ref="terminalRef" @success="getList" />

    <!-- 仓库版本选择对话框 -->
    <ElDialog
      v-model="repositoryVersionVisible"
      :title="'选择版本 - ' + (currentRepositoryPlugin?.title || '')"
      width="680"
      :close-on-click-modal="!repositoryDownloading"
      :close-on-press-escape="!repositoryDownloading"
      :show-close="!repositoryDownloading"
    >
      <ElAlert
        v-if="repositoryActionError"
        class="mb-3"
        type="error"
        :closable="false"
        title="插件操作未完成"
      >
        <div class="repository-error-row">
          <span>{{ repositoryActionError }}</span>
          <ElButton size="small" @click="goToPluginManagement">去插件管理</ElButton>
        </div>
      </ElAlert>
      <div class="version-list">
        <div
          v-for="item in currentRepositoryPlugin?.versions || []"
          :key="item.version"
          class="version-item"
        >
          <div>
            <div class="version-info-row">
              <span class="version-name">v{{ item.version }}</span>
              <ElTag size="small" type="info">{{ item.tag }}</ElTag>
            </div>
            <div class="version-compatibility">
              兼容 SandAdmin {{ item.host_min
              }}{{ item.host_max ? ` 至 ${item.host_max}` : ' 及以上' }}
            </div>
            <div class="version-remark">{{ item.notes }}</div>
            <div class="version-action-reason">{{ item.action_reason }}</div>
          </div>
          <ElSpace wrap>
            <ElButton size="small" @click="openRepositoryDocument(currentRepositoryPlugin, item)">
              查看文档
            </ElButton>
            <ElButton
              :type="repositoryActionType(item.action)"
              size="small"
              :loading="downloadingKey === repositoryVersionKey(currentRepositoryPlugin, item)"
              :disabled="repositoryActionDisabled(currentRepositoryPlugin, item)"
              @click="handleRepositoryVersionAction(currentRepositoryPlugin, item)"
            >
              {{ repositoryActionLabel(item.action) }}
            </ElButton>
          </ElSpace>
        </div>
        <ElEmpty
          v-if="(currentRepositoryPlugin?.versions.length || 0) === 0"
          description="暂无可用版本"
        />
      </div>
    </ElDialog>

    <!-- 发布包文档 -->
    <ElDrawer
      v-model="repositoryDocumentVisible"
      :title="repositoryDocumentTitle"
      size="min(760px, 92vw)"
      @closed="closeRepositoryDocument"
    >
      <div v-if="repositoryDocumentLoading" class="repository-state" v-loading="true">
        正在读取发布包文档
      </div>
      <ElAlert
        v-else-if="repositoryDocumentError"
        type="error"
        :closable="false"
        title="文档读取失败"
      >
        <div class="repository-error-row">
          <span>{{ repositoryDocumentError }}</span>
          <ElButton size="small" @click="retryRepositoryDocument">重新加载</ElButton>
        </div>
      </ElAlert>
      <ElEmpty
        v-else-if="repositoryDocumentMarkdown === ''"
        description="该版本未提供 README 文档"
      />
      <pre v-else class="repository-document">{{ repositoryDocumentMarkdown }}</pre>
    </ElDrawer>
  </div>
</template>

<script setup lang="ts">
  import { ref, reactive, computed, onMounted, watch } from 'vue'
  import { ElMessage, ElMessageBox } from 'element-plus'
  import type { ColumnOption } from '@/types'
  import type { UploadFile } from 'element-plus'
  import sandpackageApi, {
    type VersionInfo,
    type RepositoryCatalog,
    type RepositoryPlugin,
    type RepositoryPluginLocal,
    type RepositoryPluginVersion,
    type RepositoryVersionAction
  } from '../api/index'
  import InstallForm from './install-box.vue'
  import TerminalBox from './terminal.vue'
  import { TaskStatus, useTerminalStore } from '../store/terminal'
  import {
    FAILED_UPGRADE_BLOCKED_MESSAGE,
    FAILED_UPGRADE_FAILURE_MESSAGE,
    FAILED_UPGRADE_LIST_ERROR_MESSAGE,
    FAILED_UPGRADE_RUNTIME_RESTORED_MESSAGE,
    FAILED_UPGRADE_RUNTIME_RESTORE_UNCONFIRMED_MESSAGE,
    FAILED_UPGRADE_VERIFY_INCOMPLETE_MESSAGE,
    FAILED_UPGRADE_VERIFY_MISMATCH_MESSAGE,
    FailedUpgradeRecoveryClosedError,
    assertFailedUpgradeZip,
    canShowRecoveryWriteActions,
    clearRecoveryWriteState,
    createFailedUpgradeSession,
    exactReplaceConfirmation,
    exactRestoreRuntimeConfirmation,
    exactRetryConfirmation,
    isFailedUpgradeRecovery,
    isRuntimeRestoreRequiredRow,
    isRuntimeRestoreVerificationPresentation,
    isRecoveryWriteBusy,
    inspectFailedUpgradeRecovery,
    prepareFailedUpgradeReplacement,
    readFailedUpgradeFromVersion,
    readFailedUpgradeReason,
    readRecoveryErrorMessage,
    readRowVersionTuple,
    reconcileFailedUpgradeSession,
    recoveryStepNumber,
    replaceFailedUpgradeCandidate,
    restoreRuntimeFromBackup,
    retryFailedUpgrade,
    shouldHideGlobalPluginWrites,
    tuplesMatch,
    verifyFailedUpgradeRecovery,
    type FailedUpgradeRecoverySession,
    type RecoveryWriteGate,
    type SandpackageInstallRow
  } from './failed-upgrade-recovery'

  interface InstallFormBox {
    open: () => void
  }

  interface TerminalBoxExpose {
    open: () => void
    frontInstall: (extend: string) => void
    backendInstall: (extend: string) => void
  }

  interface RepositoryDocumentTarget {
    app: string
    title: string
    version: string
    sha256: string
  }

  type LocalWriteOwner =
    | ''
    | 'install'
    | 'uninstall'
    | 'upgrade'
    | 'discard'
    | 'register'
    | 'upload'

  // ========== 基础状态 ==========
  const activeTab = ref('local')
  const version = ref<VersionInfo>({})
  const loading = ref(false)
  const listError = ref('')
  const installFormRef = ref<InstallFormBox | null>(null)
  const terminalRef = ref<TerminalBoxExpose | null>(null)
  const installList = ref<SandpackageInstallRow[]>([])
  const recoverySessions = reactive<Record<string, FailedUpgradeRecoverySession>>({})
  const recoveryFiles = new Map<string, File>()
  const terminalStore = useTerminalStore()
  const localWriteOwner = ref<LocalWriteOwner>('')
  const terminalLaunchPending = ref(false)

  const isPostgresqlLifecycleRecord = (row: SandpackageInstallRow): boolean =>
    row.lifecycle_driver === 'saipackage-pg-v1'

  const isLegacyFailedUpgradeRecovery = (row: SandpackageInstallRow): boolean =>
    !isPostgresqlLifecycleRecord(row) && isFailedUpgradeRecovery(row)

  const failedUpgradeRows = computed(() =>
    installList.value.filter((row) => isLegacyFailedUpgradeRecovery(row))
  )

  const recoveryWriteGate = computed<RecoveryWriteGate>(() => ({
    listLoading: loading.value,
    listFailed: listError.value !== ''
  }))

  const hideGlobalPluginWrites = computed(() => {
    if (loading.value || listError.value !== '') return true
    return shouldHideGlobalPluginWrites(failedUpgradeRows.value, recoverySessions)
  })

  const sessionOf = (row: SandpackageInstallRow): FailedUpgradeRecoverySession => {
    const existing = recoverySessions[row.app]
    if (existing) return existing
    const created = createFailedUpgradeSession(
      row.app,
      readFailedUpgradeFromVersion(row),
      row.version
    )
    created.message = readFailedUpgradeReason(row)
    recoverySessions[row.app] = created
    return created
  }

  const isRuntimeRestoreRequired = (row: SandpackageInstallRow): boolean =>
    isRuntimeRestoreRequiredRow(row)

  const canRestoreRuntime = (row: SandpackageInstallRow): boolean => {
    const current = sessionOf(row)
    return isRuntimeRestoreRequired(row) || (current.runtimeDrift && current.runtimeRestoreAllowed)
  }

  const lockAllRecoveryWrites = (): void => {
    for (const session of Object.values(recoverySessions)) {
      if (!session || session.phase === 'success') continue
      clearRecoveryWriteState(session, {
        phase: session.blocked ? 'blocked' : 'needs_verify',
        blocked: session.blocked,
        message: session.blocked ? FAILED_UPGRADE_BLOCKED_MESSAGE : FAILED_UPGRADE_FAILURE_MESSAGE
      })
    }
    recoveryFiles.clear()
  }

  const recoveryReason = (row: SandpackageInstallRow): string => {
    const current = recoverySessions[row.app]
    if (current?.blocked && current.message) return current.message
    return readFailedUpgradeReason(row)
  }

  const failedUpgradeTitle = (row: SandpackageInstallRow): string => {
    return sessionOf(row).blocked ? '无法继续当前升级' : '数据库升级未完成'
  }

  const recoveryFromVersion = (row: SandpackageInstallRow): string => {
    return sessionOf(row).fromVersion || readFailedUpgradeFromVersion(row)
  }

  const recoveryStep = (row: SandpackageInstallRow): 1 | 2 | 3 =>
    recoveryStepNumber(sessionOf(row).phase)

  const isRecoveryBusy = (row: SandpackageInstallRow): boolean =>
    isRecoveryWriteBusy(sessionOf(row).phase)

  const syncFailedUpgradeSessions = (rows: SandpackageInstallRow[]): void => {
    const failedApps = new Set<string>()
    for (const row of rows) {
      if (!isLegacyFailedUpgradeRecovery(row)) continue
      failedApps.add(row.app)
      reconcileFailedUpgradeSession(sessionOf(row), row)
      recoveryFiles.delete(row.app)
    }
    for (const app of Object.keys(recoverySessions)) {
      if (failedApps.has(app)) {
        continue
      }
      delete recoverySessions[app]
      recoveryFiles.delete(app)
    }
  }

  // ========== 本地安装相关 ==========
  const handleUpload = () => {
    if (pluginOperationBusy.value) return
    installFormRef.value?.open()
  }

  const acquireLocalWrite = (owner: Exclude<LocalWriteOwner, '' | 'upload'>): boolean => {
    if (pluginOperationBusy.value) return false
    localWriteOwner.value = owner
    return true
  }

  const releaseLocalWrite = (owner: Exclude<LocalWriteOwner, ''>): void => {
    if (localWriteOwner.value === owner) localWriteOwner.value = ''
  }

  const canStartUploadWrite = (): boolean => !pluginOperationBusy.value

  const handleUploadBusyChange = (busy: boolean): void => {
    if (busy) {
      localWriteOwner.value = 'upload'
      return
    }
    releaseLocalWrite('upload')
  }

  const refreshAfterUpload = async (): Promise<void> => {
    await getList()
  }

  const rejectOrdinaryAction = (record: SandpackageInstallRow): boolean => {
    if (!isLegacyFailedUpgradeRecovery(record)) return false
    ElMessage.warning(FAILED_UPGRADE_FAILURE_MESSAGE)
    return true
  }

  const promptConfirmation = async (
    title: string,
    description: string,
    expected: string,
    confirmButtonText: string
  ): Promise<string | null> => {
    try {
      const result: unknown = await ElMessageBox.prompt(description, title, {
        confirmButtonText,
        cancelButtonText: '取消',
        inputPlaceholder: expected,
        inputValidator: (value: string) => value === expected || '确认内容不匹配'
      })
      if (
        typeof result === 'object' &&
        result !== null &&
        'value' in result &&
        typeof result.value === 'string'
      ) {
        return result.value
      }
      return null
    } catch {
      return null
    }
  }

  /** 第一步：诊断持久失败状态；有运行文件漂移时只允许恢复。 */
  const handleInspectRecovery = async (record: SandpackageInstallRow): Promise<void> => {
    if (pluginOperationBusy.value) return
    if (recoveryWriteGate.value.listLoading || recoveryWriteGate.value.listFailed) return
    const expected = readRowVersionTuple(record)
    const current = sessionOf(record)
    current.phase = 'diagnosing'
    current.errorMessage = ''
    try {
      const result = await inspectFailedUpgradeRecovery(expected)
      await getList(true)
      const refreshed = installList.value.find((row) => row.app === expected.app)
      if (!refreshed || !tuplesMatch(readRowVersionTuple(refreshed), expected)) {
        throw new FailedUpgradeRecoveryClosedError(
          'recoverable',
          FAILED_UPGRADE_VERIFY_MISMATCH_MESSAGE
        )
      }
      const refreshedSession = sessionOf(refreshed)
      refreshedSession.runtimeDrift = result.runtimeDrift
      refreshedSession.runtimeRestoreAllowed =
        result.recoveryMode === 'runtime_restore_required' && result.runtimeDrift
      refreshedSession.blocked = false
      refreshedSession.phase = result.runtimeDrift ? 'needs_verify' : 'needs_prepare'
      refreshedSession.message = result.message
      ElMessage.success(result.message)
    } catch (error: unknown) {
      current.phase = 'error'
      current.errorMessage = readRecoveryErrorMessage(
        error,
        FAILED_UPGRADE_VERIFY_INCOMPLETE_MESSAGE
      )
    }
  }

  /** 第四步 Gate A：只读核验已封存 replacement；失败不展示替换/重试按钮。 */
  const handleVerifyRecovery = async (record: SandpackageInstallRow): Promise<void> => {
    if (pluginOperationBusy.value) return
    if (recoveryWriteGate.value.listLoading || recoveryWriteGate.value.listFailed) return
    const expected = readRowVersionTuple(record)
    const current = sessionOf(record)
    current.phase = 'verifying'
    current.errorMessage = ''
    try {
      if (current.replacementId === '' || current.profileHash === '') {
        throw new FailedUpgradeRecoveryClosedError(
          'recoverable',
          FAILED_UPGRADE_VERIFY_INCOMPLETE_MESSAGE
        )
      }
      const result = await verifyFailedUpgradeRecovery(
        expected,
        current.replacementId,
        current.profileHash
      )
      current.blocked = false
      current.phase = 'retry_safe'
      current.app = result.app
      current.fromVersion = result.fromVersion
      current.toVersion = result.toVersion
      current.message = result.message
      current.replaceConfirmation = exactReplaceConfirmation(result.app, result.toVersion)
      current.retryConfirmation = exactRetryConfirmation(
        result.app,
        result.fromVersion,
        result.toVersion
      )
      ElMessage.success(result.message)
    } catch (error: unknown) {
      const closed = error instanceof FailedUpgradeRecoveryClosedError ? error : undefined
      if (closed?.kind === 'blocked') {
        clearRecoveryWriteState(current, {
          phase: 'blocked',
          blocked: true,
          message: FAILED_UPGRADE_BLOCKED_MESSAGE
        })
        return
      }
      current.blocked = false
      current.phase = 'error'
      current.replacementId = ''
      current.selectedFileName = ''
      current.replaceConfirmation = exactReplaceConfirmation(expected.app, expected.toVersion)
      current.retryConfirmation = exactRetryConfirmation(
        expected.app,
        expected.fromVersion,
        expected.toVersion
      )
      current.errorMessage = readRecoveryErrorMessage(
        error,
        FAILED_UPGRADE_VERIFY_INCOMPLETE_MESSAGE
      )
      current.message = FAILED_UPGRADE_FAILURE_MESSAGE
    }
  }

  const handleRestoreRuntime = async (record: SandpackageInstallRow): Promise<void> => {
    if (pluginOperationBusy.value) return
    if (!canRestoreRuntime(record) || loading.value || listError.value !== '') return
    const expected = readRowVersionTuple(record)
    const confirmation = exactRestoreRuntimeConfirmation(expected.app, expected.fromVersion)
    const typed = await promptConfirmation(
      '恢复升级前运行文件',
      '系统只会隔离当前运行文件并恢复已核验备份，不会修改候选包、备份、登记信息或数据库。请输入下方确认内容。',
      confirmation,
      '确认恢复'
    )
    if (typed === null) return
    const current = sessionOf(record)
    current.phase = 'restoring_runtime'
    current.errorMessage = ''
    try {
      const result = await restoreRuntimeFromBackup({
        appName: expected.app,
        confirmation: typed,
        expected
      })
      // The prompt does not retain a value, and this clears every staged
      // replacement/retry input before the only permitted next path is loaded.
      clearRecoveryWriteState(current, {
        phase: 'needs_verify',
        blocked: false,
        message: result.message
      })
      recoveryFiles.delete(expected.app)
      ElMessage.success(result.message)
      await getList(true)
      const refreshed = installList.value.find((row) => row.app === expected.app)
      if (
        !refreshed ||
        !isRuntimeRestoreVerificationPresentation(refreshed) ||
        !tuplesMatch(readRowVersionTuple(refreshed), expected)
      ) {
        ElMessage.warning(FAILED_UPGRADE_RUNTIME_RESTORE_UNCONFIRMED_MESSAGE)
        return
      }
      const refreshedSession = sessionOf(refreshed)
      clearRecoveryWriteState(refreshedSession, {
        // The durable restore response and the freshly loaded list both grant
        // only the next ZIP preflight action. Do not repeat diagnosis or infer
        // any later write permission from the previous runtime-drift session.
        phase: 'needs_prepare',
        blocked: false,
        message: result.message
      })
    } catch (error: unknown) {
      current.phase = 'error'
      current.errorMessage = readRecoveryErrorMessage(
        error,
        FAILED_UPGRADE_RUNTIME_RESTORE_UNCONFIRMED_MESSAGE
      )
    }
  }

  const onRecoveryFileChange = (record: SandpackageInstallRow, uploadFile: UploadFile): void => {
    const raw = uploadFile.raw
    if (!raw) return
    const problem = assertFailedUpgradeZip(raw)
    if (problem) {
      ElMessage.warning(problem)
      onRecoveryFileRemove(record)
      return
    }
    recoveryFiles.set(record.app, raw)
    const current = sessionOf(record)
    current.selectedFileName = raw.name
    current.errorMessage = ''
  }

  const onRecoveryFileRemove = (record: SandpackageInstallRow): void => {
    recoveryFiles.delete(record.app)
    const current = recoverySessions[record.app]
    if (current) {
      current.selectedFileName = ''
      if (current.phase === 'needs_gate_a') current.phase = 'needs_prepare'
      current.replacementId = ''
      current.profileHash = ''
    }
  }

  /**
   * 第二步：只封存并预检 ZIP。预检成功后才能进入只读 Gate A。
   */
  const handlePrepareRecoveryCandidate = async (record: SandpackageInstallRow): Promise<void> => {
    if (pluginOperationBusy.value) return
    const current = sessionOf(record)
    if (
      recoveryWriteGate.value.listLoading ||
      recoveryWriteGate.value.listFailed ||
      current.phase !== 'needs_prepare'
    )
      return
    const expected = {
      app: current.app,
      fromVersion: current.fromVersion,
      toVersion: current.toVersion
    }
    const file = recoveryFiles.get(record.app)
    if (!file) {
      ElMessage.warning('请先选择 ZIP 插件包')
      return
    }
    const problem = assertFailedUpgradeZip(file)
    if (problem) {
      ElMessage.warning(problem)
      return
    }
    current.phase = 'preparing'
    current.errorMessage = ''
    try {
      const prepared = await prepareFailedUpgradeReplacement(expected, file)
      current.replacementId = prepared.replacementId
      current.profileHash = prepared.profileHash
      current.phase = 'needs_gate_a'
      current.message = prepared.message
      ElMessage.success(prepared.message)
      await getList(true)
    } catch (error: unknown) {
      current.phase = 'needs_prepare'
      current.errorMessage = readRecoveryErrorMessage(
        error,
        '替换候选未完成，请重新选择插件包后再试'
      )
    }
  }

  /** 第五步：确认后替换已通过 Gate A 的候选；成功只由后续列表确认。 */
  const handleReplaceRecoveryCandidate = async (record: SandpackageInstallRow): Promise<void> => {
    if (pluginOperationBusy.value) return
    const current = sessionOf(record)
    if (
      !canShowRecoveryWriteActions(current, recoveryWriteGate.value) ||
      current.phase !== 'retry_safe' ||
      current.replacementId === ''
    )
      return
    const expected = {
      app: current.app,
      fromVersion: current.fromVersion,
      toVersion: current.toVersion
    }
    const confirmation = exactReplaceConfirmation(expected.app, expected.toVersion)
    const typed = await promptConfirmation(
      '替换已核验候选',
      '系统只会替换失败升级使用的候选包，不会执行安装或卸载。请输入下方确认内容。',
      confirmation,
      '确认替换'
    )
    if (typed === null) return
    current.phase = 'replacing'
    current.errorMessage = ''
    try {
      const message = await replaceFailedUpgradeCandidate({
        appName: expected.app,
        replacementId: current.replacementId,
        confirmation: typed,
        expected
      })
      current.message = message
      ElMessage.success(message)
      await getList(true)
    } catch (error: unknown) {
      current.phase = 'retry_safe'
      current.errorMessage = readRecoveryErrorMessage(error, '替换候选未完成，请刷新后重新诊断')
    }
  }

  /**
   * 第三步：按 RETRY app@from->to 确认后重新执行升级。
   */
  const handleRetryRecovery = async (record: SandpackageInstallRow): Promise<void> => {
    if (pluginOperationBusy.value) return
    const current = sessionOf(record)
    if (
      !canShowRecoveryWriteActions(current, recoveryWriteGate.value) ||
      current.phase === 'success'
    )
      return
    const expected = {
      app: current.app,
      fromVersion: current.fromVersion,
      toVersion: current.toVersion
    }
    const confirmation = exactRetryConfirmation(
      expected.app,
      expected.fromVersion,
      expected.toVersion
    )
    current.retryConfirmation = confirmation
    const typed = await promptConfirmation(
      '重新执行升级',
      '系统将重新执行升级，不会把它当作全新安装。请输入下方确认内容。',
      confirmation,
      '确认升级'
    )
    if (typed === null) return
    current.phase = 'retrying'
    current.errorMessage = ''
    try {
      const message = await retryFailedUpgrade({
        appName: expected.app,
        confirmation: typed,
        expected
      })
      current.phase = 'success'
      current.blocked = false
      current.message = message
      ElMessage.success(message)
      await getList(true)
    } catch (error: unknown) {
      clearRecoveryWriteState(current, {
        phase: 'error',
        blocked: false,
        message: FAILED_UPGRADE_FAILURE_MESSAGE
      })
      current.errorMessage = readRecoveryErrorMessage(error, '重新执行升级未完成，请刷新后重试')
    }
  }

  const checkVersionCompatibility = (
    support: string | undefined,
    hostVersion: string | undefined
  ): boolean => {
    if (!support || !hostVersion) return false

    const match = hostVersion.match(
      /^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-([0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*))?(?:\+([0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*))?$/
    )
    if (!match) return false

    const prerelease = match[4]
    if (
      prerelease &&
      prerelease.split('.').some((item) => /^\d+$/.test(item) && !/^(0|[1-9]\d*)$/.test(item))
    ) {
      return false
    }

    const supportTokens = support.split('|')
    if (supportTokens.length === 0 || supportTokens.some((token) => !/^\d+\.x$/.test(token))) {
      return false
    }
    return supportTokens.some((token) => token.slice(0, -2) === match[1])
  }

  const canInstallLocal = (record: SandpackageInstallRow): boolean =>
    record.ordinary_actions_blocked !== true &&
    (record.state === 0 || (record.state === 2 && record.update !== 1))

  const canUninstallLocal = (record: SandpackageInstallRow): boolean =>
    record.state === 1 && record.ordinary_actions_blocked !== true

  const localActionReason = (record: SandpackageInstallRow): string => {
    if (record.state === 1) return '已安装'
    return (
      record.recovery_reason ||
      record.last_error ||
      record.stage_label ||
      '当前状态需要先处理，不能直接安装或卸载'
    )
  }

  const handleInstall = async (record: SandpackageInstallRow) => {
    if (pluginOperationBusy.value || !canInstallLocal(record)) return
    if (rejectOrdinaryAction(record)) return
    // 检查
    if (version.value?.sandpackage_version?.state === 'fail') {
      ElMessage.error('SandPackage 安装器版本检测失败')
      return
    }

    // 检查版本兼容性
    if (!checkVersionCompatibility(record.support, version.value?.sandadmin_version?.describe)) {
      ElMessage.error(
        `此插件仅支持 ${record.support} 版本框架，当前框架版本为 ${version.value?.sandadmin_version?.describe}，不兼容无法安装`
      )
      return
    }
    if (!acquireLocalWrite('install')) return

    try {
      const result = await sandpackageApi.installApp({ appName: record.app })
      if (result.state === 1) {
        ElMessage.success('安装成功')
      } else if (result.state === 4) {
        ElMessage.info('文件已部署，等待完成依赖安装')
      } else {
        ElMessage.warning(result.last_error || result.stage_label || '安装未完成，请查看插件状态')
      }
    } catch {
      // Error already handled by http utility
    } finally {
      await getList()
      releaseLocalWrite('install')
    }
  }

  const handleUninstall = async (record: SandpackageInstallRow) => {
    if (pluginOperationBusy.value || !canUninstallLocal(record)) return
    if (rejectOrdinaryAction(record)) return
    if (!acquireLocalWrite('uninstall')) return
    try {
      await sandpackageApi.uninstallApp({ appName: record.app })
      ElMessage.success('卸载成功')
    } catch {
      // Error already handled by http utility
    } finally {
      await getList()
      releaseLocalWrite('uninstall')
    }
  }

  const parseStrictSemver = (value: string | undefined): RegExpMatchArray | null => {
    if (!value) return null
    const match = value.match(
      /^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-([0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*))?(?:\+([0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*))?$/
    )
    if (!match) return null
    const prerelease = match[4]
    return prerelease &&
      prerelease.split('.').some((part) => /^\d+$/.test(part) && !/^(0|[1-9]\d*)$/.test(part))
      ? null
      : match
  }

  const isStrictSemver = (value: string | undefined): boolean => parseStrictSemver(value) !== null

  const compareNumericIdentifier = (left: string, right: string): number => {
    if (left.length !== right.length) return left.length > right.length ? 1 : -1
    return left === right ? 0 : left > right ? 1 : -1
  }

  const compareStrictSemver = (left: string, right: string): number | null => {
    const leftParts = parseStrictSemver(left)
    const rightParts = parseStrictSemver(right)
    if (!leftParts || !rightParts) return null
    for (const index of [1, 2, 3]) {
      const result = compareNumericIdentifier(leftParts[index], rightParts[index])
      if (result !== 0) return result
    }
    const leftPre = leftParts[4]
    const rightPre = rightParts[4]
    if (!leftPre || !rightPre) return leftPre ? -1 : rightPre ? 1 : 0
    const leftItems = leftPre.split('.')
    const rightItems = rightPre.split('.')
    for (let index = 0; index < Math.min(leftItems.length, rightItems.length); index += 1) {
      const leftItem = leftItems[index]
      const rightItem = rightItems[index]
      if (leftItem === rightItem) continue
      const leftNumeric = /^\d+$/.test(leftItem)
      const rightNumeric = /^\d+$/.test(rightItem)
      if (leftNumeric && rightNumeric) return compareNumericIdentifier(leftItem, rightItem)
      if (leftNumeric) return -1
      if (rightNumeric) return 1
      return leftItem > rightItem ? 1 : -1
    }
    return leftItems.length === rightItems.length
      ? 0
      : leftItems.length > rightItems.length
        ? 1
        : -1
  }

  const hasCurrentAppBackup = (record: SandpackageInstallRow): boolean => {
    const backupId = record.package_backup_id ?? ''
    return (
      record.app !== '' &&
      backupId.startsWith(`${record.app}-package-`) &&
      new RegExp(
        `^${record.app.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}-package-\\d{14}-[a-f0-9]{12}$`
      ).test(backupId)
    )
  }

  const isReadyUpgradeCandidate = (record: SandpackageInstallRow): boolean => {
    if (
      !isUpgradeCandidateStage(record) ||
      !isStrictSemver(record.version) ||
      !isStrictSemver(record.upgrade_from_version) ||
      record.upgrade_from_version === undefined ||
      compareStrictSemver(record.version, record.upgrade_from_version) !== 1
    ) {
      return false
    }
    if (isPostgresqlLifecycleRecord(record)) {
      return record.ordinary_actions_blocked !== true
    }
    return (
      record.upgrade_candidate_verified === true &&
      hasCurrentAppBackup(record) &&
      /^[a-f0-9]{64}$/.test(record.registration_manifest ?? '') &&
      /^[a-f0-9]{64}$/.test(record.runtime_manifest ?? '')
    )
  }

  const isLegacyRecoverableCandidate = (record: SandpackageInstallRow): boolean => {
    return (
      !isPostgresqlLifecycleRecord(record) &&
      isUpgradeCandidateStage(record) &&
      record.legacy_recoverable === true &&
      hasCurrentAppBackup(record) &&
      isStrictSemver(record.version) &&
      isStrictSemver(record.derived_upgrade_from_version) &&
      record.derived_upgrade_from_version !== undefined &&
      compareStrictSemver(record.version, record.derived_upgrade_from_version) === 1
    )
  }

  const isUpgradeCandidateStage = (record: SandpackageInstallRow): boolean => {
    return (
      record.state === 2 &&
      record.update === 1 &&
      (isPostgresqlLifecycleRecord(record) || record.stage === 'ready')
    )
  }

  const isCompatibleUpgradeCandidate = (record: SandpackageInstallRow): boolean => {
    return (
      isReadyUpgradeCandidate(record) &&
      checkVersionCompatibility(record.support, version.value?.sandadmin_version?.describe)
    )
  }

  const handleUpgradeCandidate = async (record: SandpackageInstallRow) => {
    if (pluginOperationBusy.value) return
    if (rejectOrdinaryAction(record)) return
    if (!checkVersionCompatibility(record.support, version.value?.sandadmin_version?.describe)) {
      ElMessage.error(
        isPostgresqlLifecycleRecord(record)
          ? '升级候选与当前宿主版本不兼容，不能升级'
          : '升级候选与当前宿主版本不兼容，不能升级；如无需升级可撤回候选'
      )
      return
    }
    const expected = `UPGRADE ${record.app}@${record.upgrade_from_version}->${record.version}`
    let confirmation = ''
    try {
      const result = await ElMessageBox.prompt(
        '系统将仅执行升级脚本 update.sql；不会执行 install.sql。请输入下方确认内容。',
        '确认升级候选',
        {
          confirmButtonText: '确认升级',
          cancelButtonText: '取消',
          inputPlaceholder: expected,
          inputValidator: (value: string) => value === expected || '确认内容不匹配'
        }
      )
      confirmation = result.value
    } catch {
      return
    }
    if (!acquireLocalWrite('upgrade')) return
    try {
      await sandpackageApi.installApp({ appName: record.app, confirmation })
      ElMessage.success('升级已提交执行')
    } catch {
      // Error already handled by http utility
    } finally {
      await getList()
      releaseLocalWrite('upgrade')
    }
  }

  const handleDiscardCandidate = async (record: SandpackageInstallRow) => {
    if (pluginOperationBusy.value) return
    if (isPostgresqlLifecycleRecord(record)) return
    if (rejectOrdinaryAction(record)) return
    const expected = `DISCARD ${record.app}@${record.version}`
    let confirmation = ''
    try {
      const result = await ElMessageBox.prompt(
        '系统将仅恢复已验证的旧插件注册包并隔离当前候选；不会执行数据库回滚，因为候选尚未执行。请输入下方确认内容。',
        '撤回升级候选',
        {
          confirmButtonText: '撤回候选',
          cancelButtonText: '取消',
          inputPlaceholder: expected,
          inputValidator: (value: string) => value === expected || '确认内容不匹配'
        }
      )
      confirmation = result.value
    } catch {
      return
    }
    if (!acquireLocalWrite('discard')) return
    try {
      await sandpackageApi.discardCandidate({
        appName: record.app,
        confirmation
      })
      ElMessage.success('升级候选已撤回，旧插件注册包已恢复；数据库未执行无需回滚')
    } catch {
      // Error already handled by http utility
    } finally {
      await getList()
      releaseLocalWrite('discard')
    }
  }

  const handleRegisterExisting = async (record: SandpackageInstallRow) => {
    if (pluginOperationBusy.value) return
    if (rejectOrdinaryAction(record)) return
    const expected = `REGISTER ${record.app}@${record.version}`
    let confirmation = ''
    try {
      const result = await ElMessageBox.prompt(
        '系统将核对安装包、运行插件元数据，并逐文件校验后端和前端内容；不会复制文件、执行数据库脚本或运行插件代码。请输入下方确认内容。',
        '登记现有插件',
        {
          confirmButtonText: '核验并登记',
          cancelButtonText: '取消',
          inputPlaceholder: expected,
          inputValidator: (value: string) => value === expected || '确认内容不匹配'
        }
      )
      confirmation = result.value
    } catch {
      return
    }

    if (!acquireLocalWrite('register')) return
    try {
      await sandpackageApi.registerExisting({
        appName: record.app,
        confirmation
      })
      ElMessage.success('插件登记完成')
    } catch {
      // Error already handled by http utility
    } finally {
      await getList()
      releaseLocalWrite('register')
    }
  }

  const stateText = (record: SandpackageInstallRow): string => {
    if (isLegacyFailedUpgradeRecovery(record)) return '升级未完成'
    if (record.state_text) return record.state_text
    return (
      {
        0: '未安装',
        1: '已安装',
        2: '等待安装',
        3: '等待处理冲突',
        4: '等待依赖安装',
        5: '安装目录被占用',
        6: '发现未登记部署',
        7: '部署文件不一致',
        8: '操作未完成'
      }[record.state] || '状态未知'
    )
  }

  const stateTagType = (
    record: SandpackageInstallRow
  ): 'success' | 'warning' | 'danger' | 'primary' | 'info' => {
    if (record.state === 1) return 'success'
    if (record.state === 2 || record.state === 6) return 'primary'
    if (record.state === 3 || record.state === 4) return 'warning'
    if (record.state === 0 || record.state === 5 || record.state === 7 || record.state === 8)
      return 'danger'
    return 'info'
  }

  const handleExecFront = (record: SandpackageInstallRow) => {
    if (pluginOperationBusy.value) return
    if (rejectOrdinaryAction(record)) return
    const extend = 'module-install:' + record.app
    terminalLaunchPending.value = true
    terminalRef.value?.open()
    setTimeout(() => {
      try {
        terminalRef.value?.frontInstall(extend)
      } finally {
        terminalLaunchPending.value = false
      }
    }, 500)
  }

  const handleExecBackend = (record: SandpackageInstallRow) => {
    if (pluginOperationBusy.value) return
    if (rejectOrdinaryAction(record)) return
    const extend = 'module-install:' + record.app
    terminalLaunchPending.value = true
    terminalRef.value?.open()
    setTimeout(() => {
      try {
        terminalRef.value?.backendInstall(extend)
      } finally {
        terminalLaunchPending.value = false
      }
    }, 500)
  }

  const handleTerminal = () => {
    if (pluginOperationBusy.value) return
    terminalRef.value?.open()
  }

  const columns: ColumnOption[] = [
    { prop: 'app', label: '插件标识', width: 120, useSlot: true },
    { prop: 'title', label: '插件名称', width: 150 },
    { prop: 'about', label: '插件描述', showOverflowTooltip: true },
    { prop: 'author', label: '作者', width: 120 },
    { prop: 'version', label: '版本', width: 100 },
    { prop: 'support', label: '框架兼容', width: 120, align: 'center' },
    { prop: 'state', label: '插件状态', width: 100, useSlot: true },
    { prop: 'npm', label: '前端依赖', width: 100, useSlot: true },
    { prop: 'composer', label: '后端依赖', width: 100, useSlot: true },
    {
      prop: 'operation',
      label: '操作',
      width: 220,
      fixed: 'right',
      useSlot: true
    }
  ]

  async function getList(preserveRecoverySession = false): Promise<void> {
    loading.value = true
    listError.value = ''
    if (!preserveRecoverySession) lockAllRecoveryWrites()
    try {
      const resp = await sandpackageApi.getAppList()
      installList.value = resp?.data || []
      version.value = resp?.version || {}
      syncFailedUpgradeSessions(installList.value)
    } catch (error: unknown) {
      installList.value = []
      for (const app of Object.keys(recoverySessions)) {
        delete recoverySessions[app]
      }
      recoveryFiles.clear()
      listError.value = readRecoveryErrorMessage(error, FAILED_UPGRADE_LIST_ERROR_MESSAGE)
    } finally {
      loading.value = false
    }
  }

  // ========== 插件仓库相关 ==========
  const repositoryCatalog = ref<RepositoryCatalog | null>(null)
  const repositoryLoading = ref(false)
  const repositoryLoaded = ref(false)
  const repositoryError = ref('')
  const repositoryKeyword = ref('')
  const repositoryVersionVisible = ref(false)
  const currentRepositoryPlugin = ref<RepositoryPlugin | null>(null)
  const downloadingKey = ref('')
  const repositoryActionError = ref('')
  const repositoryDocumentVisible = ref(false)
  const repositoryDocumentLoading = ref(false)
  const repositoryDocumentError = ref('')
  const repositoryDocumentMarkdown = ref('')
  const repositoryDocumentTarget = ref<RepositoryDocumentTarget | null>(null)
  let repositoryRequestId = 0
  let repositoryDocumentRequestId = 0

  const repositoryPlugins = computed(() => repositoryCatalog.value?.plugins ?? [])
  const filteredRepositoryPlugins = computed(() => {
    const keyword = repositoryKeyword.value.trim().toLocaleLowerCase()
    if (!keyword) return repositoryPlugins.value
    return repositoryPlugins.value.filter((item) =>
      [item.app, item.title, item.about, item.author].some((value) =>
        value.toLocaleLowerCase().includes(keyword)
      )
    )
  })
  const repositoryDownloading = computed(() => downloadingKey.value !== '')
  const localWriteBusy = computed(() => localWriteOwner.value !== '')
  const recoveryOperationBusy = computed(() =>
    Object.values(recoverySessions).some(
      (session) => session !== undefined && isRecoveryWriteBusy(session.phase)
    )
  )
  const terminalOperationBusy = computed(
    () =>
      terminalLaunchPending.value ||
      terminalStore.taskList.some(
        (task) =>
          task.status === TaskStatus.WAITING ||
          task.status === TaskStatus.CONNECTING ||
          task.status === TaskStatus.RUNNING
      )
  )
  const pluginOperationBusy = computed(
    () =>
      repositoryDownloading.value ||
      localWriteBusy.value ||
      recoveryOperationBusy.value ||
      terminalOperationBusy.value
  )
  const repositoryWritesBlocked = computed(
    () => hideGlobalPluginWrites.value || pluginOperationBusy.value
  )
  const repositoryDocumentTitle = computed(() => {
    const target = repositoryDocumentTarget.value
    return target ? `${target.title} v${target.version}` : '插件文档'
  })

  const fetchRepositoryCatalog = async (): Promise<void> => {
    const requestId = ++repositoryRequestId
    repositoryLoading.value = true
    repositoryError.value = ''
    try {
      const response = await sandpackageApi.getRepositoryCatalog()
      if (requestId !== repositoryRequestId) return
      repositoryCatalog.value = response
      repositoryLoaded.value = true
    } catch (error: unknown) {
      if (requestId !== repositoryRequestId) return
      repositoryCatalog.value = null
      repositoryLoaded.value = true
      repositoryError.value = readRecoveryErrorMessage(error, '插件仓库读取失败，请稍后重试')
    } finally {
      if (requestId === repositoryRequestId) repositoryLoading.value = false
    }
  }

  const showRepositoryVersions = (item: RepositoryPlugin): void => {
    currentRepositoryPlugin.value = item
    repositoryActionError.value = ''
    repositoryVersionVisible.value = true
  }

  const repositoryVersionKey = (
    plugin: RepositoryPlugin | null,
    item: RepositoryPluginVersion
  ): string => (plugin ? `${plugin.app}@${item.version}` : '')

  const repositoryLocalLabel = (local: RepositoryPluginLocal): string => {
    if (local.state === 0) return '未安装'
    if (local.state === 1) return `已安装 ${local.installed_version || local.version || ''}`.trim()
    if (local.state === 2) return '已有待处理候选'
    if (local.state === 7) return '安装文件异常'
    return '需要管理'
  }

  const repositoryLocalTagType = (
    local: RepositoryPluginLocal
  ): 'success' | 'warning' | 'danger' | 'info' => {
    if (local.state === 1 && !local.blocked) return 'success'
    if (local.state === 0 && !local.blocked) return 'info'
    return local.blocked ? 'danger' : 'warning'
  }

  const repositoryActionLabel = (action: RepositoryVersionAction): string =>
    ({
      install: '直接安装',
      upgrade: '直接升级',
      installed: '当前已安装',
      downgrade: '不支持降级',
      manage: '去插件管理',
      incompatible: '版本不兼容'
    })[action]

  const repositoryActionType = (
    action: RepositoryVersionAction
  ): 'primary' | 'warning' | 'info' => {
    if (action === 'install' || action === 'upgrade') return 'primary'
    return action === 'manage' ? 'warning' : 'info'
  }

  const repositoryActionDisabled = (
    plugin: RepositoryPlugin | null,
    item: RepositoryPluginVersion
  ): boolean => {
    if (item.action === 'manage') return pluginOperationBusy.value
    if (item.action !== 'install' && item.action !== 'upgrade') return true
    return !plugin || plugin.local.blocked || repositoryWritesBlocked.value
  }

  const goToPluginManagement = (): void => {
    repositoryVersionVisible.value = false
    activeTab.value = 'local'
  }

  const readRepositoryError = (error: unknown, fallback: string): string => {
    if (error instanceof Error && error.message.trim() !== '') return error.message
    return fallback
  }

  const findRepositorySelection = (
    app: string,
    version: string
  ): { plugin: RepositoryPlugin; item: RepositoryPluginVersion } | null => {
    const plugin = repositoryCatalog.value?.plugins.find((candidate) => candidate.app === app)
    const item = plugin?.versions.find((candidate) => candidate.version === version)
    return plugin && item ? { plugin, item } : null
  }

  const validatePreparedCandidate = (
    prepared: SandpackageInstallRow,
    app: string,
    targetVersion: string,
    action: 'install' | 'upgrade',
    fromVersion: string
  ): void => {
    if (
      prepared.app !== app ||
      prepared.version !== targetVersion ||
      prepared.state !== 2 ||
      prepared.ordinary_actions_blocked === true
    ) {
      throw new Error('下载候选与所选插件身份或可执行状态不一致，请到插件管理查看')
    }
    if (
      action === 'upgrade' &&
      (prepared.update !== 1 || prepared.upgrade_from_version !== fromVersion)
    ) {
      throw new Error('升级候选的来源版本与当前已安装版本不一致，请刷新后重试')
    }
    if (
      action === 'install' &&
      (prepared.update === 1 || (prepared.upgrade_from_version ?? '') !== '')
    ) {
      throw new Error('安装候选被识别为升级候选，已停止后续安装')
    }
  }

  const handleRepositoryVersionAction = async (
    plugin: RepositoryPlugin | null,
    selectedItem: RepositoryPluginVersion
  ): Promise<void> => {
    if (!plugin) return
    if (selectedItem.action === 'manage') {
      if (pluginOperationBusy.value) return
      goToPluginManagement()
      return
    }
    if (
      (selectedItem.action !== 'install' && selectedItem.action !== 'upgrade') ||
      pluginOperationBusy.value ||
      hideGlobalPluginWrites.value
    ) {
      return
    }

    const selectedAction = selectedItem.action
    const selectedSha256 = selectedItem.sha256
    const key = repositoryVersionKey(plugin, selectedItem)
    downloadingKey.value = key
    repositoryActionError.value = ''
    try {
      await Promise.all([getList(), fetchRepositoryCatalog()])
      if (listError.value !== '' || repositoryError.value !== '') {
        throw new Error('插件状态刷新失败，未执行下载或安装')
      }
      if (hideGlobalPluginWrites.value) {
        throw new Error('当前存在待恢复或无法确认的本地状态，请先到插件管理处理')
      }

      const refreshed = findRepositorySelection(plugin.app, selectedItem.version)
      if (
        !refreshed ||
        refreshed.item.sha256 !== selectedSha256 ||
        refreshed.item.action !== selectedAction
      ) {
        throw new Error('仓库版本或可执行动作已变化，请重新选择')
      }
      currentRepositoryPlugin.value = refreshed.plugin
      if (refreshed.plugin.local.blocked) {
        throw new Error(refreshed.plugin.local.reason || '本地插件状态异常，请到插件管理处理')
      }
      const refreshedLocalRow = installList.value.find((row) => row.app === refreshed.plugin.app)
      if (refreshedLocalRow?.ordinary_actions_blocked === true) {
        throw new Error(
          refreshedLocalRow.recovery_reason ||
            refreshedLocalRow.last_error ||
            '本地插件状态禁止常规安装或升级，请到插件管理处理'
        )
      }

      const fromVersion =
        selectedAction === 'upgrade' ? refreshed.plugin.local.installed_version || '' : ''
      if (selectedAction === 'upgrade' && fromVersion === '') {
        throw new Error('无法确认当前已安装版本，未执行升级')
      }
      if (
        selectedAction === 'upgrade' &&
        (refreshedLocalRow?.state !== 1 || refreshedLocalRow.version !== fromVersion)
      ) {
        throw new Error('本地插件列表与仓库识别的已安装版本不一致，未执行升级')
      }

      try {
        await ElMessageBox.confirm(
          selectedAction === 'upgrade'
            ? `确认将 ${refreshed.plugin.title} 从 ${fromVersion} 升级到 ${refreshed.item.version}？`
            : `确认安装 ${refreshed.plugin.title} v${refreshed.item.version}？`,
          selectedAction === 'upgrade' ? '确认直接升级' : '确认直接安装',
          {
            confirmButtonText: selectedAction === 'upgrade' ? '确认升级' : '确认安装',
            cancelButtonText: '取消',
            type: 'warning'
          }
        )
      } catch {
        return
      }

      const prepared = await sandpackageApi.downloadRepositoryPlugin({
        app: refreshed.plugin.app,
        version: refreshed.item.version,
        sha256: refreshed.item.sha256
      })
      validatePreparedCandidate(
        prepared,
        refreshed.plugin.app,
        refreshed.item.version,
        selectedAction,
        fromVersion
      )

      await sandpackageApi.installApp({
        appName: refreshed.plugin.app,
        confirmation:
          selectedAction === 'upgrade'
            ? `UPGRADE ${refreshed.plugin.app}@${fromVersion}->${refreshed.item.version}`
            : undefined
      })
      await Promise.all([getList(), fetchRepositoryCatalog()])

      const installed = installList.value.find((row) => row.app === refreshed.plugin.app)
      const refreshedAfterInstall = findRepositorySelection(
        refreshed.plugin.app,
        refreshed.item.version
      )
      if (
        installed?.state === 1 &&
        installed.version === refreshed.item.version &&
        installed.ordinary_actions_blocked !== true &&
        refreshedAfterInstall?.plugin.local.state === 1 &&
        refreshedAfterInstall.plugin.local.installed_version === refreshed.item.version
      ) {
        repositoryVersionVisible.value = false
        currentRepositoryPlugin.value = null
        ElMessage.success(selectedAction === 'upgrade' ? '插件升级成功' : '插件安装成功')
        return
      }

      const pendingReason =
        installed?.recovery_reason ||
        installed?.last_error ||
        installed?.stage_label ||
        refreshedAfterInstall?.plugin.local.reason ||
        '操作已提交，但插件尚未进入已安装状态，请到插件管理继续处理'
      repositoryActionError.value = pendingReason
      ElMessage.warning('插件尚未确认安装完成，请到插件管理查看')
    } catch (error: unknown) {
      repositoryActionError.value = readRepositoryError(error, '插件操作未完成，请到插件管理查看')
    } finally {
      downloadingKey.value = ''
    }
  }

  const loadRepositoryDocument = async (target: RepositoryDocumentTarget): Promise<void> => {
    const requestId = ++repositoryDocumentRequestId
    repositoryDocumentLoading.value = true
    repositoryDocumentError.value = ''
    repositoryDocumentMarkdown.value = ''
    try {
      const response = await sandpackageApi.getRepositoryDocument({
        app: target.app,
        version: target.version,
        sha256: target.sha256
      })
      if (requestId !== repositoryDocumentRequestId) return
      if (response.app !== target.app || response.version !== target.version) {
        throw new Error('文档响应与所选插件版本不一致')
      }
      repositoryDocumentMarkdown.value = response.markdown.trim() === '' ? '' : response.markdown
    } catch (error: unknown) {
      if (requestId !== repositoryDocumentRequestId) return
      repositoryDocumentError.value = readRepositoryError(error, '插件文档读取失败，请稍后重试')
    } finally {
      if (requestId === repositoryDocumentRequestId) repositoryDocumentLoading.value = false
    }
  }

  const openRepositoryDocument = (
    plugin: RepositoryPlugin | null,
    item: RepositoryPluginVersion
  ): void => {
    if (!plugin) return
    const target: RepositoryDocumentTarget = {
      app: plugin.app,
      title: plugin.title,
      version: item.version,
      sha256: item.sha256
    }
    repositoryDocumentTarget.value = target
    repositoryDocumentVisible.value = true
    loadRepositoryDocument(target)
  }

  const retryRepositoryDocument = (): void => {
    if (repositoryDocumentTarget.value) loadRepositoryDocument(repositoryDocumentTarget.value)
  }

  const closeRepositoryDocument = (): void => {
    repositoryDocumentRequestId += 1
    repositoryDocumentLoading.value = false
    repositoryDocumentError.value = ''
    repositoryDocumentMarkdown.value = ''
    repositoryDocumentTarget.value = null
  }

  // 监听 tab 切换
  watch(activeTab, (val) => {
    if (val === 'repository' && !repositoryLoaded.value && !repositoryLoading.value) {
      fetchRepositoryCatalog()
    }
  })

  onMounted(() => {
    getList()
  })
</script>

<style lang="scss" scoped>
  .sandpackage-page {
    box-sizing: border-box;
    min-height: var(--art-full-height, 100%);
    padding-bottom: 24px;
  }

  .sandpackage-page-card {
    width: 100%;
  }

  .sandpackage-page-card :deep(.el-card__body) {
    height: auto;
    overflow: visible;
  }

  .failed-upgrade-card {
    padding: 16px;
    margin-top: 16px;
    background: var(--el-bg-color);
    border: 1px solid var(--el-border-color);
    border-radius: 8px;
  }

  .failed-upgrade-steps {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    margin: 12px 0 8px;
    font-size: 14px;
    color: var(--el-text-color-regular);
  }

  .failed-upgrade-steps .current {
    font-weight: 600;
    color: var(--el-color-primary);
  }

  .step-arrow {
    color: var(--el-text-color-secondary);
  }

  .failed-upgrade-meta,
  .failed-upgrade-op-hint,
  .failed-upgrade-upload-tip {
    font-size: 13px;
    line-height: 1.5;
    color: var(--el-text-color-secondary);
  }

  .failed-upgrade-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: flex-start;
    margin-top: 16px;
  }

  .failed-upgrade-error-row {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
  }

  .version-title {
    padding: 5px 10px;
    font-size: 12px;
    background: var(--el-fill-color-light);
    border: 1px solid var(--el-border-color);
  }

  .version-value {
    padding: 5px 10px;
    font-size: 12px;
    border: 1px solid var(--el-border-color);
  }

  .repository-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
    margin-bottom: 16px;
  }

  .repository-search {
    width: min(360px, 100%);
  }

  .repository-source {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-left: auto;
    font-size: 13px;
    color: var(--el-text-color-secondary);
  }

  .repository-state {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 160px;
    color: var(--el-text-color-secondary);
  }

  .repository-error-row {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
  }

  .app-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(320px, 100%), 1fr));
    gap: 16px;
  }

  .app-card {
    padding: 16px;
    background: var(--el-bg-color);
    border: 1px solid var(--el-border-color);
    border-radius: 8px;
    transition: all 0.3s ease;

    &:hover {
      box-shadow: var(--el-box-shadow-light);
      transform: translateY(-2px);
    }
  }

  .app-card-header {
    display: flex;
    gap: 12px;
    align-items: center;
    margin-bottom: 12px;
  }

  .repository-plugin-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    font-size: 24px;
    color: var(--el-color-primary);
    background: var(--el-fill-color-light);
    border-radius: 8px;
  }

  .app-info {
    flex: 1;
  }

  .app-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--el-text-color-primary);
  }

  .app-version {
    font-size: 12px;
    color: var(--el-text-color-secondary);
  }

  .app-about {
    display: -webkit-box;
    margin-bottom: 12px;
    overflow: hidden;
    font-size: 13px;
    line-height: 1.5;
    color: var(--el-text-color-regular);
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
  }

  .repository-local-alert {
    margin-bottom: 12px;
  }

  .app-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 12px;
    color: var(--el-text-color-secondary);
  }

  @media (width <= 768px) {
    .failed-upgrade-card,
    .failed-upgrade-actions {
      width: 100%;
    }

    .failed-upgrade-steps {
      font-size: 13px;
    }

    .version-item {
      flex-direction: column;
      align-items: flex-start;
    }

    .repository-source {
      width: 100%;
      margin-left: 0;
    }
  }

  .version-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .version-item {
    display: flex;
    gap: 12px;
    align-items: center;
    justify-content: space-between;
    padding: 12px;
    background: var(--el-fill-color-light);
    border-radius: 6px;
  }

  .version-info-row {
    display: flex;
    gap: 12px;
    align-items: center;
  }

  .version-name {
    font-weight: 600;
    color: var(--el-text-color-primary);
  }

  .version-compatibility {
    margin-top: 6px;
    font-size: 12px;
    color: var(--el-text-color-secondary);
  }

  .version-remark {
    flex: 1;
    font-size: 13px;
    color: var(--el-text-color-regular);
  }

  .version-action-reason {
    margin-top: 6px;
    font-size: 12px;
    line-height: 1.5;
    color: var(--el-text-color-secondary);
  }

  .repository-document {
    min-height: 160px;
    padding: 16px;
    margin: 0;
    overflow: auto;
    font-family: var(--el-font-family);
    font-size: 14px;
    line-height: 1.7;
    color: var(--el-text-color-primary);
    overflow-wrap: anywhere;
    white-space: pre-wrap;
    user-select: text;
    background: var(--el-fill-color-light);
    border: 1px solid var(--el-border-color);
    border-radius: 8px;
  }
</style>
