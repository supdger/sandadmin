/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_API_BASE_URL?: string;
  readonly VITE_APP_NAME?: string;
  readonly VITE_APP_LOGO?: string;
  readonly VITE_APP_COPYRIGHT?: string;
  readonly VITE_DEFAULT_LOCALE?: string;
  // SAND-DEV-BEGIN
  readonly VITE_ENABLE_MOCKS?: string;
  readonly VITE_MOCK_VIEWER_PASSWORD?: string;
  readonly VITE_MOCK_GUEST_PASSWORD?: string;
  // SAND-DEV-END
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}
