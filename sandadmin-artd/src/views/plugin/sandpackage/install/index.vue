<template>
  <div class="sandpackage-page">
    <ElCard class="sandpackage-page-card" shadow="never">
      <!-- 提示警告 -->
      <ElAlert type="warning" :closable="false">
        仅支持上传由插件市场下载的zip压缩包进行安装，请您务必确认插件包文件来自官方渠道或经由官方认证的插件作者！
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
          <ElButton :disabled="loading || !!listError" @click="getList()">刷新</ElButton>
          <ElButton
            type="primary"
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
          <ElButton :disabled="loading || isRecoveryBusy(row)" @click="getList()">刷新</ElButton>
          <ElButton
            :disabled="loading || !!listError"
            :loading="sessionOf(row).phase === 'diagnosing'"
            @click="handleInspectRecovery(row)"
          >
            诊断恢复状态
          </ElButton>
        </div>
        <div v-else class="failed-upgrade-actions" v-loading="isRecoveryBusy(row)">
          <ElButton :disabled="loading || isRecoveryBusy(row)" @click="getList()">刷新</ElButton>
          <template
            v-if="sessionOf(row).phase === 'needs_prepare' || sessionOf(row).phase === 'preparing'"
          >
            <ElUpload
              accept=".zip"
              :auto-upload="false"
              :limit="1"
              :show-file-list="true"
              :disabled="isRecoveryBusy(row)"
              :on-change="(file) => onRecoveryFileChange(row, file)"
              :on-remove="() => onRecoveryFileRemove(row)"
            >
              <ElButton :disabled="isRecoveryBusy(row)">选择 ZIP 插件包</ElButton>
              <template #tip>
                <div class="failed-upgrade-upload-tip"> 仅接受 ZIP，且不超过 5MB </div>
              </template>
            </ElUpload>
            <ElButton
              type="primary"
              :disabled="isRecoveryBusy(row) || sessionOf(row).selectedFileName === ''"
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
            :loading="sessionOf(row).phase === 'verifying'"
            @click="handleVerifyRecovery(row)"
          >
            只读 Gate A 核验
          </ElButton>
          <ElButton
            v-if="sessionOf(row).phase === 'retry_safe'"
            type="primary"
            :loading="sessionOf(row).phase === 'replacing'"
            @click="handleReplaceRecoveryCandidate(row)"
          >
            替换已核验候选
          </ElButton>
          <ElButton
            v-if="recoveryStep(row) === 3 && sessionOf(row).phase !== 'success'"
            type="primary"
            :loading="sessionOf(row).phase === 'retrying'"
            @click="handleRetryRecovery(row)"
          >
            重新执行升级
          </ElButton>
        </div>
      </section>

      <!-- 工具栏 -->
      <div class="flex flex-wrap items-center my-2 gap-2">
        <ElButton @click="getList()" v-ripple :loading="loading">
          <template #icon>
            <ArtSvgIcon icon="ri:refresh-line" />
          </template>
        </ElButton>
        <ElButton v-if="!hideGlobalPluginWrites" @click="handleUpload" v-ripple>
          <template #icon>
            <ArtSvgIcon icon="ri:upload-line" />
          </template>
          上传插件包
        </ElButton>
        <ElButton v-if="!hideGlobalPluginWrites" type="danger" @click="handleTerminal" v-ripple>
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
        <!-- 本地安装 Tab -->
        <ElTabPane label="本地安装" name="local">
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
              <ElTooltip v-if="isFailedUpgradeRecovery(row)" placement="top">
                <template #content>
                  <div>{{ recoveryReason(row) }}</div>
                </template>
                <ElTag type="danger">{{ stateText(row) }}</ElTag>
              </ElTooltip>
              <ElTooltip v-else-if="row.stage_label || row.last_error" placement="top">
                <template #content>
                  <div>{{ row.stage_label }}</div>
                  <div v-if="row.last_error" class="mt-1">
                    {{ row.last_error }}
                  </div>
                </template>
                <ElTag :type="stateTagType(row)">{{ stateText(row) }}</ElTag>
              </ElTooltip>
              <ElTag v-else :type="stateTagType(row)">{{ stateText(row) }}</ElTag>
            </template>

            <!-- 前端依赖列 -->
            <template #npm="{ row }">
              <ElTag v-if="isFailedUpgradeRecovery(row)" type="info">-</ElTag>
              <ElLink
                v-else-if="row.npm_dependent_wait_install === 1"
                type="primary"
                @click="handleExecFront(row)"
              >
                <ArtSvgIcon icon="ri:download-line" class="mr-1" />点击安装
              </ElLink>
              <ElTag v-else-if="row.state === 2" type="info">-</ElTag>
              <ElTag v-else type="success">已安装</ElTag>
            </template>

            <!-- 后端依赖列 -->
            <template #composer="{ row }">
              <ElTag v-if="isFailedUpgradeRecovery(row)" type="info">-</ElTag>
              <ElLink
                v-else-if="row.composer_dependent_wait_install === 1"
                type="primary"
                @click="handleExecBackend(row)"
              >
                <ArtSvgIcon icon="ri:download-line" class="mr-1" />点击安装
              </ElLink>
              <ElTag v-else-if="row.state === 2" type="info">-</ElTag>
              <ElTag v-else type="success">已安装</ElTag>
            </template>

            <!-- 操作列 -->
            <template #operation="{ row }">
              <span v-if="isFailedUpgradeRecovery(row)" class="failed-upgrade-op-hint">
                请按上方恢复流程处理
              </span>
              <ElSpace v-else wrap>
                <ElLink
                  v-if="row.registration_candidate === 1"
                  type="primary"
                  @click="handleRegisterExisting(row)"
                >
                  <ArtSvgIcon icon="ri:shield-check-line" class="mr-1" />登记现有插件
                </ElLink>
                <template v-else-if="isCompatibleUpgradeCandidate(row)">
                  <ElLink type="primary" @click="handleUpgradeCandidate(row)">
                    <ArtSvgIcon icon="ri:arrow-up-circle-line" class="mr-1" />确认升级
                  </ElLink>
                  <ElLink type="warning" @click="handleDiscardCandidate(row)">
                    <ArtSvgIcon icon="ri:arrow-go-back-line" class="mr-1" />撤回候选
                  </ElLink>
                </template>
                <template v-else-if="isLegacyRecoverableCandidate(row)">
                  <ElTag type="warning">这是较早版本上传的候选，请先撤回后重新上传</ElTag>
                  <ElLink type="warning" @click="handleDiscardCandidate(row)">
                    <ArtSvgIcon icon="ri:arrow-go-back-line" class="mr-1" />撤回候选
                  </ElLink>
                </template>
                <template v-else-if="isReadyUpgradeCandidate(row)">
                  <ElTag type="warning">与当前宿主版本不兼容，仅可撤回候选</ElTag>
                  <ElLink type="warning" @click="handleDiscardCandidate(row)">
                    <ArtSvgIcon icon="ri:arrow-go-back-line" class="mr-1" />撤回候选
                  </ElLink>
                </template>
                <ElTag v-else-if="isUpgradeCandidateStage(row)" type="danger"
                  >候选不完整，请联系管理员</ElTag
                >
                <ElPopconfirm
                  v-else
                  title="确定要安装当前插件吗?"
                  @confirm="handleInstall(row)"
                  confirm-button-text="确定"
                  cancel-button-text="取消"
                >
                  <template #reference>
                    <ElLink type="warning">
                      <ArtSvgIcon icon="ri:apps-2-add-line" class="mr-1" />安装
                    </ElLink>
                  </template>
                </ElPopconfirm>
                <ElPopconfirm
                  v-if="!isUpgradeCandidateStage(row)"
                  title="确定要卸载当前插件吗?"
                  @confirm="handleUninstall(row)"
                  confirm-button-text="确定"
                  cancel-button-text="取消"
                >
                  <template #reference>
                    <ElLink type="danger">
                      <ArtSvgIcon icon="ri:delete-bin-5-line" class="mr-1" />卸载
                    </ElLink>
                  </template>
                </ElPopconfirm>
              </ElSpace>
            </template>
          </ArtTable>
        </ElTabPane>

        <!-- 在线商店 Tab -->
        <ElTabPane label="在线商店" name="online">
          <!-- 搜索栏 -->
          <div class="flex flex-wrap items-center gap-4 mb-4">
            <ElInput
              v-model="searchForm.keywords"
              placeholder="请输入关键词"
              clearable
              class="!w-48"
              @keyup.enter="fetchOnlineApps"
            >
              <template #prefix>
                <ArtSvgIcon icon="ri:search-line" />
              </template>
            </ElInput>
            <ElSelect v-model="searchForm.type" placeholder="类型" clearable class="!w-32">
              <ElOption label="全部" value="" />
              <ElOption label="插件" :value="1" />
              <ElOption label="系统" :value="2" />
              <ElOption label="组件" :value="3" />
              <ElOption label="项目" :value="4" />
            </ElSelect>
            <ElSelect v-model="searchForm.price" placeholder="价格" class="!w-32">
              <ElOption label="全部" value="all" />
              <ElOption label="免费" value="free" />
              <ElOption label="付费" value="paid" />
            </ElSelect>
            <ElButton type="primary" @click="fetchOnlineApps">搜索</ElButton>

            <!-- 商店账号 -->
            <div class="ml-auto flex items-center gap-2">
              <template v-if="storeUser">
                <ElAvatar :size="24">
                  <img v-if="storeUser.avatar" :src="storeUser.avatar" />
                  <ArtSvgIcon v-else icon="ri:user-line" />
                </ElAvatar>
                <span class="font-medium">{{ storeUser.nickname || storeUser.username }}</span>
                <ElButton size="small" @click="showPurchasedApps">已购应用</ElButton>
                <ElButton size="small" @click="handleLogout">退出</ElButton>
              </template>
              <template v-else>
                <ElButton size="small" @click="handleLogin">登录</ElButton>
                <ElButton size="small" @click="handleRegister">注册</ElButton>
                <span class="text-sm text-gray-400">来管理已购插件</span>
              </template>
            </div>
          </div>

          <!-- 应用网格 -->
          <div class="app-grid">
            <div
              v-for="item in onlineApps"
              :key="item.id"
              class="app-card"
              @click="showDetail(item)"
            >
              <div class="app-card-header">
                <img :src="item.logo" :alt="item.title" class="app-logo" />
                <div class="app-info">
                  <div class="app-title">{{ item.title }}</div>
                  <div class="app-version">v{{ item.version }}</div>
                </div>
                <div class="app-price" :class="{ free: item.price === '0.00' }">
                  {{ item.price === '0.00' ? '免费' : '¥' + item.price }}
                </div>
              </div>
              <div class="app-about">{{ item.about }}</div>
              <div class="app-footer">
                <div class="app-author">
                  <img
                    :src="item.avatar || 'https://via.placeholder.com/24'"
                    class="author-avatar"
                  />
                  <span>{{ item.username }}</span>
                </div>
                <div class="app-sales">
                  <ArtSvgIcon icon="ri:user-line" class="mr-1" />
                  {{ item.sales_num }} 销量
                </div>
              </div>
            </div>
          </div>

          <!-- 分页 -->
          <div class="flex justify-center mt-4">
            <ElPagination
              v-model:current-page="onlinePagination.current"
              v-model:page-size="onlinePagination.size"
              :total="onlinePagination.total"
              :page-sizes="[12, 24, 48]"
              layout="total, prev, pager, next, sizes"
              @size-change="fetchOnlineApps"
              @current-change="fetchOnlineApps"
            />
          </div>
        </ElTabPane>
      </ElTabs>
    </ElCard>

    <!-- 上传插件弹窗 -->
    <InstallForm ref="installFormRef" @success="getList" />

    <!-- 终端弹窗 -->
    <TerminalBox ref="terminalRef" @success="getList" />

    <!-- 详情抽屉 -->
    <ElDrawer v-model="detailVisible" :size="600" :with-header="true">
      <template #header>
        <div class="flex items-center gap-3">
          <img :src="currentApp?.logo" class="w-9 h-9 rounded-lg" />
          <div>
            <div class="text-lg font-semibold">{{ currentApp?.title }}</div>
            <div class="text-xs text-gray-400">
              v{{ currentApp?.version }} · {{ currentApp?.username }}
            </div>
          </div>
        </div>
      </template>
      <div class="detail-content">
        <div class="detail-price" :class="{ free: currentApp?.price === '0.00' }">
          {{ currentApp?.price === '0.00' ? '免费' : '¥' + currentApp?.price }}
        </div>
        <div class="detail-about">{{ currentApp?.about }}</div>

        <!-- 截图预览 -->
        <div v-if="currentApp?.screenshots?.length" class="mb-6">
          <div class="text-base font-semibold mb-3">截图预览</div>
          <ElSpace wrap :size="12">
            <ElImage
              v-for="(img, idx) in currentApp?.screenshots"
              :key="idx"
              :src="img"
              :preview-src-list="currentApp?.screenshots"
              :preview-teleported="true"
              fit="cover"
              class="w-36 h-24 rounded-lg cursor-pointer"
            />
          </ElSpace>
        </div>

        <!-- 详情描述 -->
        <div class="detail-desc">
          <div class="text-base font-semibold mb-3">详细介绍</div>
          <div class="desc-content" v-html="renderMarkdown(currentApp?.content)"></div>
        </div>

        <!-- 购买按钮 -->
        <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
          <ElButton type="primary" size="large" class="w-full" @click="handleBuy">
            <template #icon>
              <ArtSvgIcon icon="ri:shopping-cart-line" />
            </template>
            前往购买
          </ElButton>
        </div>
      </div>
    </ElDrawer>

    <!-- 登录弹窗 -->
    <ElDialog v-model="loginVisible" title="登录应用商店" width="400" :close-on-click-modal="false">
      <ElForm :model="loginForm" @submit.prevent="submitLogin" label-position="top">
        <ElFormItem label="用户名/邮箱" required>
          <ElInput v-model="loginForm.username" placeholder="请输入用户名或邮箱" clearable>
            <template #prefix>
              <ArtSvgIcon icon="ri:user-line" />
            </template>
          </ElInput>
        </ElFormItem>
        <ElFormItem label="密码" required>
          <ElInput
            v-model="loginForm.password"
            type="password"
            placeholder="请输入密码"
            show-password
            clearable
          >
            <template #prefix>
              <ArtSvgIcon icon="ri:lock-line" />
            </template>
          </ElInput>
        </ElFormItem>
        <ElFormItem label="验证码" required>
          <div class="flex gap-2 w-full">
            <ElInput v-model="loginForm.code" placeholder="请输入验证码" clearable class="flex-1">
              <template #prefix>
                <ArtSvgIcon icon="ri:shield-check-line" />
              </template>
            </ElInput>
            <img
              :src="captchaImage"
              @click="getCaptcha"
              class="h-8 w-24 cursor-pointer rounded"
              title="点击刷新"
            />
          </div>
        </ElFormItem>
        <ElFormItem>
          <ElButton type="primary" native-type="submit" class="w-full" :loading="loginLoading">
            登录
          </ElButton>
        </ElFormItem>
        <div class="text-center text-sm text-gray-400">
          还没有账号？
          <ElLink type="primary" @click="handleRegister">立即注册</ElLink>
        </div>
      </ElForm>
    </ElDialog>

    <!-- 已购应用抽屉 -->
    <ElDrawer v-model="purchasedVisible" title="已购应用" :size="720">
      <div v-loading="purchasedLoading" class="purchased-list">
        <div v-for="app in purchasedApps" :key="app.id" class="purchased-card">
          <img :src="app.logo" class="purchased-logo" />
          <div class="purchased-info">
            <div class="purchased-title">{{ app.title }}</div>
            <div class="purchased-version"> v{{ app.version }} · {{ app.developer }} </div>
            <div class="purchased-about">{{ app.about }}</div>
          </div>
          <div class="gap-2">
            <ElButton size="small" @click="viewDocs(app)">
              <template #icon>
                <ArtSvgIcon icon="ri:book-line" />
              </template>
              文档
            </ElButton>
            <ElButton type="primary" size="small" @click="showVersions(app)">
              <template #icon>
                <ArtSvgIcon icon="ri:download-line" />
              </template>
              下载
            </ElButton>
          </div>
        </div>
        <ElEmpty
          v-if="!purchasedLoading && purchasedApps.length === 0"
          description="暂无已购应用"
        />
      </div>
    </ElDrawer>

    <!-- 版本选择对话框 -->
    <ElDialog
      v-model="versionVisible"
      :title="'选择版本 - ' + (currentPurchasedApp?.title || '')"
      width="500"
    >
      <div v-loading="versionLoading" class="version-list">
        <div v-for="ver in versionList" :key="ver.id" class="version-item">
          <div>
            <div class="version-info-row">
              <span class="version-name">v{{ ver.version }}</span>
              <span class="version-date">{{ ver.create_time }}</span>
            </div>
            <div class="version-remark">{{ ver.remark }}</div>
          </div>
          <ElButton
            type="primary"
            size="small"
            :loading="downloadingId === ver.id"
            @click="downloadVersion(ver)"
          >
            下载安装
          </ElButton>
        </div>
        <ElEmpty v-if="!versionLoading && versionList.length === 0" description="暂无可用版本" />
      </div>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { ref, reactive, computed, onMounted, watch } from 'vue'
  import { ElMessage, ElMessageBox } from 'element-plus'
  import type { ColumnOption } from '@/types'
  import type { UploadFile } from 'element-plus'
  import sandpackageApi, {
    type VersionInfo,
    type StoreApp,
    type StoreUser,
    type PurchasedApp,
    type AppVersion
  } from '../api/index'
  import InstallForm from './install-box.vue'
  import TerminalBox from './terminal.vue'
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

  const failedUpgradeRows = computed(() =>
    installList.value.filter((row) => isFailedUpgradeRecovery(row))
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
      if (!isFailedUpgradeRecovery(row)) continue
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
    installFormRef.value?.open()
  }

  const rejectOrdinaryAction = (record: SandpackageInstallRow): boolean => {
    if (!isFailedUpgradeRecovery(record)) return false
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

  const handleInstall = async (record: SandpackageInstallRow) => {
    if (rejectOrdinaryAction(record)) return
    // 检查
    if (version.value?.sandpackage_version?.state === 'fail') {
      ElMessage.error('插件市场sandpackage版本检测失败')
      return
    }

    // 检查版本兼容性
    if (!checkVersionCompatibility(record.support, version.value?.sandadmin_version?.describe)) {
      ElMessage.error(
        `此插件仅支持 ${record.support} 版本框架，当前框架版本为 ${version.value?.sandadmin_version?.describe}，不兼容无法安装`
      )
      return
    }

    try {
      const result = await sandpackageApi.installApp({ appName: record.app })
      if (result.state === 1) {
        ElMessage.success('安装成功')
      } else if (result.state === 4) {
        ElMessage.info('文件已部署，等待完成依赖安装')
      } else {
        ElMessage.warning(result.last_error || result.stage_label || '安装未完成，请查看插件状态')
      }
      getList()
    } catch {
      // Error already handled by http utility
    }
  }

  const handleUninstall = async (record: SandpackageInstallRow) => {
    if (rejectOrdinaryAction(record)) return
    await sandpackageApi.uninstallApp({ appName: record.app })
    ElMessage.success('卸载成功')
    getList()
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
    return (
      isUpgradeCandidateStage(record) &&
      record.upgrade_candidate_verified === true &&
      hasCurrentAppBackup(record) &&
      /^[a-f0-9]{64}$/.test(record.registration_manifest ?? '') &&
      /^[a-f0-9]{64}$/.test(record.runtime_manifest ?? '') &&
      isStrictSemver(record.version) &&
      isStrictSemver(record.upgrade_from_version) &&
      record.upgrade_from_version !== undefined &&
      compareStrictSemver(record.version, record.upgrade_from_version) === 1
    )
  }

  const isLegacyRecoverableCandidate = (record: SandpackageInstallRow): boolean => {
    return (
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
    return record.state === 2 && record.stage === 'ready' && record.update === 1
  }

  const isCompatibleUpgradeCandidate = (record: SandpackageInstallRow): boolean => {
    return (
      isReadyUpgradeCandidate(record) &&
      checkVersionCompatibility(record.support, version.value?.sandadmin_version?.describe)
    )
  }

  const handleUpgradeCandidate = async (record: SandpackageInstallRow) => {
    if (rejectOrdinaryAction(record)) return
    if (!checkVersionCompatibility(record.support, version.value?.sandadmin_version?.describe)) {
      ElMessage.error('升级候选与当前宿主版本不兼容，不能升级；如无需升级可撤回候选')
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
    try {
      await sandpackageApi.installApp({ appName: record.app, confirmation })
      ElMessage.success('升级已提交执行')
      getList()
    } catch {
      // Error already handled by http utility
    }
  }

  const handleDiscardCandidate = async (record: SandpackageInstallRow) => {
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
    try {
      await sandpackageApi.discardCandidate({
        appName: record.app,
        confirmation
      })
      ElMessage.success('升级候选已撤回，旧插件注册包已恢复；数据库未执行无需回滚')
      getList()
    } catch {
      // Error already handled by http utility
    }
  }

  const handleRegisterExisting = async (record: SandpackageInstallRow) => {
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

    try {
      await sandpackageApi.registerExisting({
        appName: record.app,
        confirmation
      })
      ElMessage.success('插件登记完成')
      getList()
    } catch {
      // Error already handled by http utility
    }
  }

  const stateText = (record: SandpackageInstallRow): string => {
    if (isFailedUpgradeRecovery(record)) return '升级未完成'
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
    if (rejectOrdinaryAction(record)) return
    const extend = 'module-install:' + record.app
    terminalRef.value?.open()
    setTimeout(() => {
      terminalRef.value?.frontInstall(extend)
    }, 500)
  }

  const handleExecBackend = (record: SandpackageInstallRow) => {
    if (rejectOrdinaryAction(record)) return
    const extend = 'module-install:' + record.app
    terminalRef.value?.open()
    setTimeout(() => {
      terminalRef.value?.backendInstall(extend)
    }, 500)
  }

  const handleTerminal = () => {
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

  // ========== 在线商店相关 ==========
  const detailVisible = ref(false)
  const currentApp = ref<StoreApp | null>(null)
  const storeUser = ref<StoreUser | null>(null)
  const storeToken = ref(localStorage.getItem('storeToken') || '')
  const onlineApps = ref<StoreApp[]>([])
  const onlineLoading = ref(false)
  const onlinePagination = reactive({
    current: 1,
    size: 12,
    total: 0
  })

  // 登录相关
  const loginVisible = ref(false)
  const loginLoading = ref(false)
  const captchaImage = ref('')
  const captchaUuid = ref('')
  const loginForm = reactive({
    username: '',
    password: '',
    code: ''
  })

  // 搜索表单
  const searchForm = reactive({
    keywords: '',
    type: '' as string | number,
    price: 'all'
  })

  // 已购应用相关
  const purchasedVisible = ref(false)
  const purchasedLoading = ref(false)
  const purchasedApps = ref<PurchasedApp[]>([])
  const versionVisible = ref(false)
  const versionLoading = ref(false)
  const versionList = ref<AppVersion[]>([])
  const currentPurchasedApp = ref<PurchasedApp | null>(null)
  const downloadingId = ref<number | null>(null)

  const handleLogin = () => {
    loginVisible.value = true
    getCaptcha()
  }

  const handleRegister = () => {
    window.open('https://saas.saithink.top/register', '_blank')
  }

  const handleLogout = () => {
    storeUser.value = null
    storeToken.value = ''
    localStorage.removeItem('storeToken')
  }

  const getCaptcha = async () => {
    try {
      const response = await sandpackageApi.getStoreCaptcha()
      captchaImage.value = response?.image || ''
      captchaUuid.value = response?.uuid || ''
    } catch {
      // Error already handled by http utility
    }
  }

  const submitLogin = async () => {
    if (!loginForm.username || !loginForm.password || !loginForm.code) {
      ElMessage.warning('请填写完整信息')
      return
    }

    loginLoading.value = true
    try {
      const response = await sandpackageApi.storeLogin({
        username: loginForm.username,
        password: loginForm.password,
        code: loginForm.code,
        uuid: captchaUuid.value
      })

      storeToken.value = response?.access_token || ''
      localStorage.setItem('storeToken', response?.access_token || '')
      loginVisible.value = false
      loginForm.username = ''
      loginForm.password = ''
      loginForm.code = ''
      await fetchStoreUser()
      ElMessage.success('登录成功')
    } catch {
      getCaptcha()
      // Error already handled by http utility
    } finally {
      loginLoading.value = false
    }
  }

  const fetchStoreUser = async () => {
    if (!storeToken.value) return

    try {
      const response = await sandpackageApi.getStoreUserInfo(storeToken.value)
      storeUser.value = response || null
    } catch {
      handleLogout()
    }
  }

  const fetchOnlineApps = async () => {
    onlineLoading.value = true
    try {
      const response = await sandpackageApi.getOnlineAppList({
        page: onlinePagination.current,
        limit: onlinePagination.size,
        price: searchForm.price,
        type: searchForm.type,
        keywords: searchForm.keywords
      })

      onlineApps.value = response?.data || []
      onlinePagination.total = response?.total || 0
    } catch {
      // Error already handled by http utility
    } finally {
      onlineLoading.value = false
    }
  }

  const showDetail = (item: StoreApp) => {
    currentApp.value = item
    detailVisible.value = true
  }

  const renderMarkdown = (content?: string) => {
    if (!content) return ''
    return content
      .replace(/^### (.+)$/gm, '<h3>$1</h3>')
      .replace(/^## (.+)$/gm, '<h2>$1</h2>')
      .replace(/^# (.+)$/gm, '<h1>$1</h1>')
      .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
      .replace(/\*(.+?)\*/g, '<em>$1</em>')
      .replace(/`(.+?)`/g, '<code>$1</code>')
      .replace(/^- (.+)$/gm, '<li>$1</li>')
      .replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>')
      .replace(/\n/g, '<br/>')
      .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank">$1</a>')
  }

  const handleBuy = () => {
    window.open('https://saas.saithink.top/apps', '_blank')
  }

  const showPurchasedApps = async () => {
    purchasedVisible.value = true
    purchasedLoading.value = true

    try {
      const response = await sandpackageApi.getPurchasedApps(storeToken.value)
      purchasedApps.value = response || []
    } catch {
      // Error already handled by http utility
    }
    purchasedLoading.value = false
  }

  const viewDocs = (app: PurchasedApp) => {
    window.open(`https://saas.saithink.top/docs/${app.appname}`, '_blank')
  }

  const showVersions = async (app: PurchasedApp) => {
    currentPurchasedApp.value = app
    versionVisible.value = true
    versionLoading.value = true

    try {
      const response = await sandpackageApi.getAppVersions(storeToken.value, app.app_id)
      versionList.value = response || []
    } catch {
      // Error already handled by http utility
    }
    versionLoading.value = false
  }

  const downloadVersion = async (ver: AppVersion) => {
    downloadingId.value = ver.id

    try {
      await sandpackageApi.downloadApp({
        token: storeToken.value,
        id: ver.id
      })

      ElMessage.success('下载成功，即将刷新插件列表...')
      versionVisible.value = false
      purchasedVisible.value = false
      activeTab.value = 'local'
      getList()
    } catch {
      // Error already handled by http utility
    }
    downloadingId.value = null
  }

  // 监听 tab 切换
  watch(activeTab, (val) => {
    if (val === 'online') {
      fetchOnlineApps()
      fetchStoreUser()
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
    margin-top: 16px;
    padding: 16px;
    border: 1px solid var(--el-border-color);
    border-radius: 8px;
    background: var(--el-bg-color);
  }

  .failed-upgrade-steps {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin: 12px 0 8px;
    font-size: 14px;
    color: var(--el-text-color-regular);
  }

  .failed-upgrade-steps .current {
    color: var(--el-color-primary);
    font-weight: 600;
  }

  .step-arrow {
    color: var(--el-text-color-secondary);
  }

  .failed-upgrade-meta,
  .failed-upgrade-op-hint,
  .failed-upgrade-upload-tip {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    line-height: 1.5;
  }

  .failed-upgrade-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    gap: 12px;
    margin-top: 16px;
  }

  .failed-upgrade-error-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
  }

  .version-title {
    padding: 5px 10px;
    background: var(--el-fill-color-light);
    border: 1px solid var(--el-border-color);
    font-size: 12px;
  }

  .version-value {
    padding: 5px 10px;
    border: 1px solid var(--el-border-color);
    font-size: 12px;
  }

  .app-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(320px, 100%), 1fr));
    gap: 16px;
  }

  .app-card {
    background: var(--el-bg-color);
    border-radius: 8px;
    padding: 16px;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 1px solid var(--el-border-color);

    &:hover {
      box-shadow: var(--el-box-shadow-light);
      transform: translateY(-2px);
    }
  }

  .app-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
  }

  .app-logo {
    width: 48px;
    height: 48px;
    border-radius: 8px;
    object-fit: cover;
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

  .app-price {
    font-size: 16px;
    font-weight: 600;
    color: var(--el-color-danger);

    &.free {
      color: var(--el-color-success);
    }
  }

  .app-about {
    font-size: 13px;
    color: var(--el-text-color-regular);
    line-height: 1.5;
    margin-bottom: 12px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }

  .app-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 12px;
    color: var(--el-text-color-secondary);
  }

  .app-author {
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .author-avatar {
    width: 20px;
    height: 20px;
    border-radius: 50%;
  }

  .app-sales {
    display: flex;
    align-items: center;
  }

  .detail-content {
    padding: 16px 0;
  }

  .detail-price {
    font-size: 24px;
    font-weight: 600;
    color: var(--el-color-danger);
    margin-bottom: 16px;

    &.free {
      color: var(--el-color-success);
    }
  }

  .detail-about {
    font-size: 14px;
    color: var(--el-text-color-regular);
    line-height: 1.6;
    margin-bottom: 24px;
  }

  .desc-content {
    font-size: 14px;
    color: var(--el-text-color-regular);
    line-height: 1.8;

    :deep(code) {
      background: var(--el-fill-color);
      padding: 2px 6px;
      border-radius: 4px;
      font-size: 13px;
    }

    :deep(a) {
      color: var(--el-color-primary);
    }
  }

  .purchased-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .purchased-card {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 16px;
    padding: 16px;
    background: var(--el-bg-color);
    border-radius: 8px;
    border: 1px solid var(--el-border-color);
  }

  @media (max-width: 768px) {
    .failed-upgrade-card,
    .failed-upgrade-actions {
      width: 100%;
    }

    .failed-upgrade-steps {
      font-size: 13px;
    }

    .purchased-card,
    .version-item {
      flex-direction: column;
      align-items: flex-start;
    }
  }

  .purchased-logo {
    width: 56px;
    height: 56px;
    border-radius: 8px;
    object-fit: cover;
  }

  .purchased-info {
    flex: 1;
    min-width: 0;
  }

  .purchased-title {
    font-size: 15px;
    font-weight: 600;
    color: var(--el-text-color-primary);
    margin-bottom: 4px;
  }

  .purchased-version {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-bottom: 6px;
  }

  .purchased-about {
    font-size: 13px;
    color: var(--el-text-color-regular);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }

  .version-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .version-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px;
    background: var(--el-fill-color-light);
    border-radius: 6px;
  }

  .version-info-row {
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .version-name {
    font-weight: 600;
    color: var(--el-text-color-primary);
  }

  .version-date {
    font-size: 12px;
    color: var(--el-text-color-secondary);
  }

  .version-remark {
    flex: 1;
    font-size: 13px;
    color: var(--el-text-color-regular);
  }
</style>
