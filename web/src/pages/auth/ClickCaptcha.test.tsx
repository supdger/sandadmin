import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { env } from '@/config/env';
import { server } from '@/mocks/server';
import { ClickCaptcha } from './ClickCaptcha';

const base = env.apiBaseUrl;

function renderCaptcha() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  render(
    <QueryClientProvider client={queryClient}>
      <ClickCaptcha onVerified={() => undefined} />
    </QueryClientProvider>,
  );
}

describe('ClickCaptcha', () => {
  it('shows the unified system notice and recovers via retry on HTTP 5xx', async () => {
    let calls = 0;
    server.use(
      http.get(`${base}/auth/captcha`, () => {
        calls += 1;
        if (calls === 1) {
          return HttpResponse.json({ code: 5000, message: 'boom', data: null }, { status: 500 });
        }
        return HttpResponse.json({
          code: 200,
          message: 'ok',
          data: {
            captchaId: 'captcha-ok',
            imageDataUrl: 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg"/>',
          },
        });
      }),
    );

    renderCaptcha();

    expect(await screen.findByText('系统出了点问题，请稍后再试')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '复制问题信息' })).toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: '重试' }));

    expect(await screen.findByTestId('captcha-image')).toBeInTheDocument();
  });

  it('shows the backend reason verbatim when verify is rejected', async () => {
    server.use(
      http.post(`${base}/auth/captcha/verify`, () =>
        HttpResponse.json({
          code: 4004,
          message: '点选验证暂时不可用，请稍后再试',
          data: null,
        }),
      ),
    );

    renderCaptcha();

    // Clicks are ignored until the captcha image has loaded.
    await screen.findByTestId('captcha-image');
    const box = screen.getByTestId('captcha-box');
    // Three clicks complete the point set and trigger the verify request.
    for (let i = 0; i < 3; i += 1) {
      fireEvent.click(box, { clientX: 40 + i * 30, clientY: 60 });
    }

    expect(await screen.findByText('点选验证暂时不可用，请稍后再试')).toBeInTheDocument();
    expect(screen.queryByText('点的位置不对，已换一张，请重新点选')).not.toBeInTheDocument();
  });
});
