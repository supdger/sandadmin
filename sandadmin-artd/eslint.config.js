import { existsSync, readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import js from '@eslint/js'
import globals from 'globals'
import tseslint from 'typescript-eslint'
import pluginVue from 'eslint-plugin-vue'
import eslintConfigPrettier from 'eslint-config-prettier'

// unplugin-auto-import 在 vite dev/build 时生成 .auto-import.json（已在 .gitignore）。
// 未生成时跳过，lint 仍可运行，只是 auto-import 的 API 会按未定义变量处理。
function readAutoImportGlobals() {
  const filePath = fileURLToPath(new URL('./.auto-import.json', import.meta.url))
  if (!existsSync(filePath)) return {}
  const { globals: autoImportGlobals = {} } = JSON.parse(readFileSync(filePath, 'utf8'))
  return Object.fromEntries(Object.keys(autoImportGlobals).map((name) => [name, 'readonly']))
}

export default tseslint.config(
  {
    ignores: [
      'dist/**',
      'dist-ssr/**',
      'node_modules/**',
      // unplugin-vue-components / unplugin-auto-import 的生成物，不属于手写源码
      'src/types/import/**'
    ]
  },
  js.configs.recommended,
  ...tseslint.configs.recommended,
  ...pluginVue.configs['flat/recommended'],
  {
    files: ['**/*.vue'],
    languageOptions: {
      parserOptions: {
        parser: tseslint.parser,
        extraFileExtensions: ['.vue']
      }
    }
  },
  {
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: {
        ...globals.browser,
        ...globals.node,
        ...readAutoImportGlobals(),
        // vite.config.ts define 注入的编译期常量（声明见 src/env.d.ts）
        __APP_VERSION__: 'readonly'
      }
    }
  },
  {
    rules: {
      // TS 已承担未定义变量的静态检查，auto-import 名称也不在本文件 import 中
      'no-undef': 'off',
      // 存量代码大量使用 any 承接第三方响应与动态表单值，先降 warn 后续逐步收窄
      '@typescript-eslint/no-explicit-any': 'warn',
      // 模板/脚本里未使用变量的存量较多，保留规则但允许下划线豁免
      '@typescript-eslint/no-unused-vars': [
        'warn',
        { argsIgnorePattern: '^_', varsIgnorePattern: '^_', caughtErrorsIgnorePattern: '^_' }
      ],
      // 单文件导出组件名多为 index.vue + defineOptions name，规则对存量误判较多
      'vue/multi-word-component-names': 'warn',
      // 存量 props 普遍依赖 withDefaults 之外的调用方传值
      'vue/require-default-prop': 'warn'
    }
  },
  eslintConfigPrettier
)
