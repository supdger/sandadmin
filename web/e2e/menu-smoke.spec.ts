import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

// 菜单页冒烟（S-15）：mock 登录后从 /me/menus 取菜单树，逐个 page 节点
// 打开，断言无 JS 报错、无 console error、非空白页、未落到错误态页。
// 外链（type 'link'）与隐藏节点不跑。

interface MenuNode {
  type: string;
  path: string | null;
  children?: MenuNode[];
}

const TOKEN_KEY = 'sandadmin.token';
// ErrorState 的标题文案：菜单页渲染出这些说明走到了 403/404/500/通用错误态。
const ERROR_TITLES = ['页面不存在', '没有权限', '出了点问题'];

async function loginAsAdmin(page: Page): Promise<void> {
  await page.goto('/login');
  // bootstrap 要等 msw worker.start 才 render；见到根节点说明 mock 已接管请求。
  await page.waitForSelector('#root > *', { timeout: 15_000 });
  const token = await page.evaluate(async () => {
    const res = await fetch('/api/auth/login', {
      method: 'POST',
      headers: { 'content-type': 'application/json' },
      body: JSON.stringify({ username: 'admin', password: 'Admin@123456' }),
    });
    const body = (await res.json()) as { data?: { access_token?: string } };
    return body.data?.access_token ?? null;
  });
  expect(token, 'mock 登录应返回 access_token').toBeTruthy();
  await page.evaluate(
    ([key, value]) => window.localStorage.setItem(key, value),
    [TOKEN_KEY, token as string],
  );
}

async function menuPagePaths(page: Page): Promise<string[]> {
  await page.waitForSelector('#root > *', { timeout: 15_000 });
  const items = await page.evaluate(async () => {
    const token = window.localStorage.getItem('sandadmin.token');
    // msw service worker 在新文档上接管请求有启动窗口，
    // 拿到非 JSON 响应（被 bypass 回 index.html）时重试。
    for (let attempt = 0; attempt < 20; attempt++) {
      const res = await fetch('/api/admin/v1/me/menus', {
        headers: { authorization: `Bearer ${token}` },
      });
      if ((res.headers.get('content-type') ?? '').includes('json')) {
        const body = (await res.json()) as { data?: { items?: MenuNode[] } };
        return body.data?.items ?? [];
      }
      await new Promise((resolve) => setTimeout(resolve, 300));
    }
    return null;
  });
  const paths: string[] = [];
  const walk = (nodes: MenuNode[]): void => {
    for (const node of nodes) {
      if (node.type === 'page' && node.path) {
        paths.push(node.path);
      }
      if (node.children) {
        walk(node.children);
      }
    }
  };
  walk(items ?? []);
  return paths;
}

test('每个菜单页可打开且无报错、无空白', async ({ page }) => {
  test.setTimeout(300_000);
  await loginAsAdmin(page);
  await page.goto('/');
  const paths = await menuPagePaths(page);
  expect(paths.length, '/me/menus 应返回至少一个菜单页').toBeGreaterThan(0);

  const failures: string[] = [];
  for (const path of paths) {
    const errors: string[] = [];
    const onConsole = (msg: { type: () => string; text: () => string }): void => {
      if (msg.type() === 'error') {
        errors.push(`console: ${msg.text()}`);
      }
    };
    const onPageError = (err: Error): void => {
      errors.push(`pageerror: ${err.message}`);
    };
    page.on('console', onConsole);
    page.on('pageerror', onPageError);
    try {
      await page.goto(path);
      await page.waitForSelector('#root > *', { timeout: 10_000 });
      // 页面查询没收敛（Spin 不消失）说明有请求挂住或 mock 缺失，也算失败。
      // Semi 的包裹型 Spin 转完后保留 hidden 占位节点，只把未隐藏的算作未收敛。
      const settled = await page
        .waitForFunction(
          () => !document.querySelector('.semi-spin:not(.semi-spin-hidden)'),
          undefined,
          { timeout: 10_000 },
        )
        .then(() => true)
        .catch(() => false);
      if (!settled) {
        errors.push('加载未收敛：页面 Spin 持续存在');
      }

      const rootText = await page.evaluate(
        () => document.getElementById('root')?.innerText.trim() ?? '',
      );
      if (rootText.length < 20) {
        errors.push(`root 内容过短（${rootText.length} 字符），疑似空白页`);
      }
      for (const title of ERROR_TITLES) {
        if (rootText.includes(title)) {
          errors.push(`页面呈现错误态「${title}」`);
        }
      }
    } catch (err) {
      errors.push(`打开失败: ${err instanceof Error ? err.message : String(err)}`);
    } finally {
      page.off('console', onConsole);
      page.off('pageerror', onPageError);
    }
    if (errors.length > 0) {
      failures.push(`${path}: ${errors.join(' | ')}`);
    } else {
      console.log(`ok ${path}`);
    }
  }

  expect(failures, `菜单页冒烟失败 ${failures.length}/${paths.length}`).toEqual([]);
});
