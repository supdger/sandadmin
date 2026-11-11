import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { afterEach, describe, expect, it, vi } from 'vitest';

import '@/i18n/i18n';
import { env } from '@/config/env';
import { clearToken, setToken } from '@/lib/authToken';
import { server } from '@/mocks/server';
import { ImportantAnnouncementModal } from './ImportantAnnouncementModal';

if (typeof globalThis.ResizeObserver === 'undefined') {
  globalThis.ResizeObserver = class {
    observe() {}
    unobserve() {}
    disconnect() {}
  } as unknown as typeof ResizeObserver;
}

const base = `${env.apiBaseUrl}/admin/v1`;

const ITEMS = [
  {
    id: 'ann-1',
    title: '第一条重要公告',
    content: '<p>正文<strong>一</strong></p><script>window.__x=1</script>',
    level: 'important' as const,
    published_time: '2026-11-01T10:00:00Z',
    expire_time: null,
  },
  {
    id: 'ann-2',
    title: '第二条重要公告',
    content: '<p>正文二</p>',
    level: 'important' as const,
    published_time: '2026-11-02T10:00:00Z',
    expire_time: null,
  },
];

function renderModal(onCloseAll = vi.fn()) {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  render(
    <QueryClientProvider client={queryClient}>
      <ImportantAnnouncementModal items={ITEMS} onCloseAll={onCloseAll} />
    </QueryClientProvider>,
  );
  return onCloseAll;
}

describe('ImportantAnnouncementModal', () => {
  afterEach(() => {
    clearToken();
  });

  it('shows items one at a time and marks each read on 知道了', async () => {
    setToken('mock-admin-token');
    const readIds: string[] = [];
    server.use(
      http.post(`${base}/announcements/:id/read`, ({ params }) => {
        readIds.push(String(params.id));
        return HttpResponse.json({ data: { read: true }, request_id: 't' });
      }),
    );
    const onCloseAll = renderModal();

    expect(await screen.findByText('第一条重要公告')).toBeInTheDocument();
    // Script tags are stripped before rendering.
    expect(document.querySelector('script')).toBeNull();

    await userEvent.click(screen.getByRole('button', { name: '知道了' }));
    expect(await screen.findByText('第二条重要公告')).toBeInTheDocument();
    expect(readIds).toEqual(['ann-1']);

    await userEvent.click(screen.getByRole('button', { name: '知道了' }));
    await waitFor(() => expect(onCloseAll).toHaveBeenCalled());
    expect(readIds).toEqual(['ann-1', 'ann-2']);
  });
});
