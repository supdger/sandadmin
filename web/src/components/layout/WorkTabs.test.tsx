import '@douyinfe/semi-ui/react19-adapter';

import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { afterEach, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { getTabsState, openTab, resetTabs } from '@/stores/tabs';
import { WorkTabs } from './WorkTabs';

function renderTabs(entry = '/b') {
  const router = createMemoryRouter(
    [
      {
        element: <WorkTabs />,
        children: [
          { path: '/', element: <div /> },
          { path: '/a', element: <div /> },
          { path: '/b', element: <div /> },
          { path: '*', element: <div /> },
        ],
      },
    ],
    { initialEntries: [entry] },
  );
  render(<RouterProvider router={router} />);
  return router;
}

describe('WorkTabs', () => {
  afterEach(() => {
    resetTabs();
  });

  it('渲染已开标签，激活态跟 activeKey', () => {
    openTab({ key: '/a', path: '/a', title: '页面A' });
    openTab({ key: '/b', path: '/b', title: '页面B' });
    renderTabs('/b');

    expect(screen.getByRole('tab', { name: /页面A/ })).toHaveAttribute(
      'aria-selected',
      'false',
    );
    expect(screen.getByRole('tab', { name: /页面B/ })).toHaveAttribute(
      'aria-selected',
      'true',
    );
  });

  it('点标签跳路由并激活', async () => {
    openTab({ key: '/a', path: '/a', title: '页面A' });
    openTab({ key: '/b', path: '/b', title: '页面B' });
    const router = renderTabs('/b');

    await userEvent.click(screen.getByRole('tab', { name: /页面A/ }));

    await waitFor(() => expect(router.state.location.pathname).toBe('/a'));
    expect(getTabsState().activeKey).toBe('/a');
  });

  it('关闭激活标签后跳到相邻标签', async () => {
    openTab({ key: '/a', path: '/a', title: '页面A' });
    openTab({ key: '/b', path: '/b', title: '页面B' });
    const router = renderTabs('/b');

    await userEvent.click(screen.getByRole('button', { name: '关闭 页面B' }));

    expect(getTabsState().tabs.map((tab) => tab.key)).toEqual(['/a']);
    await waitFor(() => expect(router.state.location.pathname).toBe('/a'));
  });

  it('右键菜单支持关闭其他', async () => {
    openTab({ key: '/a', path: '/a', title: '页面A' });
    openTab({ key: '/b', path: '/b', title: '页面B' });
    renderTabs('/b');

    const { fireEvent } = await import('@testing-library/react');
    fireEvent.contextMenu(screen.getByRole('tab', { name: /页面B/ }));
    await userEvent.click(await screen.findByText('关闭其他'));

    expect(getTabsState().tabs.map((tab) => tab.key)).toEqual(['/b']);
  });
});
