<!-- 登录页面 -->
<template>
  <div class="login-page">
    <LoginLeftView />

    <main class="login-shell">
      <div class="auth-right-wrap">
        <section class="login-card" aria-labelledby="login-page-title">
          <ElAlert
            v-if="isDevExperience"
            class="dev-login-banner"
            type="warning"
            :closable="false"
            show-icon
            title="开发模式：仅本机可用，正式环境会自动关闭"
          />
          <div class="form">
            <h3 id="login-page-title" class="title">{{ $t('login.title') }}</h3>
            <p class="sub-title">{{ $t('login.subTitle') }}</p>
            <ElAlert
              v-if="devError"
              class="dev-login-error"
              type="error"
              :closable="false"
              show-icon
              :title="devError"
            />
            <ElForm
              ref="formRef"
              :model="formData"
              :rules="rules"
              :key="formKey"
              @keyup.enter="handleEnter"
              class="login-form"
            >
              <div class="form-field">
                <label class="field-label" for="login-username">账号</label>
                <ElFormItem class="login-form-item" prop="username">
                  <ElInput
                    id="login-username"
                    class="custom-height"
                    :placeholder="$t('login.placeholder.username')"
                    v-model.trim="formData.username"
                  >
                    <template #prefix><ArtSvgIcon icon="ri:user-3-line" /></template>
                  </ElInput>
                </ElFormItem>
              </div>

              <div class="form-field">
                <label class="field-label" for="login-password">密码</label>
                <ElFormItem class="login-form-item" prop="password">
                  <ElInput
                    id="login-password"
                    class="custom-height"
                    :placeholder="$t('login.placeholder.password')"
                    v-model.trim="formData.password"
                    type="password"
                    autocomplete="off"
                    show-password
                  >
                    <template #prefix><ArtSvgIcon icon="ri:lock-2-line" /></template>
                  </ElInput>
                </ElFormItem>
              </div>

              <div class="form-field" v-if="!isDevExperience">
                <label class="field-label" for="login-code">验证码</label>
                <ElFormItem class="login-form-item" prop="code">
                  <div class="captcha-row">
                    <ElInput
                      id="login-code"
                      class="custom-height"
                      :placeholder="$t('login.placeholder.code')"
                      v-model.trim="formData.code"
                      type="text"
                      autocomplete="off"
                    >
                      <template #prefix><ArtSvgIcon icon="ri:shield-check-line" /></template>
                    </ElInput>
                    <button
                      class="captcha-button"
                      type="button"
                      aria-label="刷新验证码"
                      @click="refreshCaptcha"
                    >
                      <img :src="captcha" class="captcha-image" alt="验证码" />
                    </button>
                  </div>
                </ElFormItem>
              </div>

              <div class="login-options">
                <ElCheckbox v-model="formData.rememberPassword">{{
                  $t('login.rememberPwd')
                }}</ElCheckbox>
                <span class="forgot-password">{{ $t('login.forgetPwd') }}</span>
              </div>

              <div class="login-submit-wrap">
                <template v-if="isDevExperience && devEntry">
                  <ElButton
                    id="dev-enter-button"
                    class="w-full custom-height"
                    type="primary"
                    @click="handleDevEnter"
                    :loading="devLoading"
                    v-ripple
                  >
                    一键进入
                  </ElButton>
                  <button type="button" class="dev-switch-account" @click="useOtherAccount">
                    用其他账号登录
                  </button>
                </template>
                <ElButton
                  v-else
                  class="w-full custom-height"
                  type="primary"
                  @click="handleSubmit"
                  :loading="loading"
                  v-ripple
                >
                  {{ $t('login.btnText') }} SandAdmin
                </ElButton>
              </div>

              <p class="login-security-note">
                <ArtSvgIcon icon="ri:shield-check-line" />
                安全连接已启用，数据传输全程加密
              </p>

              <!-- <div class="mt-5 text-sm text-gray-600">
                <span>{{ $t('login.noAccount') }}</span>
                <RouterLink class="text-theme" :to="{ name: 'Register' }">{{
                  $t('login.register')
                }}</RouterLink>
              </div> -->
            </ElForm>
          </div>
        </section>
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
  import AppConfig from '@/config'
  import { useUserStore } from '@/store/modules/user'
  import { useI18n } from 'vue-i18n'
  import { HttpError } from '@/utils/http/error'
  import { fetchCaptcha, fetchLogin, fetchDevLogin, fetchGetUserInfo } from '@/api/auth'
  import { ElNotification, type FormInstance, type FormRules } from 'element-plus'

  defineOptions({ name: 'Login' })

  const { t, locale } = useI18n()
  const formKey = ref(0)

  // 监听语言切换，重置表单
  watch(locale, () => {
    formKey.value++
  })

  const userStore = useUserStore()
  const router = useRouter()

  const captcha = ref(
    'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'
  )

  const systemName = AppConfig.systemInfo.name
  const formRef = ref<FormInstance>()

  // 开发体验门控：构建期开关 VITE_SAND_DEV_LOGIN=1 且页面由回环主机名访问。
  // 两个条件缺一个即生产体验：提示条、一键进入、dev=1 一概不渲染不生效。
  const LOOPBACK_HOSTS = ['127.0.0.1', 'localhost', '::1', '[::1]']
  const isDevExperience =
    import.meta.env.VITE_SAND_DEV_LOGIN === '1' && LOOPBACK_HOSTS.includes(location.hostname)

  // 开发体验下账号密码预填展示：密码只是占位圆点，真实发牌走免口令 dev 接口
  const DEV_USERNAME = 'admin'
  const DEV_PASSWORD_PLACEHOLDER = '········'

  const formData = reactive({
    username: isDevExperience ? DEV_USERNAME : '',
    password: isDevExperience ? DEV_PASSWORD_PLACEHOLDER : '',
    code: '',
    uuid: '',
    rememberPassword: true
  })

  const rules = computed<FormRules>(() => ({
    username: [{ required: true, message: t('login.placeholder.username'), trigger: 'blur' }],
    password: [{ required: true, message: t('login.placeholder.password'), trigger: 'blur' }],
    ...(isDevExperience
      ? {}
      : { code: [{ required: true, message: t('login.placeholder.code'), trigger: 'blur' }] })
  }))

  const loading = ref(false)

  // devEntry=true 显示一键进入；点了「用其他账号登录」或 dev 接口失败后回落为普通表单
  const devEntry = ref(isDevExperience)
  const devLoading = ref(false)
  const devError = ref('')
  // dev=1 参数只自动执行一次，避免失败后无限重试
  const devAutoRan = ref(false)

  const route = useRoute()

  onMounted(() => {
    if (!isDevExperience) {
      refreshCaptcha()
      return
    }
    nextTick(() => document.getElementById('dev-enter-button')?.focus())
    if (route.query.dev === '1' && !devAutoRan.value) {
      devAutoRan.value = true
      handleDevEnter()
    }
  })

  // 开发体验主按钮聚焦时回车走一键进入，其余情况走普通登录
  const handleEnter = () => {
    if (isDevExperience && devEntry.value) {
      handleDevEnter()
      return
    }
    handleSubmit()
  }

  // 一键进入：调免口令 dev 接口，成功后与正常登录走同一段后续流程
  const handleDevEnter = async () => {
    if (devLoading.value) return
    devLoading.value = true
    devError.value = ''
    try {
      const data = await fetchDevLogin()
      await completeLogin(data)
    } catch (error) {
      // 入口不存在（404）或失败时回落普通表单，不自动重试
      useOtherAccount()
      devError.value =
        error instanceof HttpError ? error.message : '开发模式登录失败，请检查服务端开关后重试'
    } finally {
      devLoading.value = false
    }
  }

  const useOtherAccount = () => {
    devEntry.value = false
    devError.value = ''
    formData.username = ''
    formData.password = ''
  }

  // 拿到令牌后的公共流程：存 token、拉用户信息、按强制改密或原目标页跳转
  const completeLogin = async (data: Api.Auth.LoginResponse) => {
    const { access_token, refresh_token, must_change_password } = data
    if (!access_token) {
      throw new Error('Login failed - no token received')
    }

    userStore.setToken(access_token, refresh_token)
    const user = await fetchGetUserInfo()
    userStore.setUserInfo({
      ...user,
      must_change_password: must_change_password === true || user.must_change_password === true
    })
    userStore.setLoginStatus(true)

    // 初始密码必须先修改：跳转改密页，修改前不进入后台
    if (userStore.info.must_change_password === true) {
      router.push({ name: 'ChangePassword' })
      return
    }

    showLoginSuccessNotice()
    // 只允许站内地址：以 / 开头且不是协议相对地址（// 开头会被当成外站）
    const redirect = route.query.redirect
    const target =
      typeof redirect === 'string' && redirect.startsWith('/') && !redirect.startsWith('//')
        ? redirect
        : '/'
    router.push(target)
  }

  // 登录
  const handleSubmit = async () => {
    if (!formRef.value) return

    try {
      // 表单验证
      const valid = await formRef.value.validate()
      if (!valid) return

      loading.value = true

      // 登录请求
      const data = await fetchLogin({
        username: formData.username,
        password: formData.password,
        code: formData.code,
        uuid: formData.uuid
      })

      await completeLogin(data)
    } catch (error) {
      // 处理 HttpError
      if (error instanceof HttpError) {
        // console.log(error.code)
      } else {
        // 处理非 HttpError
        // ElMessage.error('登录失败，请稍后重试')
        console.error('[Login] Unexpected error:', error)
      }
    } finally {
      if (!isDevExperience) {
        refreshCaptcha()
      }
      loading.value = false
    }
  }

  // 获取验证码
  const refreshCaptcha = async () => {
    fetchCaptcha().then((res) => {
      formData.uuid = res.uuid
      captcha.value = res.image
    })
  }

  // 登录成功提示
  const showLoginSuccessNotice = () => {
    setTimeout(() => {
      ElNotification({
        title: t('login.success.title'),
        type: 'success',
        duration: 2500,
        zIndex: 10000,
        message: `${t('login.success.message')}, ${systemName}!`
      })
    }, 150)
  }
</script>

<style scoped>
  @import './style.css';
</style>

<style lang="scss" scoped>
  :deep(.el-select__wrapper) {
    height: 40px !important;
  }
</style>
