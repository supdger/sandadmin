import '@douyinfe/semi-ui/react19-adapter';
import '@/i18n/i18n';

import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { LoginLeftView } from './LoginLeftView';

describe('LoginLeftView', () => {
  it('渲染产品名、标语与说明', () => {
    render(<LoginLeftView />);
    expect(screen.getByText('SandAdmin')).toBeInTheDocument();
    expect(screen.getByText('一套界面，管好整摊生意')).toBeInTheDocument();
    expect(
      screen.getByText('菜单、权限、字典、文件和后台任务都在一个地方，点开就能用。'),
    ).toBeInTheDocument();
  });
});
