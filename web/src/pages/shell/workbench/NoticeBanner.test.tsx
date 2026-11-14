import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { MemoryRouter } from 'react-router';
import { afterEach, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { env } from '@/config/env';
import { clearToken, setToken } from '@/lib/authToken';
import { server } from '@/mocks/server';
import { NoticeBanner } from './NoticeBanner';

// jsdom has no ResizeObserver; Semi's observer component needs a no-op stub.
if (typeof globalThis.ResizeObserver === 'undefined') {
  globalThis.ResizeObserver = class {
    observe() {}
    unobserve() {}
    disconnect() {}
  } as unknown as typeof ResizeObserver;
}

const v1 = `${env.apiBaseUrl}/admin/v1`;

/** 让后端把升级类 + 普通通知一起发回来，过滤交给前端验证。 */
function mockNoticesWithUpgrade() {
  server.use(
    http.get(`${v1}/dashboard/notices`, () =>
      HttpResponse.json({
        data: {
          items: [
            {
              notice_key: 'plugin-upgrade',
              level: 'warning',
              text_key: 'pluginUpgrade',
              params: { count: 2 },
              action: { label_key: 'goUpgrade', link: '/system/plugin' },
            },
            {
              notice_key: 'system-upgrade',
              level: 'info',
              text_key: 'pluginUpgrade',
              params: { count: 1 },
              action: { label_key: 'goUpgrade', link: '/system/update' },
            },
            {
              notice_key: 'ai-provider-missing',
              level: 'info',
              text_key: 'aiProviderMissing',
              params: null,
              action: { label_key: 'goConnect', link: '/plugin/sand-ai/provider' },
            },
          ],
        },
        request_id: 't',
      }),
    ),
  );
}

function renderBanner() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        <NoticeBanner />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('NoticeBanner', () => {
  afterEach(() => {
    clearToken();
  });

  it('有 system:update 权限的账号能看到升级提示', async () => {
    setToken('mock-admin-token');
    mockNoticesWithUpgrade();
    renderBanner();

    expect(await screen.findByText('有 2 个插件可以升级到新版本')).toBeInTheDocument();
  });

  it('无权限账号看不到升级提示，普通通知照常显示', async () => {
    setToken('mock-viewer-token');
    mockNoticesWithUpgrade();
    renderBanner();

    // 三条里两条是升级类，viewer 只看到 AI 服务商那条。
    expect(await screen.findByText('还没有连接 AI 服务商，AI 功能暂时用不了')).toBeInTheDocument();
    expect(screen.queryByText('有 2 个插件可以升级到新版本')).not.toBeInTheDocument();
    expect(screen.queryByText('有 1 个插件可以升级到新版本')).not.toBeInTheDocument();
  });
});
